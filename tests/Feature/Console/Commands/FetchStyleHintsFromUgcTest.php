<?php

namespace Tests\Feature\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchStyleHintsFromUgcTest extends TestCase
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
        Config::set('cache.default', 'array');
        Config::set('app.crawler.page_sizes.ugc_style_hints', 50);
        Config::set('app.crawler.delay.min', 0);
        Config::set('app.crawler.delay.max', 0);
        Config::set('app.crawler.retry.sleep_min', 0);
        Config::set('app.crawler.retry.sleep_max', 0);
        Config::set('app.crawler.batch_rest.offset.interval', 0);
        Config::set('app.crawler.batch_rest.detail.interval', 0);

        // Prevent real Discord notifications during tests
        Event::fake();
    }

    public function test_command_shows_fresh_warning_when_fresh_option_used()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'content_list' => [],
                    'total_result_count' => 0,
                ]
            ),
        ]);

        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc/list');

        $this->artisan('style-hint-ugc:fetch UNIQLO --fresh')
            ->expectsOutput('Starting fresh - ignoring checkpoint')
            ->assertExitCode(0);
    }

    public function test_command_accepts_brand_argument()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'content_list' => [],
                    'total_result_count' => 0,
                ]
            ),
        ]);

        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc/list');
        Config::set('gu.api.ugc_style_hint_list.tw', 'https://api.example.com/gu/ugc/list');

        $this->artisan('style-hint-ugc:fetch GU')
            ->assertExitCode(0);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.example.com/gu/ugc/list')
            && $request['brand'] === 'gu'
            && $request->header('x-fr-clientid') === ['gutw-sth-sb-proxy']);
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://api.example.com/ugc/list'));
    }

    public function test_command_stops_on_403_blocking()
    {
        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc/list');

        $this->artisan('style-hint-ugc:fetch UNIQLO --is-scheduled')
            ->assertExitCode(1);

        // 403 不重試、不換下一個性別，並放掉排程旗標讓手動抓取之後能再跑
        Http::assertSentCount(1);
        $this->assertFalse(Cache::get('style_hint_ugc:scheduling'));
        Event::assertDispatched(AppTaskStarting::class);
        Event::assertNotDispatched(AppTaskFinished::class);
    }

    public function test_command_starts_from_first_page_with_fresh_option()
    {
        Cache::forever('style_hint_ugc:manual:UNIQLO:last_page', 5);
        Cache::forever('style_hint_ugc:manual:UNIQLO:last_gender', '1');

        Http::fake([
            '*' => Http::response(
                [
                    'content_list' => [],
                    'total_result_count' => 0,
                ]
            ),
        ]);

        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc/list');

        $this->artisan('style-hint-ugc:fetch UNIQLO --fresh')
            ->assertExitCode(0);

        $this->assertSame([1], $this->pagesForGender('1'), '帶 --fresh 要從第 1 頁開始，不沿用續跑點');
    }

    public function test_command_resumes_from_checkpoint_without_fresh_option()
    {
        Cache::forever('style_hint_ugc:manual:UNIQLO:last_page', 5);
        Cache::forever('style_hint_ugc:manual:UNIQLO:last_gender', '1');

        Http::fake([
            '*' => Http::response(
                [
                    'content_list' => [],
                    'total_result_count' => 0,
                ]
            ),
        ]);

        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc/list');

        $this->artisan('style-hint-ugc:fetch UNIQLO')
            ->assertExitCode(0);

        $this->assertSame([5], $this->pagesForGender('1'), '不帶 --fresh 要從續跑點那一頁開始');
    }

    public function test_command_with_only_recent_option()
    {
        // 頁大小放大，讓上限 10000 筆只對應 5 頁；不設上限時 50000 筆會抓到第 21 頁
        Config::set('app.crawler.page_sizes.ugc_style_hints', 2500);

        Http::fake([
            '*' => Http::response(
                [
                    'content_list' => [],
                    'total_result_count' => 50000,
                ]
            ),
        ]);

        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc/list');

        $this->artisan('style-hint-ugc:fetch UNIQLO --only-recent')
            ->expectsOutput('Only recent style hints will be fetched.')
            ->assertExitCode(0);

        $this->assertSame([1, 2, 3, 4, 5], $this->pagesForGender('1'));
    }

    public function test_command_with_is_scheduled_option()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'content_list' => [],
                    'total_result_count' => 0,
                ]
            ),
        ]);

        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc/list');

        $this->artisan('style-hint-ugc:fetch UNIQLO --is-scheduled')
            ->doesntExpectOutput('This is a manual task.')
            ->assertExitCode(0);

        Event::assertDispatched(AppTaskStarting::class, fn (AppTaskStarting $event) => $event->data['isManual'] === false);
        $this->assertFalse(Cache::get('style_hint_ugc:scheduling'), '排程跑完要放掉旗標');
    }

    public function test_command_dispatches_task_events()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'content_list' => [],
                    'total_result_count' => 0,
                ]
            ),
        ]);

        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc/list');

        $this->artisan('style-hint-ugc:fetch UNIQLO')
            ->assertExitCode(0);

        // Verify AppTaskStarting and AppTaskFinished events were dispatched
        Event::assertDispatched(AppTaskStarting::class);
        Event::assertDispatched(AppTaskFinished::class);
    }

    /**
     * @return int[] 依送出順序列出該性別 UGC 清單請求的頁碼
     */
    private function pagesForGender(string $gender): array
    {
        return Http::recorded()
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $request) => ($request->data()['style_gender'] ?? null) === [$gender])
            ->map(fn (Request $request) => $request['page'])
            ->values()
            ->all();
    }
}
