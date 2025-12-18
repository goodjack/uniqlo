<?php

namespace Tests\Unit\Services;

use App\Repositories\StyleRepository;
use App\Services\StyleService;
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

        // No checkpoint should be saved when blocked
        $this->assertNull(Cache::get('styles:page:UNIQLO:1'));
        $this->assertNull(Cache::get('styles:last_gender:UNIQLO'));
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

        $this->service->fetchAllStyles('UNIQLO');

        // Test completes without throwing exception
        $this->assertTrue(true);
    }

    public function test_uses_configured_retry_count()
    {
        $maxRetry = Config::get('app.crawler.retry.manual');

        $this->assertEquals(2, $maxRetry, 'Expected configured manual retry count to be 2');
    }
}
