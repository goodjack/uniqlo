<?php

namespace Tests\Unit\Services;

use App\Repositories\StyleHintRepository;
use App\Services\StyleHintService;
use Exception;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Tests\TestCase;

class StyleHintServiceTest extends TestCase
{
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

    // ==================== Helper Methods Tests ====================

    public function test_get_random_user_agent_returns_valid_ua()
    {
        $userAgents = Config::get('app.user_agents');

        for ($i = 0; $i < 10; $i++) {
            $ua = $this->invokeMethod($this->service, 'getRandomUserAgent');
            $this->assertTrue(in_array($ua, $userAgents), 'UA should be from the configured pool');
        }
    }

    public function test_build_headers_contains_required_fields()
    {
        $headers = $this->invokeMethod($this->service, 'buildHeaders');

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertArrayHasKey('Accept-Language', $headers);
        $this->assertArrayHasKey('Accept-Encoding', $headers);
        $this->assertArrayHasKey('Referer', $headers);
        $this->assertArrayHasKey('Origin', $headers);

        // Verify values are not empty
        $this->assertNotEmpty($headers['User-Agent']);
        $this->assertNotEmpty($headers['Accept']);

        // Verify StyleHintService-specific headers (override from trait)
        $this->assertEquals('for-app-review', $headers['appCheck']);
        $this->assertEquals('zh_TW', $headers['langCode']);
        $this->assertEquals('https://m.uniqlo.com', $headers['Origin']);
        $this->assertEquals('https://m.uniqlo.com/', $headers['Referer']);
    }

    public function test_is_403_error_detects_403_correctly()
    {
        // Create a response with status 403
        $mockResponse = new Response(
            new Psr7Response(403)
        );

        // Create RequestException properly
        $requestException = new RequestException($mockResponse);

        $result = $this->invokeMethod($this->service, 'is403Error', [$requestException]);

        $this->assertTrue($result);
    }

    public function test_is_403_error_returns_false_for_other_status_codes()
    {
        $mockResponse = new Response(
            new Psr7Response(500)
        );

        $requestException = new RequestException($mockResponse);

        $result = $this->invokeMethod($this->service, 'is403Error', [$requestException]);

        $this->assertFalse($result);
    }

    public function test_is_403_error_returns_false_for_non_request_exception()
    {
        $exception = new Exception('Some error');

        $result = $this->invokeMethod($this->service, 'is403Error', [$exception]);

        $this->assertFalse($result);
    }

    public function test_should_offset_batch_rest_triggers_at_correct_interval()
    {
        Config::set('app.crawler.batch_rest.offset.interval', 75);

        // offset: 0, limit: 50, result: 0 / 50 = 0 (should not trigger when offset is 0)
        $result = $this->invokeMethod($this->service, 'shouldOffsetBatchRest', [0, 50]);
        $this->assertFalse($result);

        // offset: 3750 (75 * 50), limit: 50, result: 3750 / 50 = 75 % 75 = 0 (should trigger)
        $result = $this->invokeMethod($this->service, 'shouldOffsetBatchRest', [3750, 50]);
        $this->assertTrue($result);

        // offset: 3700, limit: 50, result: 3700 / 50 = 74 % 75 != 0 (should not trigger)
        $result = $this->invokeMethod($this->service, 'shouldOffsetBatchRest', [3700, 50]);
        $this->assertFalse($result);
    }

    public function test_should_offset_batch_rest_handles_zero_limit()
    {
        // This tests the division-by-zero bug fix
        Config::set('app.crawler.batch_rest.offset.interval', 75);

        // Should not trigger (and not crash) when limit is 0
        $result = $this->invokeMethod($this->service, 'shouldOffsetBatchRest', [100, 0]);
        $this->assertFalse($result, 'Should handle zero limit without crashing');
    }

    public function test_should_offset_batch_rest_handles_zero_interval()
    {
        // This tests the division-by-zero bug fix
        Config::set('app.crawler.batch_rest.offset.interval', 0);

        // Should not trigger (and not crash) when interval is 0
        $result = $this->invokeMethod($this->service, 'shouldOffsetBatchRest', [100, 50]);
        $this->assertFalse($result, 'Should handle zero interval without crashing');
    }

    public function test_should_detail_batch_rest_triggers_at_interval()
    {
        Config::set('app.crawler.batch_rest.detail.interval', 200);

        // detailCounter: 0 (should not trigger when 0)
        $this->setPrivateProperty($this->service, 'detailCounter', 0);
        $result = $this->invokeMethod($this->service, 'shouldDetailBatchRest');
        $this->assertFalse($result);

        // detailCounter: 200 (200 % 200 = 0, should trigger)
        $this->setPrivateProperty($this->service, 'detailCounter', 200);
        $result = $this->invokeMethod($this->service, 'shouldDetailBatchRest');
        $this->assertTrue($result);

        // detailCounter: 150 (150 % 200 != 0, should not trigger)
        $this->setPrivateProperty($this->service, 'detailCounter', 150);
        $result = $this->invokeMethod($this->service, 'shouldDetailBatchRest');
        $this->assertFalse($result);
    }

    public function test_should_detail_batch_rest_handles_zero_interval()
    {
        // This tests the division-by-zero bug fix
        Config::set('app.crawler.batch_rest.detail.interval', 0);

        // Should not trigger (and not crash) when interval is 0
        $this->setPrivateProperty($this->service, 'detailCounter', 200);
        $result = $this->invokeMethod($this->service, 'shouldDetailBatchRest');
        $this->assertFalse($result, 'Should handle zero interval without crashing');
    }

    public function test_get_random_user_agent_handles_empty_config()
    {
        // This tests the empty array bug fix
        Config::set('app.user_agents', []);

        Log::shouldReceive('warning')->once()->andReturnNull();

        // Should not crash with Fatal Error from array_rand([])
        $result = $this->invokeMethod($this->service, 'getRandomUserAgent');

        $this->assertNotEmpty($result, 'Should return fallback User-Agent');
        $this->assertStringContainsString('Mozilla', $result);
    }

    public function test_get_random_user_agent_handles_null_config()
    {
        // This tests the null config bug fix
        Config::set('app.user_agents', null);

        Log::shouldReceive('warning')->once()->andReturnNull();

        // Should not crash
        $result = $this->invokeMethod($this->service, 'getRandomUserAgent');

        $this->assertNotEmpty($result, 'Should return fallback User-Agent when config is null');
    }

    public function test_get_random_user_agent_returns_from_pool()
    {
        Config::set('app.user_agents', [
            'UserAgent1',
            'UserAgent2',
            'UserAgent3',
        ]);

        $result = $this->invokeMethod($this->service, 'getRandomUserAgent');

        $this->assertContains($result, ['UserAgent1', 'UserAgent2', 'UserAgent3']);
    }

    // ==================== 403 Blocking Tests ====================

    public function test_fetch_all_style_hints_stops_on_403_error()
    {
        // Pre-set checkpoint (simulating partial completion)
        Cache::set('style_hint:offset:us', 50);
        $checkpointBefore = Cache::get('style_hint:offset:us');

        // Use wildcard to match GET URLs with query parameters (e.g. ?offset=50&limit=50&...)
        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $this->service->fetchAllStyleHints('us');

        // Checkpoint must not be overwritten on 403
        $checkpointAfter = Cache::get('style_hint:offset:us');
        $this->assertEquals(50, $checkpointBefore);
        $this->assertEquals(50, $checkpointAfter, 'Checkpoint must not be overwritten on 403');
    }

    public function test_fetch_style_hints_details_stops_on_403_error()
    {
        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.style_hint_detail.us', 'https://api.example.com/style-hint-detail/');

        Log::spy();

        $mockSummaries = [
            (object) ['outfitId' => '123'],
        ];

        // With the fix (throw exception), this should throw
        try {
            $this->invokeMethod($this->service, 'fetchStyleHintsDetails', ['us', $mockSummaries]);
        } catch (\Throwable $e) {
            // Expected - 403 should throw exception
        }

        // Verify error was logged
        \Log::shouldHaveReceived('error')->atLeast()->once();
    }

    // ==================== Checkpoint Tests ====================

    public function test_backfill_saves_and_clears_checkpoint_on_completion()
    {
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

        Cache::flush();

        $this->service->fetchAllStyleHints('us', backfill: true);

        // Checkpoint should be cleared after successful backfill completion
        $this->assertNull(Cache::get('style_hint:offset:us'));
    }

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

        $this->service->fetchAllStyleHints('us');

        // retry(3) = 3 total attempts; total stays 0 → loop exits after 1 iteration
        $this->assertEquals(3, $requestCount, 'Should make exactly 3 HTTP attempts (1 initial + 2 retries)');
    }

    public function test_uses_configured_retry_count_for_ugc_api()
    {
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc-style-hints');

        $requestCount = 0;
        // Use wildcard to match GET URLs with query parameters (e.g. ?style_gender[]=1&...)
        Http::fake(['https://api.example.com/ugc-style-hints*' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response([], 500);
        }]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        // Directly call private method to avoid 5-gender loop
        $this->invokeMethod($this->service, 'fetchStyleHintsFromUgcByGender', ['1', 'UNIQLO', false, false]);

        // retry(3) = 3 total attempts; totalResultCount stays 0 → loop exits after 1 iteration
        $this->assertEquals(3, $requestCount, 'Should make exactly 3 HTTP attempts (1 initial + 2 retries)');
    }

    public function test_fetch_style_hints_from_ugc_fetches_last_partial_page()
    {
        // total_result_count=51, result_limit=50 → page 1 and page 2 should both be fetched
        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc-style-hints');

        $requestCount = 0;
        // Use wildcard to match GET URLs with query parameters (e.g. ?style_gender[]=1&...)
        Http::fake(['https://api.example.com/ugc-style-hints*' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response(json_encode([
                'total_result_count' => 51,
                'content_list' => [],
            ]), 200, ['Content-Type' => 'application/json']);
        }]);

        $this->invokeMethod($this->service, 'fetchStyleHintsFromUgcByGender', ['1', 'UNIQLO', false, false]);

        $this->assertEquals(2, $requestCount, 'Should fetch page 1 (51 >= 50) and page 2 (51 >= 50), stop at page 3 (51 < 100)');
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

        // Simulate 2 pages with 3 items each → detailCounter should reach 6 total
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

        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllStyleHints('us');

        // detailCounter should be 6 (3 items × 2 pages), NOT reset to 3 after each page
        $detailCounter = $this->getPrivateProperty($this->service, 'detailCounter');
        $this->assertEquals(6, $detailCounter, 'detailCounter should accumulate across pages, not reset per page');
    }

    public function test_list_api_throws_on_missing_images_field()
    {
        // Bug #2: list API response missing result.images was not validated,
        // causing null property access errors.
        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');
        Config::set('app.crawler.retry.times', 1);

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([
                'result' => [
                    // 'images' is missing
                    'pagination' => ['total' => 100],
                ],
            ]),
        ]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        // Should not crash — the exception is caught by the do-while's catch block
        $this->service->fetchAllStyleHints('us');

        // Verify it logged an error (retry exhausted)
        Log::shouldHaveReceived('error')->atLeast()->once();
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

        $requestCount = 0;
        Http::fake(['https://api.example.com/ugc-style-hints*' => function ($request) use (&$requestCount) {
            $requestCount++;

            // First attempt fails, second succeeds
            if ($requestCount === 1) {
                return Http::response([], 500);
            }

            return Http::response(json_encode([
                'total_result_count' => 10,
                'content_list' => [
                    (object) ['id' => 1],
                    (object) ['id' => 2],
                    (object) ['id' => 3],
                ],
            ]), 200, ['Content-Type' => 'application/json']);
        }]);

        $this->setPrivateProperty($this->service, 'detailCounter', 0);

        $this->invokeMethod($this->service, 'fetchStyleHintsFromUgcByGender', ['1', 'UNIQLO', false, false]);

        // detailCounter should be 3 (count of content_list), NOT 6 (from being inside retry)
        $detailCounter = $this->getPrivateProperty($this->service, 'detailCounter');
        $this->assertEquals(3, $detailCounter, 'detailCounter should only increment once per successful request, not per retry attempt');
    }

    // ==================== Earlier Review Bug Fix Tests ====================

    public function test_manual_mode_advances_past_failed_page()
    {
        // Bug #56: In manual mode, forgetLastManualFetchPage was only called on success.
        // On non-403 failure, the cached page persisted, causing getLastManualFetchPage
        // to restore the same failed page → infinite loop.
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc-style-hints');

        $requestCount = 0;
        Http::fake(['https://api.example.com/ugc-style-hints*' => function ($request) use (&$requestCount) {
            $requestCount++;

            // Page 1 succeeds with totalResultCount=100 (needs 2 pages of 50)
            if ($requestCount === 1) {
                return Http::response(json_encode([
                    'total_result_count' => 100,
                    'content_list' => [],
                ]), 200, ['Content-Type' => 'application/json']);
            }

            // Page 2 fails (500) — this is the page that must be advanced past
            if ($requestCount === 2) {
                return Http::response([], 500);
            }

            // Page 3 succeeds — proves we advanced past failed page 2
            return Http::response(json_encode([
                'total_result_count' => 100,
                'content_list' => [],
            ]), 200, ['Content-Type' => 'application/json']);
        }]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        // Run in manual mode — page 2 fails, should advance to page 3 (not loop on page 2)
        $this->invokeMethod($this->service, 'fetchStyleHintsFromUgcByGender', ['1', 'UNIQLO', false, true]);

        // Should make exactly 3 requests: page 1 (success) + page 2 (fail) + page 3 (success)
        // Without the fix, it would loop on page 2 indefinitely
        $this->assertEquals(3, $requestCount, 'Manual mode must advance past failed page, not retry infinitely');
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

    public function test_fetch_style_hints_details_returns_new_item_count()
    {
        Config::set('uniqlo.api.style_hint_detail.us', 'https://api.example.com/style-hint-detail/');

        // 2 of 4 items already exist
        $mockRepository = $this->createMock(StyleHintRepository::class);
        $mockRepository->method('getExistStyleHintOutfitIds')->willReturn(['o1', 'o3']);
        $mockRepository->method('saveStyleHints')->willReturn(true);
        $service = new StyleHintService($mockRepository);

        Http::fake(['*' => Http::response(['result' => (object) ['some' => 'data']])]);

        $summaries = [
            (object) ['outfitId' => 'o1'],
            (object) ['outfitId' => 'o2'],
            (object) ['outfitId' => 'o3'],
            (object) ['outfitId' => 'o4'],
        ];

        $result = $this->invokeMethod($service, 'fetchStyleHintsDetails', ['us', $summaries]);

        $this->assertEquals(2, $result, 'Should return count of new items (not existing in DB)');
    }

    public function test_fetch_style_hints_details_returns_zero_when_all_exist()
    {
        $mockRepository = $this->createMock(StyleHintRepository::class);
        $mockRepository->method('getExistStyleHintOutfitIds')->willReturn(['o1', 'o2']);
        $service = new StyleHintService($mockRepository);

        $summaries = [
            (object) ['outfitId' => 'o1'],
            (object) ['outfitId' => 'o2'],
        ];

        $result = $this->invokeMethod($service, 'fetchStyleHintsDetails', ['us', $summaries]);

        $this->assertEquals(0, $result, 'Should return 0 when all items already exist');
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

    public function test_backfill_fresh_clears_and_starts_from_zero()
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

        $this->service->fetchAllStyleHints('us', fresh: true, backfill: true);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'offset=0');
        });

        // Checkpoint should be cleared (fresh cleared it, then completion cleared it)
        $this->assertNull(Cache::get('style_hint:offset:us'));
    }

    // ==================== Helper Methods ====================

    private function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    private function setPrivateProperty(&$object, $propertyName, $value)
    {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function getPrivateProperty(&$object, $propertyName)
    {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
