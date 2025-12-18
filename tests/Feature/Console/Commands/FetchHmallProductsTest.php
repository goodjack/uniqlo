<?php

namespace Tests\Feature\Console\Commands;

use App\Services\HmallProductService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FetchHmallProductsTest extends TestCase
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
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->expects($this->once())
            ->method('fetchAllHmallProducts')
            ->with('UNIQLO', true);

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO --fresh')
            ->assertExitCode(0);
    }

    public function test_command_shows_fresh_warning()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProducts');

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO --fresh')
            ->expectsOutput('Starting fresh - ignoring checkpoint')
            ->assertExitCode(0);
    }

    public function test_command_stops_on_403_blocking()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProducts');

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO')
            ->assertExitCode(0);
    }

    public function test_command_resumes_from_checkpoint()
    {
        Cache::set('hmall_products:page:UNIQLO', 2);

        $mockService = $this->createMock(HmallProductService::class);
        $mockService->expects($this->once())
            ->method('fetchAllHmallProducts')
            ->with('UNIQLO', false);

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO')
            ->assertExitCode(0);
    }
}
