<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FetchStyleHintsTest extends TestCase
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
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->artisan('style-hint:fetch us --fresh')
            ->assertExitCode(0);
    }

    public function test_command_runs_without_fresh_option()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->artisan('style-hint:fetch us')
            ->assertExitCode(0);
    }

    public function test_command_warns_fresh_without_backfill()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->artisan('style-hint:fetch us --fresh')
            ->expectsOutput('--fresh has no effect without --backfill (daily mode always starts from 0)')
            ->assertExitCode(0);
    }

    public function test_command_shows_fresh_backfill_warning()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->artisan('style-hint:fetch us --fresh --backfill')
            ->expectsOutput('Starting fresh backfill - clearing checkpoint')
            ->assertExitCode(0);
    }

    public function test_command_stops_on_403_blocking()
    {
        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        Log::spy();

        $this->artisan('style-hint:fetch us')
            ->assertExitCode(0); // Command itself doesn't error, but logs the block

        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_command_with_backfill_resumes_from_checkpoint()
    {
        $checkpointOffset = 50;
        Cache::set('style_hint:offset:us', $checkpointOffset);

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->artisan('style-hint:fetch us --backfill')
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.example.com/style-hint-list?offset=50&limit=50&userType=0%2C1%2C2%2C3&order=published_at%3Adesc';
        });
    }

    public function test_daily_mode_ignores_checkpoint()
    {
        // Set a checkpoint that daily mode should ignore
        Cache::set('style_hint:offset:us', 100);

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->artisan('style-hint:fetch us')
            ->assertExitCode(0);

        // Daily mode always starts from offset 0
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.example.com/style-hint-list?offset=0&limit=50&userType=0%2C1%2C2%2C3&order=published_at%3Adesc';
        });
    }

    public function test_backfill_clears_checkpoint_on_success()
    {
        Cache::set('style_hint:offset:us', 50);

        Http::fake([
            'https://api.example.com/style-hint-list*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->artisan('style-hint:fetch us --backfill')
            ->assertExitCode(0);

        // Checkpoint should be cleared after successful backfill completion
        $checkpoint = Cache::get('style_hint:offset:us');
        $this->assertNull($checkpoint, 'Checkpoint should be cleared after successful backfill completion');
    }

    public function test_command_with_multiple_batches()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->artisan('style-hint:fetch us --fresh')
            ->assertExitCode(0);

        // Should have made at least one request
        $this->assertTrue(Http::recorded()->count() >= 1);
    }

    public function test_command_accepts_backfill_option()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->artisan('style-hint:fetch us --backfill')
            ->expectsOutput('Backfilling style hints for us...')
            ->assertExitCode(0);
    }

    public function test_daily_mode_shows_fetching_message()
    {
        Http::fake([
            '*' => Http::response(
                [
                    'result' => [
                        'images' => [],
                        'pagination' => ['total' => 0],
                    ],
                ]
            ),
        ]);

        Config::set('uniqlo.api.style_hint_list.us', 'https://api.example.com/style-hint-list');

        $this->artisan('style-hint:fetch us')
            ->expectsOutput('Fetching style hints for us...')
            ->assertExitCode(0);
    }
}
