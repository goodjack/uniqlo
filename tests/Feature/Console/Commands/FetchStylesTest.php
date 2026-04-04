<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchStylesTest extends TestCase
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
        Config::set('app.crawler.page_sizes.official_styles', 50);
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
            '*' => Http::response([
                'result' => [
                    'styles' => [],
                    'total_styles' => 0,
                ],
            ]),
        ]);

        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        $this->artisan('style:fetch UNIQLO --fresh')
            ->assertExitCode(0);
    }

    public function test_command_shows_fresh_warning()
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

        $this->artisan('style:fetch UNIQLO --fresh')
            ->expectsOutput('Starting fresh - ignoring checkpoint')
            ->assertExitCode(0);
    }

    public function test_command_stops_on_403_blocking()
    {
        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.ugc_official_style_list.tw', 'https://api.example.com/styles');

        $this->artisan('style:fetch UNIQLO')
            ->assertExitCode(1);
    }

    public function test_command_resumes_from_checkpoint_per_gender()
    {
        Cache::set('styles:page:UNIQLO:1', 2);
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

        $this->artisan('style:fetch UNIQLO')
            ->assertExitCode(0);

        // Checkpoints should be cleared after completion
        $this->assertNull(Cache::get('styles:page:UNIQLO:1'));
        $this->assertNull(Cache::get('styles:last_gender:UNIQLO'));
    }
}
