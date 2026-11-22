<?php

namespace Tests\Unit\Services;

use App\Repositories\StyleRepository;
use App\Services\StyleService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StyleServiceTest extends TestCase
{
    private StyleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test user agents
        Config::set('app.user_agents', [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36',
        ]);
        Config::set('cache.default', 'array');
        Config::set('app.crawler.page_sizes.official_styles', 50);
        Config::set('app.crawler.delay.min', 0);
        Config::set('app.crawler.delay.max', 0);
        Config::set('app.crawler.retry.sleep_min', 0);
        Config::set('app.crawler.retry.sleep_max', 0);
        Config::set('app.crawler.batch_rest.offset.interval', 0);
        Config::set('app.crawler.batch_rest.detail.interval', 0);

        // Mock repository
        $mockRepository = $this->createMock(StyleRepository::class);
        $mockRepository->method('saveStyleFromOfficialStyling');

        $this->service = new StyleService($mockRepository);
    }

    public function test_fetch_styles_by_gender_stops_on_403_error()
    {
        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        Cache::flush();

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->service->fetchAllStyles('UNIQLO');

        $this->assertFalse($result, 'fetchAllStyles should return false on 403');
        // Page checkpoint should not be advanced when blocked by 403
        $this->assertNull(Cache::get('styles:page:UNIQLO:1'));

        // Last gender checkpoint is preserved for resumption (set before the 403 hit)
        // This allows the crawler to resume from gender 1 on next run
        $this->assertEquals('1', Cache::get('styles:last_gender:UNIQLO'));
    }

    public function test_fetch_styles_clears_checkpoints_after_completion()
    {
        Http::fake([
            '*' => Http::response([
                'result' => [
                    'styles' => [],
                    'total_styles' => 0,
                ],
            ]),
        ]);

        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        Cache::flush();

        $this->service->fetchAllStyles('UNIQLO');

        // All checkpoints should be cleared after completion
        $this->assertNull(Cache::get('styles:page:UNIQLO:1'));
        $this->assertNull(Cache::get('styles:last_gender:UNIQLO'));
    }

    public function test_fetch_styles_saves_page_checkpoint_after_each_completed_page()
    {
        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        Http::fake(['https://api.example.com/styles*' => fn ($request) => $request['page'] === 1
            ? Http::response(['result' => ['styles' => [], 'total_styles' => 100]])
            : Http::response([], 403),
        ]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllStyles('UNIQLO');

        $this->assertSame(2, Cache::get('styles:page:UNIQLO:1'), '第 1 頁做完、第 2 頁被擋時，下次要從第 2 頁續跑');
    }

    public function test_fetch_styles_by_gender_resumes_from_checkpoint()
    {
        Cache::set('styles:page:UNIQLO:1', 3);
        Cache::set('styles:last_gender:UNIQLO', '1');

        Http::fake([
            '*' => Http::response([
                'result' => [
                    'styles' => [],
                    'total_styles' => 0,
                ],
            ]),
        ]);

        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        $this->service->fetchAllStyles('UNIQLO');

        $this->assertSame([3], $this->listPagesForGender('1'), '性別 1 要從續跑點第 3 頁開始');

        // Checkpoints should be cleared after completion
        $this->assertNull(Cache::get('styles:page:UNIQLO:1'));
        $this->assertNull(Cache::get('styles:last_gender:UNIQLO'));
    }

    public function test_fetch_styles_fresh_ignores_checkpoint()
    {
        Cache::set('styles:page:UNIQLO:1', 10);
        Cache::set('styles:last_gender:UNIQLO', '2');

        Http::fake([
            '*' => Http::response([
                'result' => [
                    'styles' => [],
                    'total_styles' => 0,
                ],
            ]),
        ]);

        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        $this->service->fetchAllStyles('UNIQLO', fresh: true);

        // All checkpoints should be cleared
        $this->assertNull(Cache::get('styles:page:UNIQLO:1'));
        $this->assertNull(Cache::get('styles:last_gender:UNIQLO'));
    }

    public function test_fetch_style_details_stops_on_403_error()
    {
        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        Http::fake(function ($request) {
            $urlPath = parse_url($request->url(), PHP_URL_PATH);

            if ($urlPath === '/styles') {
                // List request
                return Http::response([
                    'result' => [
                        'styles' => [
                            (object) ['style_id' => 'test123'],
                        ],
                        'total_styles' => 1,
                    ],
                ]);
            }

            // Detail request — return 403
            return Http::response([], 403);
        });

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->service->fetchAllStyles('UNIQLO');

        $this->assertFalse($result, 'fetchAllStyles should return false on 403');
        // Verify page checkpoint was NOT advanced (403 stops before checkpoint update)
        $this->assertNull(Cache::get('styles:page:UNIQLO:1'), 'Page checkpoint must not be set on 403');
    }

    public function test_fetch_style_details_actually_stops_processing_on_403()
    {
        $saveCount = 0;
        $mockRepository = $this->createMock(StyleRepository::class);
        $mockRepository->method('saveStyleFromOfficialStyling')
            ->willReturnCallback(function () use (&$saveCount) {
                $saveCount++;
            });

        $service = new StyleService($mockRepository);

        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        Http::fake(function ($request) {
            $urlPath = parse_url($request->url(), PHP_URL_PATH);

            if ($urlPath === '/styles') {
                return Http::response([
                    'result' => [
                        'styles' => [
                            (object) ['style_id' => 'style1'],
                            (object) ['style_id' => 'style2'],
                            (object) ['style_id' => 'style3'],
                        ],
                        'total_styles' => 3,
                    ],
                ]);
            }

            // 只有第一筆被擋；若 403 後還繼續處理，後兩筆會成功存檔
            if ($urlPath === '/styles/style1') {
                return Http::response([], 403);
            }

            return Http::response(['result' => (object) ['some' => 'data']]);
        });

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $service->fetchAllStyles('UNIQLO');

        $this->assertFalse($result, 'fetchAllStyles should return false on 403');
        $this->assertSame(['/styles/style1'], $this->detailPaths(), '403 後不該再抓其他明細');
        $this->assertSame(0, $saveCount);
    }

    public function test_uses_configured_retry_count()
    {
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        Http::fake(['https://api.example.com/styles*' => Http::response([], 500)]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $this->service->fetchAllStyles('UNIQLO');

        // retry(3) = 3 total attempts; totalStyles stays 0 → loop exits after 1 iteration
        $this->assertSame([1, 1, 1], $this->listPagesForGender('1'), 'Should make exactly 3 HTTP attempts (1 initial + 2 retries)');
    }

    public function test_fetch_styles_by_gender_fetches_last_partial_page()
    {
        // total_styles=51, page_size=50 → page 1 and page 2 should both be fetched
        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        Http::fake(['https://api.example.com/styles*' => Http::response([
            'result' => [
                'styles' => [],
                'total_styles' => 51,
            ],
        ])]);

        $this->service->fetchAllStyles('UNIQLO');

        $this->assertSame([1, 2], $this->listPagesForGender('1'), 'Should fetch page 1 (51 >= 50) and page 2 (51 >= 50), stop at page 3 (51 < 100)');
    }

    public function test_list_api_not_re_called_when_detail_fails()
    {
        // Structural guard test: verifies that detail fetch failures do NOT trigger list API retries.
        // This test is a regression guard to prevent fetchStyleDetails from being moved back
        // inside the list retry callback. It does NOT claim a currently reproducible bug.
        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        Http::fake(function ($request) {
            if (parse_url($request->url(), PHP_URL_PATH) === '/styles') {
                return Http::response([
                    'result' => [
                        'styles' => [
                            (object) ['style_id' => 'style1'],
                        ],
                        'total_styles' => 1,
                    ],
                ]);
            }

            // Detail request (path is /styles/style1)
            return Http::response([], 500);
        });

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllStyles('UNIQLO');

        $this->assertSame([1], $this->listPagesForGender('1'), 'List API should be called exactly once; detail failures must not trigger list retries');
    }

    // ==================== Earlier Review Bug Fix Tests ====================

    public function test_non_403_failure_preserves_gender_page_checkpoint()
    {
        // Bug #58: Cache::forget(page_checkpoint) ran unconditionally after the try-catch,
        // even when fetchStylesByGenderId failed with non-403 error.
        // This cleared page progress, so next run would restart from page 1.
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        // Pre-set page checkpoint for gender 1 at page 5
        Cache::put('styles:page:UNIQLO:1', 5);

        // First gender (MEN=1): list API fails with 500
        Http::fake([
            'https://api.example.com/styles*' => Http::response([], 500),
        ]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $this->service->fetchAllStyles('UNIQLO');

        // Page checkpoint for gender 1 must be preserved for resumption
        $this->assertEquals(
            5,
            Cache::get('styles:page:UNIQLO:1'),
            'Page checkpoint must be preserved when gender fails with non-403 error'
        );
    }

    // ==================== Helper Methods ====================

    /**
     * @return int[] 依送出順序列出該性別清單請求的頁碼（含重試）
     */
    private function listPagesForGender(string $genderId): array
    {
        return Http::recorded()
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $request) => ($request->data()['gender_id'] ?? null) === $genderId)
            ->map(fn (Request $request) => $request['page'])
            ->values()
            ->all();
    }

    /**
     * @return string[]
     */
    private function detailPaths(): array
    {
        return Http::recorded()
            ->map(fn (array $pair) => parse_url($pair[0]->url(), PHP_URL_PATH))
            ->filter(fn (string $path) => str_starts_with($path, '/styles/'))
            ->values()
            ->all();
    }
}
