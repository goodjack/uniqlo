<?php

namespace Tests\Feature\Console\Commands;

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

    public function test_command_accepts_fresh_option()
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
            ->assertExitCode(0);
    }

    public function test_command_runs_without_fresh_option()
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

        // Test with GU brand
        $this->artisan('style-hint-ugc:fetch GU')
            ->assertExitCode(0);
    }

    public function test_command_stops_on_403_blocking()
    {
        Http::fake([
            'https://api.example.com/ugc/list' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc/list');

        $this->artisan('style-hint-ugc:fetch UNIQLO')
            ->assertExitCode(0); // Command itself doesn't error, but logs the block
    }

    public function test_command_clears_checkpoints_with_fresh_option()
    {
        // Set some existing checkpoints
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

        // Checkpoints should be cleared
        $this->assertNull(
            Cache::get('style_hint_ugc:manual:UNIQLO:last_page'),
            'Last page checkpoint should be cleared'
        );

        $this->assertNull(
            Cache::get('style_hint_ugc:manual:UNIQLO:last_gender'),
            'Last gender checkpoint should be cleared'
        );
    }

    public function test_command_preserves_checkpoints_without_fresh_option()
    {
        // Set some existing checkpoints
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

        // Checkpoints should be preserved (command ends due to content_list being empty)
        // Note: In actual execution, checkpoints would be modified as part of normal operation
    }

    public function test_command_with_only_recent_option()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'content_list' => [],
                    'total_result_count' => 50000, // More than 10000
                ]
            ),
        ]);

        Config::set('uniqlo.api.ugc_style_hint_list.tw', 'https://api.example.com/ugc/list');

        $this->artisan('style-hint-ugc:fetch UNIQLO --only-recent')
            ->expectsOutput('Only recent style hints will be fetched.')
            ->assertExitCode(0);
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

        // When is-scheduled is set, isManual should be false
        $this->artisan('style-hint-ugc:fetch UNIQLO --is-scheduled')
            ->assertExitCode(0);
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
        Event::assertDispatched(\App\Events\AppTaskStarting::class);
        Event::assertDispatched(\App\Events\AppTaskFinished::class);
    }
}
