<?php

namespace Tests\Feature\Console\Commands;

use App\Http\Middleware\SetEdgeCacheHeaders;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PurgeCloudflareCacheTest extends TestCase
{
    private const PURGE_URL = 'https://api.cloudflare.com/client/v4/zones/zone-123/purge_cache';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare.zone_id' => 'zone-123',
            'services.cloudflare.purge_token' => 'token-abc',
        ]);
        Log::spy();
    }

    public function test_purges_only_the_cached_pages_in_the_configured_zone(): void
    {
        Http::fake([self::PURGE_URL => Http::response(['success' => true, 'errors' => []])]);

        $this->artisan('cloudflare:purge')->assertExitCode(Command::SUCCESS);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::PURGE_URL
            && $request->hasHeader('Authorization', 'Bearer token-abc')
            && $request->data() === ['tags' => [SetEdgeCacheHeaders::CACHE_TAG]]);
    }

    /**
     * 本機與還沒設定的環境：每日排程照常跑完，不因為清快取而算失敗。
     */
    public function test_skips_without_failing_when_not_configured(): void
    {
        config(['services.cloudflare.purge_token' => '']);
        Http::fake();

        $this->artisan('cloudflare:purge')->assertExitCode(Command::SUCCESS);

        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_a_rejected_purge_is_logged_and_fails(): void
    {
        Http::fake([self::PURGE_URL => Http::response([
            'success' => false,
            'errors' => [['code' => 10000, 'message' => 'Authentication error']],
        ], 403)]);

        $this->artisan('cloudflare:purge')->assertExitCode(Command::FAILURE);

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context) => $context['status'] === 403
                && $context['errors'] === [['code' => 10000, 'message' => 'Authentication error']]
        );
    }

    public function test_a_connection_failure_is_logged_and_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->artisan('cloudflare:purge')->assertExitCode(Command::FAILURE);

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context) => $context['exception'] instanceof ConnectionException
        );
    }
}
