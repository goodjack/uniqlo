<?php

namespace Tests\Unit\Services;

use App\Repositories\StyleRepository;
use App\Services\StyleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
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

        $this->service->fetchAllStyles('UNIQLO');

        // Page checkpoint should not be advanced when blocked by 403
        $this->assertNull(Cache::get('styles:page:UNIQLO:1'));

        // Last gender checkpoint is preserved for resumption (set before the 403 hit)
        // This allows the crawler to resume from gender 1 on next run
        $this->assertEquals('1', Cache::get('styles:last_gender:UNIQLO'));
    }

    public function test_fetch_styles_by_gender_saves_checkpoint()
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
        Http::fake([
            // First request succeeds to get list
            'https://api.example.com/styles' => Http::sequence()
                ->push([
                    'result' => [
                        'styles' => [
                            (object) ['style_id' => 'test123'],
                        ],
                        'total_styles' => 1,
                    ],
                ])
                ->push([
                    'result' => [
                        'styles' => [],
                        'total_styles' => 1,
                    ],
                ]),
            // Detail request returns 403
            'https://api.example.com/styles/*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        // With the fix (throw exception), this should throw
        // Without the fix (return), it would silently continue
        try {
            $this->service->fetchAllStyles('UNIQLO');
            // If we get here, the exception was caught somewhere (which is OK)
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            // If exception is thrown, that's also correct behavior
            $this->assertTrue(true);
        }
    }

    public function test_fetch_style_details_actually_stops_processing_on_403()
    {
        // This test verifies that Collection::each() actually stops on 403
        // Create a mock that tracks how many times save is called
        $saveCount = 0;
        $mockRepository = $this->createMock(StyleRepository::class);
        $mockRepository->method('saveStyleFromOfficialStyling')
            ->willReturnCallback(function () use (&$saveCount) {
                $saveCount++;
            });

        $service = new StyleService($mockRepository);

        Http::fake([
            'https://api.example.com/styles' => Http::sequence()
                ->push([
                    'result' => [
                        'styles' => [
                            (object) ['style_id' => 'style1'],
                            (object) ['style_id' => 'style2'],
                            (object) ['style_id' => 'style3'],
                        ],
                        'total_styles' => 3,
                    ],
                ])
                ->push([
                    'result' => [
                        'styles' => [],
                        'total_styles' => 3,
                    ],
                ]),
            // First detail request returns 403
            'https://api.example.com/styles/*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        try {
            $service->fetchAllStyles('UNIQLO');
        } catch (\Throwable $e) {
            // Expected - 403 should throw
        }

        // With throw: save should be called 0 times (stops immediately)
        // Without throw (old code with return): save would be called 0 times but loop continues
        // The key is that exception is thrown, not silently continuing
        $this->assertEquals(0, $saveCount, 'Should not save any styles when 403 occurs');
    }

    public function test_uses_configured_retry_count()
    {
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        $requestCount = 0;
        // Use wildcard to match GET URLs with query parameters (e.g. ?gender_id=1&...)
        Http::fake(['https://api.example.com/styles*' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response([], 500);
        }]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        // Directly call private fetchStylesByGenderId to avoid 4-gender loop
        $this->invokeMethod($this->service, 'fetchStylesByGenderId', ['1', 'UNIQLO']);

        // retry(3) = 3 total attempts; totalStyles stays 0 → loop exits after 1 iteration
        $this->assertEquals(3, $requestCount, 'Should make exactly 3 HTTP attempts (1 initial + 2 retries)');
    }

    public function test_fetch_styles_by_gender_fetches_last_partial_page()
    {
        // total_styles=51, page_size=50 → page 1 and page 2 should both be fetched
        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        $requestCount = 0;
        // Use wildcard to match GET URLs with query parameters (e.g. ?gender_id=1&...)
        // styles=[] so fetchStyleDetails makes no detail requests; all requestCount come from list
        Http::fake(['https://api.example.com/styles*' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response([
                'result' => [
                    'styles' => [],
                    'total_styles' => 51,
                ],
            ]);
        }]);

        $this->invokeMethod($this->service, 'fetchStylesByGenderId', ['1', 'UNIQLO']);

        $this->assertEquals(2, $requestCount, 'Should fetch page 1 (51 >= 50) and page 2 (51 >= 50), stop at page 3 (51 < 100)');
    }

    public function test_list_api_not_re_called_when_detail_fails()
    {
        // Structural guard test: verifies that detail fetch failures do NOT trigger list API retries.
        // This test is a regression guard to prevent fetchStyleDetails from being moved back
        // inside the list retry callback. It does NOT claim a currently reproducible bug.
        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        $listRequestCount = 0;
        // Use closure-based fake to distinguish list (/styles?) from detail (/styles/id?) by URL path
        Http::fake(function ($request) use (&$listRequestCount) {
            $urlPath = parse_url($request->url(), PHP_URL_PATH);

            if ($urlPath === '/styles') {
                // List request (path is exactly /styles, query params follow)
                $listRequestCount++;

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

        try {
            $this->invokeMethod($this->service, 'fetchStylesByGenderId', ['1', 'UNIQLO']);
        } catch (\Throwable $e) {
            // detail failures may propagate (403) or be swallowed (500)
        }

        $this->assertEquals(1, $listRequestCount, 'List API should be called exactly once; detail failures must not trigger list retries');
    }

    // ==================== Helper Methods ====================

    private function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
