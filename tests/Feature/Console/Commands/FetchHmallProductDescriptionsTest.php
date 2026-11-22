<?php

namespace Tests\Feature\Console\Commands;

use App\Events\AppTaskFinished;
use App\Services\HmallProductService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FetchHmallProductDescriptionsTest extends TestCase
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

    public function test_a_fresh_run_passes_the_option_and_sends_the_finished_notification()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->expects($this->once())
            ->method('fetchAllHmallProductDescriptions')
            ->with('UNIQLO', false, true)
            ->willReturn(true);

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product-description:fetch UNIQLO --fresh')
            ->assertExitCode(0);

        Event::assertDispatched(AppTaskFinished::class, fn (AppTaskFinished $event) => $event->brand === 'UNIQLO');
    }

    public function test_command_shows_fresh_warning()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProductDescriptions')
            ->willReturn(true);

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product-description:fetch UNIQLO --fresh')
            ->expectsOutput('Starting fresh - ignoring checkpoint')
            ->assertExitCode(0);
    }

    public function test_a_failed_run_exits_with_failure_code_and_sends_no_finished_notification()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProductDescriptions')
            ->willReturn(false);

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product-description:fetch UNIQLO')
            ->assertExitCode(1);

        Event::assertNotDispatched(AppTaskFinished::class);
    }
}
