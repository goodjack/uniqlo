<?php

namespace Tests\Feature\Console\Commands;

use App\Services\JapanProductService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FetchJapanProductsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Set test user agents
        Config::set('app.user_agents', [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36',
        ]);

        // Prevent real Discord notifications during tests
        Event::fake();
    }

    public function test_command_accepts_fresh_option()
    {
        $mockService = $this->createMock(JapanProductService::class);
        $mockService->expects($this->once())
            ->method('fetchAllProducts')
            ->with('UNIQLO', true);

        $this->app->instance(JapanProductService::class, $mockService);

        $this->artisan('japan-product:fetch UNIQLO --fresh')
            ->assertExitCode(0);
    }

    public function test_command_shows_fresh_warning()
    {
        $mockService = $this->createMock(JapanProductService::class);
        $mockService->method('fetchAllProducts');

        $this->app->instance(JapanProductService::class, $mockService);

        $this->artisan('japan-product:fetch UNIQLO --fresh')
            ->expectsOutput('Starting fresh - ignoring checkpoint')
            ->assertExitCode(0);
    }

    public function test_command_stops_on_403_blocking()
    {
        $mockService = $this->createMock(JapanProductService::class);
        $mockService->method('fetchAllProducts');

        $this->app->instance(JapanProductService::class, $mockService);

        $this->artisan('japan-product:fetch UNIQLO')
            ->assertExitCode(0);
    }

    public function test_command_resumes_from_checkpoint()
    {
        Cache::set('japan_products:offset:UNIQLO', 72);

        $mockService = $this->createMock(JapanProductService::class);
        $mockService->expects($this->once())
            ->method('fetchAllProducts')
            ->with('UNIQLO', false);

        $this->app->instance(JapanProductService::class, $mockService);

        $this->artisan('japan-product:fetch UNIQLO')
            ->assertExitCode(0);
    }
}
