<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\CrawlOutcome;
use App\Events\AppTaskFinished;
use App\Services\HmallProductService;
use App\Support\CrawlResult;
use App\Support\TaskNotes;
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
            ->with('UNIQLO', true)
            ->willReturn(new CrawlResult(CrawlOutcome::Succeeded));

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO --fresh')
            ->assertExitCode(0);
    }

    public function test_command_shows_fresh_warning()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProducts')
            ->willReturn(new CrawlResult(CrawlOutcome::Succeeded));

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO --fresh')
            ->expectsOutput('Starting fresh - ignoring checkpoint')
            ->assertExitCode(0);
    }

    public function test_command_stops_on_403_blocking()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProducts')
            ->willReturn(new CrawlResult(CrawlOutcome::Failed));

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO')
            ->assertExitCode(1);
    }

    public function test_command_resumes_from_checkpoint()
    {
        Cache::set('hmall_products:page:UNIQLO', 2);

        $mockService = $this->createMock(HmallProductService::class);
        $mockService->expects($this->once())
            ->method('fetchAllHmallProducts')
            ->with('UNIQLO', false)
            ->willReturn(new CrawlResult(CrawlOutcome::Succeeded));

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO')
            ->assertExitCode(0);
    }

    /**
     * 部分成功時要把這一輪的說明留給排程，通知才寫得出有沒有做缺貨判定。
     */
    public function test_a_partial_success_leaves_an_explanation_for_the_schedule()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProducts')
            ->willReturn(new CrawlResult(
                CrawlOutcome::PartiallySucceeded,
                '已執行缺貨判定，排除 1 件寫入失敗商品'
            ));

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO')
            ->assertExitCode(CrawlOutcome::PartiallySucceeded->value);

        $this->assertSame(
            '已執行缺貨判定，排除 1 件寫入失敗商品',
            app(TaskNotes::class)->pull('hmall-product:fetch', 'UNIQLO')
        );
    }

    /**
     * 部分成功時仍然要送結束通知並附說明：手動執行時沒有排程彙整，不送的話
     * start 之後就沒有下文。
     */
    public function test_a_partial_success_still_sends_the_finished_notification()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProducts')
            ->willReturn(new CrawlResult(
                CrawlOutcome::PartiallySucceeded,
                '未執行缺貨判定，目錄有缺頁'
            ));

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO')->run();

        Event::assertDispatched(AppTaskFinished::class, function (AppTaskFinished $event) {
            return $event->brand === 'UNIQLO'
                && $event->data === ['result' => '部分成功：未執行缺貨判定，目錄有缺頁'];
        });
    }

    /**
     * 完全失敗仍然不送結束通知：那一輪真的什麼都沒完成。
     */
    public function test_a_total_failure_does_not_send_the_finished_notification()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProducts')
            ->willReturn(new CrawlResult(CrawlOutcome::Failed));

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO')->run();

        Event::assertNotDispatched(AppTaskFinished::class);
    }

    public function test_the_accept_shrink_option_is_passed_to_the_service()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->expects($this->once())
            ->method('fetchAllHmallProducts')
            ->with('GU', false, true)
            ->willReturn(new CrawlResult(CrawlOutcome::Succeeded));

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch GU --accept-shrink')->assertExitCode(0);
    }

    /**
     * 缺貨判定逾期跟完全失敗一樣不送一般的結束通知，交給排程發紅色的失敗通知。
     */
    public function test_an_overdue_stockout_does_not_send_the_finished_notification()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProducts')
            ->willReturn(new CrawlResult(CrawlOutcome::StockoutOverdue, '已經 3 天沒有執行缺貨判定'));

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO')->assertExitCode(CrawlOutcome::StockoutOverdue->value);

        Event::assertNotDispatched(AppTaskFinished::class);
    }

    /**
     * 整批抓完、沒有任何問題時不留說明：通知只會寫成功，沒有什麼要多講的。
     */
    public function test_a_clean_run_leaves_no_explanation()
    {
        $mockService = $this->createMock(HmallProductService::class);
        $mockService->method('fetchAllHmallProducts')
            ->willReturn(new CrawlResult(CrawlOutcome::Succeeded));

        $this->app->instance(HmallProductService::class, $mockService);

        $this->artisan('hmall-product:fetch UNIQLO')->assertExitCode(0);

        $this->assertNull(app(TaskNotes::class)->pull('hmall-product:fetch', 'UNIQLO'));
    }
}
