<?php

namespace Tests\Unit\Services;

use App\Repositories\StyleHintRepository;
use App\Services\StyleHintService;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class StyleHintServiceTest extends TestCase
{
    private const DETAIL_REST_SECONDS = 7;

    private StyleHintService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test user agents
        Config::set('app.user_agents', [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36',
        ]);
        Config::set('cache.default', 'array');
        Config::set('app.crawler.page_sizes.ugc_style_hints', 50);
        Config::set('app.crawler.delay.min', 0);
        Config::set('app.crawler.delay.max', 0);
        Config::set('app.crawler.retry.sleep_min', 0);
        Config::set('app.crawler.retry.sleep_max', 0);
        Config::set('app.crawler.batch_rest.offset.interval', 0);
        Config::set('app.crawler.batch_rest.detail.interval', 0);

        // Mock the repository
        $mockRepository = $this->createMock(StyleHintRepository::class);
        $mockRepository->method('getExistStyleHintOutfitIds')->willReturn([]);
        $mockRepository->method('saveStyleHints')->willReturn(true);
        $mockRepository->method('saveStyleHintsFromUgc')->willReturn(true);

        $this->service = new StyleHintService($mockRepository);
    }

    // ==================== Request Header Tests ====================

    public function test_list_and_detail_requests_carry_the_headers_the_api_requires()
    {
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('uniqlo.api.style_hint_detail.us', 'https://api.example.com/style-hint-detail/');

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([
                'result' => [
                    'images' => [(object) ['outfitId' => 'o1']],
                    'pagination' => ['total' => 1],
                ],
            ]),
            'https://api.example.com/style-hint-detail/*' => Http::response(['result' => (object) ['some' => 'data']]),
        ]);

        $this->service->fetchAllStyleHints('us');

        $requests = Http::recorded();
        $this->assertCount(2, $requests);

        foreach ($requests as [$request]) {
            $this->assertSame(['for-app-review'], $request->header('appCheck'), $request->url());
            $this->assertSame(['zh_TW'], $request->header('langCode'), $request->url());
            $this->assertSame(['https://m.uniqlo.com'], $request->header('Origin'), $request->url());
            $this->assertSame(['https://m.uniqlo.com/'], $request->header('Referer'), $request->url());
        }
    }

    public static function missingUserAgentConfigs(): array
    {
        return [
            'empty array' => [[]],
            'null' => [null],
        ];
    }

    #[DataProvider('missingUserAgentConfigs')]
    public function test_requests_fall_back_to_a_default_user_agent_when_none_configured(?array $userAgents)
    {
        // 曾因 array_rand([]) 讓整支爬蟲當掉
        Config::set('app.user_agents', $userAgents);
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([
                'result' => ['images' => [], 'pagination' => ['total' => 0]],
            ]),
        ]);

        $this->assertTrue($this->service->fetchAllStyleHints('us'));

        Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'Mozilla'));
    }

    public function test_requests_use_a_user_agent_from_the_configured_pool()
    {
        Config::set('app.user_agents', ['UserAgent1', 'UserAgent2', 'UserAgent3']);
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([
                'result' => ['images' => [], 'pagination' => ['total' => 0]],
            ]),
        ]);

        $this->service->fetchAllStyleHints('us');

        Http::assertSent(fn ($request) => in_array(
            $request->header('User-Agent')[0] ?? null,
            ['UserAgent1', 'UserAgent2', 'UserAgent3'],
            true
        ));
    }

    // ==================== 403 Blocking Tests ====================

    public function test_fetch_all_style_hints_stops_on_403_error()
    {
        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $result = $this->service->fetchAllStyleHints('us');

        // 403 代表被封鎖：不重試、也不往下一頁
        $this->assertFalse($result);
        Http::assertSentCount(1);
    }

    public function test_fetch_style_hints_details_stops_on_403_error()
    {
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('uniqlo.api.style_hint_detail.us', 'https://api.example.com/style-hint-detail/');

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([
                'result' => [
                    'images' => [
                        (object) ['outfitId' => 'o1'],
                        (object) ['outfitId' => 'o2'],
                    ],
                    'pagination' => ['total' => 100],
                ],
            ]),
            'https://api.example.com/style-hint-detail/*' => Http::response([], 403),
        ]);

        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $result = $this->service->fetchAllStyleHints('us');

        $this->assertFalse($result);
        $this->assertCount(1, $this->requestsTo('/style-hint-detail/'), '明細遇到 403 後不該再抓同頁其他明細');
        $this->assertCount(1, $this->requestsTo('/style-hint-list'), '明細遇到 403 後不該往下一頁');
    }

    // ==================== Checkpoint Tests ====================

    public function test_backfill_resumes_from_checkpoint()
    {
        $initialCheckpoint = 50;
        Cache::set('style_hint:offset:us', $initialCheckpoint);

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->service->fetchAllStyleHints('us', backfill: true);

        // Verify checkpoint was used (offset=50 in request)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'offset=50');
        });

        // Checkpoint cleared on success
        $this->assertNull(Cache::get('style_hint:offset:us'));
    }

    public function test_backfill_fresh_ignores_checkpoint()
    {
        Cache::set('style_hint:offset:us', 100);

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->service->fetchAllStyleHints('us', fresh: true, backfill: true);

        // Should start from offset 0 despite checkpoint being 100
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'offset=0');
        });

        // Checkpoint cleared on success
        $this->assertNull(Cache::get('style_hint:offset:us'));
    }

    // ==================== Retry Logic Tests ====================

    public function test_uses_configured_retry_count_for_list_api()
    {
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $requestCount = 0;
        // Use wildcard to match GET URLs with query parameters (e.g. ?offset=0&limit=50&...)
        Http::fake(['https://api.example.com/style-hint-list*' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response([], 500);
        }]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $this->service->fetchAllStyleHints('us');

        // retry(3) = 3 total attempts; total stays 0 → loop exits after 1 iteration
        $this->assertEquals(3, $requestCount, 'Should make exactly 3 HTTP attempts (1 initial + 2 retries)');
    }

    public function test_uses_configured_retry_count_for_ugc_api()
    {
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc-style-hints');

        Http::fake(['https://api.example.com/ugc-style-hints*' => Http::response([], 500)]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllStyleHintsFromUgc(isManual: false);

        // retry(3) = 3 total attempts; totalResultCount stays 0 → loop exits after 1 iteration
        $this->assertSame([1, 1, 1], $this->ugcPagesForGender('1'), 'Should make exactly 3 HTTP attempts (1 initial + 2 retries)');
    }

    public function test_fetch_style_hints_from_ugc_fetches_last_partial_page()
    {
        // total_result_count=51, result_limit=50 → page 1 and page 2 should both be fetched
        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc-style-hints');

        Http::fake(['https://api.example.com/ugc-style-hints*' => Http::response([
            'total_result_count' => 51,
            'content_list' => [],
        ])]);

        $this->service->fetchAllStyleHintsFromUgc(isManual: false);

        $this->assertSame([1, 2], $this->ugcPagesForGender('1'), 'Should fetch page 1 (51 >= 50) and page 2 (51 >= 50), stop at page 3 (51 < 100)');
    }

    public function test_list_api_not_re_called_when_style_hint_detail_fails()
    {
        // Structural guard test: verifies that detail fetch failures do NOT trigger list API retries.
        // This test is a regression guard to prevent fetchStyleHintsDetails from being moved back
        // inside the list retry callback. It does NOT claim a currently reproducible bug.
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('uniqlo.api.style_hint_detail.us', 'https://api.example.com/style-hint-detail/');

        $listRequestCount = 0;
        // Use closure-based fake to distinguish list (path=/style-hint-list) from detail (path=/style-hint-detail/...)
        Http::fake(function ($request) use (&$listRequestCount) {
            $urlPath = parse_url($request->url(), PHP_URL_PATH);

            if ($urlPath === '/style-hint-list') {
                // List request (path is exactly /style-hint-list, query params follow)
                $listRequestCount++;

                return Http::response([
                    'result' => [
                        'images' => [
                            (object) ['outfitId' => 'outfit1'],
                        ],
                        'pagination' => ['total' => 1],
                    ],
                ]);
            }

            // Detail request (path is /style-hint-detail/outfit1/details)
            return Http::response([], 500);
        });

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        try {
            $this->service->fetchAllStyleHints('us');
        } catch (\Throwable $e) {
            // detail failures may propagate (403) or be swallowed (500)
        }

        $this->assertEquals(1, $listRequestCount, 'List API should be called exactly once; detail failures must not trigger list retries');
    }

    // ==================== Bug Fix Regression Tests ====================

    public function test_detail_counter_accumulates_across_pages()
    {
        // Bug #1: resetDetailCounter() was called per-page in fetchStyleHintsDetails(),
        // causing detailCounter to never reach the batch rest interval (200).
        // Fix: resetDetailCounter() is now called once in fetchAllStyleHints() before the do-while.
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('uniqlo.api.style_hint_detail.us', 'https://api.example.com/style-hint-detail/');
        // 兩頁各 3 筆：跨頁累積才會在第 4 筆休息，每頁歸零就永遠到不了 4
        Config::set('app.crawler.batch_rest.detail.interval', 4);
        Config::set('app.crawler.batch_rest.detail.sleep_min', self::DETAIL_REST_SECONDS);
        Config::set('app.crawler.batch_rest.detail.sleep_max', self::DETAIL_REST_SECONDS);
        Sleep::fake();

        $page = 0;
        Http::fake(function ($request) use (&$page) {
            $urlPath = parse_url($request->url(), PHP_URL_PATH);

            if ($urlPath === '/style-hint-list') {
                $page++;

                if ($page === 1) {
                    return Http::response([
                        'result' => [
                            'images' => [
                                (object) ['outfitId' => 'o1'],
                                (object) ['outfitId' => 'o2'],
                                (object) ['outfitId' => 'o3'],
                            ],
                            'pagination' => ['total' => 100],
                        ],
                    ]);
                }

                // Page 2: stop loop
                return Http::response([
                    'result' => [
                        'images' => [
                            (object) ['outfitId' => 'o4'],
                            (object) ['outfitId' => 'o5'],
                            (object) ['outfitId' => 'o6'],
                        ],
                        'pagination' => ['total' => 51],
                    ],
                ]);
            }

            // Detail requests succeed
            return Http::response([
                'result' => (object) ['some' => 'data'],
            ]);
        });

        $this->service->fetchAllStyleHints('us');

        $this->assertDetailRestedTimes(1);
    }

    /**
     * 明細休息的 interval 設 0 代表關閉：明細照樣全部抓完，不休息。
     */
    public function test_a_detail_rest_interval_of_zero_turns_the_rest_off()
    {
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('uniqlo.api.style_hint_detail.us', 'https://api.example.com/style-hint-detail/');
        Config::set('app.crawler.batch_rest.detail.interval', 0);
        Config::set('app.crawler.batch_rest.detail.sleep_min', self::DETAIL_REST_SECONDS);
        Config::set('app.crawler.batch_rest.detail.sleep_max', self::DETAIL_REST_SECONDS);
        Sleep::fake();

        Http::fake(function ($request) {
            if (parse_url($request->url(), PHP_URL_PATH) === '/style-hint-list') {
                return Http::response([
                    'result' => [
                        'images' => [
                            (object) ['outfitId' => 'o1'],
                            (object) ['outfitId' => 'o2'],
                            (object) ['outfitId' => 'o3'],
                        ],
                        'pagination' => ['total' => 3],
                    ],
                ]);
            }

            return Http::response(['result' => (object) ['some' => 'data']]);
        });

        $this->service->fetchAllStyleHints('us');

        $this->assertCount(3, Http::recorded(fn ($request) => str_contains($request->url(), '/style-hint-detail/')));
        $this->assertDetailRestedTimes(0);
    }

    public function test_page_missing_images_field_is_skipped_and_the_next_page_is_still_fetched()
    {
        // Bug #2: list API response missing result.images was not validated,
        // causing null property access errors.
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('app.crawler.retry.times', 1);

        Http::fake([
            'https://api.example.com/style-hint-list*' => function ($request) {
                if ($request['offset'] === 50) {
                    return Http::response([
                        'result' => [
                            // 'images' is missing
                            'pagination' => ['total' => 100],
                        ],
                    ]);
                }

                return Http::response([
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 100],
                    ],
                ]);
            },
        ]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $result = $this->service->fetchAllStyleHints('us');

        $this->assertTrue($result);
        $this->assertSame(
            [0, 50, 100],
            $this->requestsTo('/style-hint-list')->map(fn ($request) => $request['offset'])->all(),
            '缺欄位的那頁跳過後要繼續抓下一頁'
        );
    }

    public function test_list_api_throws_on_missing_pagination_field()
    {
        // Bug #2: list API response missing result.pagination.total was not validated.
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('app.crawler.retry.times', 1);

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([
                'result' => [
                    'images' => [],
                    // 'pagination' is missing
                ],
            ]),
        ]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $this->service->fetchAllStyleHints('us');

        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_fresh_clears_checkpoint_cache()
    {
        // Bug #3: $fresh only set offset=0 but didn't Cache::forget the old checkpoint.
        // If the fresh run is interrupted, the old stale checkpoint would persist.
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        // Pre-set a stale checkpoint
        Cache::put('style_hint:offset:us', 500);

        // Return 403 immediately to simulate an interrupted fresh run
        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([], 403),
        ]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllStyleHints('us', fresh: true, backfill: true);

        // With the fix, Cache::forget is called before the loop,
        // so the stale checkpoint (500) should be gone even though the run was interrupted
        $this->assertNull(
            Cache::get('style_hint:offset:us'),
            'Fresh run must clear old checkpoint even when interrupted by 403'
        );
    }

    public function test_ugc_detail_counter_not_incremented_on_retry()
    {
        // Fix #4: detailCounter was incremented inside retry callback,
        // causing over-counting when retries occur.
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc-style-hints');
        // 第一頁 3 筆（儲存失敗一次後重試成功）、第二頁 1 筆：正確計數在第二頁剛好到 4；
        // 若重試也計數，第一頁就變 6、第二頁 7，永遠不會休息
        Config::set('app.crawler.batch_rest.detail.interval', 4);
        Config::set('app.crawler.batch_rest.detail.sleep_min', self::DETAIL_REST_SECONDS);
        Config::set('app.crawler.batch_rest.detail.sleep_max', self::DETAIL_REST_SECONDS);
        Sleep::fake();

        $saveCalls = 0;
        $mockRepository = $this->createMock(StyleHintRepository::class);
        $mockRepository->method('saveStyleHintsFromUgc')
            ->willReturnCallback(function () use (&$saveCalls) {
                $saveCalls++;

                if ($saveCalls === 1) {
                    throw new RuntimeException('Temporary database failure');
                }

                return true;
            });
        $service = new StyleHintService($mockRepository);

        Http::fake(['https://api.example.com/ugc-style-hints*' => function ($request) {
            if ($request['style_gender'] !== ['1']) {
                return Http::response(['total_result_count' => 0, 'content_list' => []]);
            }

            $contentList = $request['page'] === 1
                ? [['id' => 1], ['id' => 2], ['id' => 3]]
                : [['id' => 4]];

            return Http::response(['total_result_count' => 51, 'content_list' => $contentList]);
        }]);

        $service->fetchAllStyleHintsFromUgc(isManual: false);

        $this->assertSame([1, 1, 2], $this->ugcPagesForGender('1'), '第一頁應因儲存失敗重試一次');
        $this->assertDetailRestedTimes(1);
    }

    // ==================== Earlier Review Bug Fix Tests ====================

    public function test_manual_mode_advances_past_failed_page()
    {
        // Bug #56: In manual mode, forgetLastManualFetchPage was only called on success.
        // On non-403 failure, the cached page persisted, causing getLastManualFetchPage
        // to restore the same failed page → infinite loop.
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc-style-hints');

        $menRequests = 0;
        Http::fake(['https://api.example.com/ugc-style-hints*' => function ($request) use (&$menRequests) {
            if ($request['style_gender'] !== ['1']) {
                return Http::response(['total_result_count' => 0, 'content_list' => []]);
            }

            // 程式退回卡在同一頁時用 403 中止，讓測試失敗而不是跑不完
            if (++$menRequests > 10) {
                return Http::response([], 403);
            }

            // Page 2 fails (500) — this is the page that must be advanced past
            if ($request['page'] === 2) {
                return Http::response([], 500);
            }

            return Http::response(['total_result_count' => 100, 'content_list' => []]);
        }]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllStyleHintsFromUgc(isManual: true);

        $this->assertSame([1, 2, 3], $this->ugcPagesForGender('1'), 'Manual mode must advance past failed page, not retry infinitely');
    }

    public function test_backfill_all_pages_fail_preserves_checkpoint()
    {
        // Bug #57: When all pages fail with non-403 errors, the loop exits with
        // total/productSum=0 and incorrectly runs Cache::forget + completion logic,
        // treating a complete failure as successful completion.
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('app.crawler.retry.times', 1);

        // Pre-set checkpoint
        Cache::put('style_hint:offset:us', 100);

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([], 500),
        ]);

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllStyleHints('us', backfill: true);

        // Checkpoint must be preserved — not cleared as if completed successfully
        $this->assertEquals(
            100,
            Cache::get('style_hint:offset:us'),
            'Checkpoint must not be cleared when no pages succeeded'
        );
    }

    // ==================== Daily Mode Tests ====================

    public function test_daily_mode_starts_from_offset_zero_ignoring_checkpoint()
    {
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        Cache::put('style_hint:offset:us', 500);

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([
                'result' => [
                    'images' => [],
                    'pagination' => ['total' => 0],
                ],
            ]),
        ]);

        $this->service->fetchAllStyleHints('us');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'offset=0');
        });
    }

    public function test_daily_mode_does_not_save_checkpoint()
    {
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        Cache::flush();

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([
                'result' => [
                    'images' => [],
                    'pagination' => ['total' => 0],
                ],
            ]),
        ]);

        $this->service->fetchAllStyleHints('us');

        $this->assertNull(Cache::get('style_hint:offset:us'), 'Daily mode should not write checkpoint');
    }

    public function test_daily_mode_terminates_after_consecutive_empty_pages()
    {
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        // All items already exist in DB
        $mockRepository = $this->createMock(StyleHintRepository::class);
        $mockRepository->method('getExistStyleHintOutfitIds')
            ->willReturnCallback(fn ($country, $outfitIds) => $outfitIds->toArray());
        $service = new StyleHintService($mockRepository);

        $listRequestCount = 0;
        Http::fake(['https://api.example.com/style-hint-list*' => function () use (&$listRequestCount) {
            $listRequestCount++;

            return Http::response([
                'result' => [
                    'images' => [(object) ['outfitId' => "o{$listRequestCount}"]],
                    'pagination' => ['total' => 10000],
                ],
            ]);
        }]);

        Log::shouldReceive('info')->andReturnNull();

        $service->fetchAllStyleHints('us');

        // Should stop after 3 consecutive empty pages, not all 200 pages
        $this->assertEquals(3, $listRequestCount, 'Should stop after 3 consecutive empty pages');
    }

    public function test_daily_mode_resets_counter_on_page_with_new_items()
    {
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('uniqlo.api.style_hint_detail.us', 'https://api.example.com/style-hint-detail/');

        // Pages 1-2: all exist → counter 2
        // Page 3: new item → counter reset to 0
        // Pages 4-6: all exist → counter reaches 3 → stop
        $pageNum = 0;
        $mockRepository = $this->createMock(StyleHintRepository::class);
        $mockRepository->method('getExistStyleHintOutfitIds')
            ->willReturnCallback(function ($country, $outfitIds) use (&$pageNum) {
                if ($pageNum === 3) {
                    return []; // Page 3: nothing exists → item is new
                }

                return $outfitIds->toArray(); // All exist
            });
        $mockRepository->method('saveStyleHints')->willReturn(true);
        $service = new StyleHintService($mockRepository);

        $listRequestCount = 0;
        Http::fake(function ($request) use (&$pageNum, &$listRequestCount) {
            $urlPath = parse_url($request->url(), PHP_URL_PATH);

            if ($urlPath === '/style-hint-list') {
                $listRequestCount++;
                $pageNum = $listRequestCount;

                return Http::response([
                    'result' => [
                        'images' => [(object) ['outfitId' => "outfit{$listRequestCount}"]],
                        'pagination' => ['total' => 10000],
                    ],
                ]);
            }

            // Detail request
            return Http::response(['result' => (object) ['some' => 'data']]);
        });

        Log::shouldReceive('info')->andReturnNull();

        $service->fetchAllStyleHints('us');

        // Pages 1,2 (empty) + Page 3 (new, resets) + Pages 4,5,6 (empty, reaches 3) = 6
        $this->assertEquals(6, $listRequestCount, 'Counter should reset when page has new items');
    }

    public function test_details_are_only_fetched_for_outfits_not_already_stored()
    {
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('uniqlo.api.style_hint_detail.us', 'https://api.example.com/style-hint-detail/');

        // 2 of 4 items already exist
        $mockRepository = $this->createMock(StyleHintRepository::class);
        $mockRepository->method('getExistStyleHintOutfitIds')->willReturn(['o1', 'o3']);
        $mockRepository->method('saveStyleHints')->willReturn(true);
        $service = new StyleHintService($mockRepository);

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([
                'result' => [
                    'images' => [
                        (object) ['outfitId' => 'o1'],
                        (object) ['outfitId' => 'o2'],
                        (object) ['outfitId' => 'o3'],
                        (object) ['outfitId' => 'o4'],
                    ],
                    'pagination' => ['total' => 4],
                ],
            ]),
            'https://api.example.com/style-hint-detail/*' => Http::response(['result' => (object) ['some' => 'data']]),
        ]);

        $service->fetchAllStyleHints('us');

        $this->assertSame(
            ['o2', 'o4'],
            $this->requestsTo('/style-hint-detail/')
                ->map(fn ($request) => basename(dirname(parse_url($request->url(), PHP_URL_PATH))))
                ->all()
        );
    }

    // ==================== Backfill Mode Tests ====================

    public function test_backfill_mode_saves_checkpoint()
    {
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('uniqlo.api.style_hint_detail.us', 'https://api.example.com/style-hint-detail/');

        $page = 0;
        Http::fake(function ($request) use (&$page) {
            $urlPath = parse_url($request->url(), PHP_URL_PATH);

            if ($urlPath === '/style-hint-list') {
                $page++;

                if ($page === 1) {
                    return Http::response([
                        'result' => [
                            'images' => [(object) ['outfitId' => 'o1']],
                            'pagination' => ['total' => 100],
                        ],
                    ]);
                }

                // Page 2: 403 to stop the loop
                return Http::response([], 403);
            }

            return Http::response(['result' => (object) ['some' => 'data']]);
        });

        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $this->service->fetchAllStyleHints('us', backfill: true);

        // Checkpoint should be saved at offset 50 from the successful first page
        $this->assertEquals(50, Cache::get('style_hint:offset:us'));
    }

    public function test_backfill_mode_no_early_termination()
    {
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        // All items already exist — daily mode would stop after 3 pages
        $mockRepository = $this->createMock(StyleHintRepository::class);
        $mockRepository->method('getExistStyleHintOutfitIds')
            ->willReturnCallback(fn ($country, $outfitIds) => $outfitIds->toArray());
        $service = new StyleHintService($mockRepository);

        $listRequestCount = 0;
        Http::fake(['https://api.example.com/style-hint-list*' => function () use (&$listRequestCount) {
            $listRequestCount++;

            return Http::response([
                'result' => [
                    'images' => [(object) ['outfitId' => "o{$listRequestCount}"]],
                    'pagination' => ['total' => 200],
                ],
            ]);
        }]);

        Log::shouldReceive('info')->andReturnNull();

        $service->fetchAllStyleHints('us', backfill: true);

        // total=200, limit=50 → 5 pages (offsets 0,50,100,150,200)
        // Backfill should NOT early-terminate even though all pages have zero new items
        $this->assertEquals(5, $listRequestCount, 'Backfill should fetch all pages without early termination');
    }

    // ==================== Helper Methods ====================

    /**
     * @return Collection<int, Request>
     */
    private function requestsTo(string $pathPrefix): Collection
    {
        return Http::recorded()
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $request) => str_starts_with((string) parse_url($request->url(), PHP_URL_PATH), $pathPrefix))
            ->values();
    }

    /**
     * @return int[] 依送出順序列出該性別 UGC 清單請求的頁碼（含重試）
     */
    private function ugcPagesForGender(string $gender): array
    {
        return Http::recorded()
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $request) => ($request->data()['style_gender'] ?? null) === [$gender])
            ->map(fn (Request $request) => $request['page'])
            ->values()
            ->all();
    }

    /**
     * 明細休息設成一個獨特的秒數（見 DETAIL_REST_SECONDS），跟重試與隨機延遲的
     * 睡眠分得開，只數這種長度的睡眠就是休息次數。
     */
    private function assertDetailRestedTimes(int $times): void
    {
        Sleep::assertSlept(
            fn (CarbonInterval $duration) => (int) $duration->totalSeconds === self::DETAIL_REST_SECONDS,
            $times
        );
    }
}
