<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 測試環境的連線來源固定是 127.0.0.1；把它加進 extra_proxies 就是模擬
 * 「請求經過 Cloudflare」，不加就是直連。
 */
class TrustProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test/ip', fn () => request()->ip());
        Route::get('/__test/url', fn () => url('/x'));
    }

    public function test_x_forwarded_for_is_ignored_when_the_connecting_ip_is_not_a_trusted_proxy(): void
    {
        $response = $this->get('/__test/ip', ['X-Forwarded-For' => '1.2.3.4']);

        $response->assertContent('127.0.0.1');
    }

    public function test_x_forwarded_for_is_trusted_once_the_connecting_ip_is_a_trusted_proxy(): void
    {
        config(['trustedproxy.extra_proxies' => ['127.0.0.1']]);

        $response = $this->get('/__test/ip', ['X-Forwarded-For' => '1.2.3.4']);

        $response->assertContent('1.2.3.4');
    }

    /**
     * Cloudflare 把真實來源附加在最後，訪客偽造的值留在前面。
     */
    public function test_a_forged_prefix_in_x_forwarded_for_is_ignored_in_favor_of_the_trusted_proxys_value(): void
    {
        config(['trustedproxy.extra_proxies' => ['127.0.0.1']]);

        $response = $this->get('/__test/ip', [
            'X-Forwarded-For' => '1.2.3.4, 203.0.113.9',
        ]);

        $response->assertContent('203.0.113.9');
    }

    public function test_forwarded_host_and_port_from_a_trusted_proxy_do_not_change_generated_urls(): void
    {
        config(['trustedproxy.extra_proxies' => ['127.0.0.1']]);

        $response = $this->get('/__test/url', [
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-Port' => '8443',
        ]);

        $this->assertStringNotContainsString('evil.example', $response->getContent());
        $this->assertStringNotContainsString('8443', $response->getContent());
    }

    public function test_favorites_cards_throttle_tracks_separate_quotas_per_forwarded_ip(): void
    {
        config(['trustedproxy.extra_proxies' => ['127.0.0.1']]);

        for ($i = 0; $i < 60; $i++) {
            $this->postJson(route('favorites.cards'), ['items' => []], [
                'X-Forwarded-For' => '10.0.0.1',
            ])->assertStatus(422);
        }

        $this->postJson(route('favorites.cards'), ['items' => []], [
            'X-Forwarded-For' => '10.0.0.1',
        ])->assertStatus(429);

        $this->postJson(route('favorites.cards'), ['items' => []], [
            'X-Forwarded-For' => '10.0.0.2',
        ])->assertStatus(422);
    }
}
