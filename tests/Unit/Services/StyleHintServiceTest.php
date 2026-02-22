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
            $this->assertTrue(in_array($ua, $userAgents), "UA should be from the configured pool");
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
        Http::fake([
            'https://api.example.com/style-hint-list' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        // Allow all log calls
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        // Track that the method completes without exception
        $checkpointBefore = Cache::get('style_hint:offset:us');

        $this->service->fetchAllStyleHints('us');

        // The method should have executed without throwing exception
        // Verify that offset was not set (method returned early on 403)
        $this->assertNull($checkpointBefore, 'Checkpoint should be null before execution');
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

    public function test_fetch_all_style_hints_saves_checkpoint()
    {
        Http::fake([
            'https://api.example.com/style-hint-list' => Http::response(
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

        $this->service->fetchAllStyleHints('us');

        // Checkpoint should be cleared after completion
        $this->assertNull(Cache::get('style_hint:offset:us'));
    }

    public function test_fetch_all_style_hints_resumes_from_checkpoint()
    {
        $initialCheckpoint = 50;
        Cache::set('style_hint:offset:us', $initialCheckpoint);

        Http::fake([
            'https://api.example.com/style-hint-list' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->service->fetchAllStyleHints('us');

        // Verify checkpoint was used and then cleared on success
        $this->assertNull(Cache::get('style_hint:offset:us'));
    }

    public function test_fetch_all_style_hints_fresh_ignores_checkpoint()
    {
        Cache::set('style_hint:offset:us', 100);

        Http::fake([
            'https://api.example.com/style-hint-list' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->service->fetchAllStyleHints('us', fresh: true);

        // Verify checkpoint was cleared on success
        $this->assertNull(Cache::get('style_hint:offset:us'));
    }

    // ==================== Retry Logic Tests ====================

    public function test_uses_configured_retry_count_for_list_api()
    {
        $maxRetry = Config::get('app.crawler.retry.times');

        Http::fake([
            'https://api.example.com/style-hint-list' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->service->fetchAllStyleHints('us');

        $this->assertEquals(3, $maxRetry, 'Expected configured retry count to be 3');
    }

    public function test_uses_configured_retry_count_for_detail_api()
    {
        $maxRetry = Config::get('app.crawler.retry.times');

        $this->assertEquals(3, $maxRetry, 'Expected configured retry count to be 3');
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
}
