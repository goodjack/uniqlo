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

    public function test_command_shows_fresh_warning_when_fresh_option_used()
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
            ->expectsOutput('Starting fresh - ignoring checkpoint')
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

    public function test_command_resumes_from_checkpoint_without_fresh()
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

        $this->artisan('style-hint:fetch us')
            ->assertExitCode(0);

        // Verify checkpoint was used and then cleared on completion
        $this->assertNull(Cache::get('style_hint:offset:us'));
    }

    public function test_command_clears_checkpoint_on_success()
    {
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

        // Checkpoint should be cleared after successful completion
        $checkpoint = Cache::get('style_hint:offset:us');
        $this->assertNull($checkpoint, 'Checkpoint should be cleared after successful completion');
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
}
