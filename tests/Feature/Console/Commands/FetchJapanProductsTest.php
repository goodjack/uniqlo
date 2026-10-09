<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\CrawlOutcome;
use App\Events\AppTaskFinished;
use App\Services\JapanProductService;
use App\Support\CrawlResult;
use App\Support\TaskNotes;
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
            ->with('UNIQLO', true)
            ->willReturn(new CrawlResult(CrawlOutcome::Succeeded));

        $this->app->instance(JapanProductService::class, $mockService);

        $this->artisan('japan-product:fetch UNIQLO --fresh')
            ->assertExitCode(0);
    }

    public function test_command_shows_fresh_warning()
    {
        $mockService = $this->createMock(JapanProductService::class);
        $mockService->method('fetchAllProducts')
            ->willReturn(new CrawlResult(CrawlOutcome::Succeeded));

        $this->app->instance(JapanProductService::class, $mockService);

        $this->artisan('japan-product:fetch UNIQLO --fresh')
            ->expectsOutput('Starting fresh - ignoring checkpoint')
            ->assertExitCode(0);
    }

    public function test_command_stops_on_403_blocking()
    {
        $mockService = $this->createMock(JapanProductService::class);
        $mockService->method('fetchAllProducts')
            ->willReturn(new CrawlResult(CrawlOutcome::Failed));

        $this->app->instance(JapanProductService::class, $mockService);

        $this->artisan('japan-product:fetch UNIQLO')
            ->assertExitCode(1);
    }

    public function test_command_resumes_from_checkpoint()
    {
        Cache::set('japan_products:offset:UNIQLO', 72);

        $mockService = $this->createMock(JapanProductService::class);
        $mockService->expects($this->once())
            ->method('fetchAllProducts')
            ->with('UNIQLO', false)
            ->willReturn(new CrawlResult(CrawlOutcome::Succeeded));

        $this->app->instance(JapanProductService::class, $mockService);

        $this->artisan('japan-product:fetch UNIQLO')
            ->assertExitCode(0);
    }

    /**
     * 部分成功（例如目錄有缺頁、今天沒做缺貨判定）要讓排程看得出來，並送出附說明的結束通知。
     */
    public function test_the_accept_shrink_option_is_passed_to_the_service()
    {
        $mockService = $this->createMock(JapanProductService::class);
        $mockService->expects($this->once())
            ->method('fetchAllProducts')
            ->with('GU', false, true)
            ->willReturn(new CrawlResult(CrawlOutcome::Succeeded));

        $this->app->instance(JapanProductService::class, $mockService);

        $this->artisan('japan-product:fetch GU --accept-shrink')->assertExitCode(0);
    }

    public function test_a_partial_success_is_reported_with_its_note()
    {
        $mockService = $this->createMock(JapanProductService::class);
        $mockService->method('fetchAllProducts')
            ->willReturn(new CrawlResult(
                CrawlOutcome::PartiallySucceeded,
                '未執行缺貨判定，目錄有缺頁（最早在第 2 頁）'
            ));

        $this->app->instance(JapanProductService::class, $mockService);

        $this->artisan('japan-product:fetch UNIQLO')
            ->assertExitCode(CrawlOutcome::PartiallySucceeded->value);

        $this->assertSame(
            '未執行缺貨判定，目錄有缺頁（最早在第 2 頁）',
            app(TaskNotes::class)->pull('japan-product:fetch', 'UNIQLO')
        );
        Event::assertDispatched(AppTaskFinished::class, fn (AppTaskFinished $event) => $event->brand === 'UNIQLO'
            && $event->data === ['result' => '部分成功：未執行缺貨判定，目錄有缺頁（最早在第 2 頁）']);
    }
}
