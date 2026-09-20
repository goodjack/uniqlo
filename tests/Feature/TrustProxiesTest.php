<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PR #76 審查留言（4057184150）：正式站前面有 Cloudflare，
 * App\Http\Middleware\TrustProxies 原本沒信任任何代理，$request->ip()
 * 對所有訪客都回傳 Cloudflare 的 IP，讓 favorites.cards／search／categories
 * 的限流全站共用一個額度。這裡釘住修好之後的行為。
 *
 * 用一條只在測試裡存在的臨時路由回傳 $request->ip()，不動 routes/web.php。
 */
class TrustProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test/ip', fn () => request()->ip());
    }

    public function test_x_forwarded_for_is_ignored_when_the_connecting_ip_is_not_a_trusted_proxy(): void
    {
        // 測試環境的連線來源固定是 127.0.0.1（Symfony Request::create 的預設
        // REMOTE_ADDR），沒有把它加進信任清單，就是模擬「本機沒有 Cloudflare」
        // 的原始狀況：不該理會 X-Forwarded-For，看到的還是連線來源本身。
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
     * 模擬 Cloudflare 對 X-Forwarded-For 的「附加不是覆寫」：訪客自己塞的假
     * IP 留在表頭最前面，Cloudflare 加上去的真實來源 IP 在最後面。取到的要是
     * 最後面那個真的，不能是訪客自己塞的假的——這是這一則審查留言的重點，
     * 也是不能把信任清單填 '*' 的原因（填 '*' 一樣會把最前面那個假的當真）。
     */
    public function test_a_forged_prefix_in_x_forwarded_for_is_ignored_in_favor_of_the_trusted_proxys_value(): void
    {
        config(['trustedproxy.extra_proxies' => ['127.0.0.1']]);

        $response = $this->get('/__test/ip', [
            'X-Forwarded-For' => '1.2.3.4, 203.0.113.9',
        ]);

        $response->assertContent('203.0.113.9');
    }

    /**
     * 限流的識別（ThrottleRequests::resolveRequestSignature，預設用
     * $request->ip()）要真的跟著 IP 走：不同來源各自有各自的額度，不會因為
     * 都經過同一台代理就共用同一份。
     */
    public function test_favorites_cards_throttle_tracks_separate_quotas_per_forwarded_ip(): void
    {
        config(['trustedproxy.extra_proxies' => ['127.0.0.1']]);

        for ($i = 0; $i < 60; $i++) {
            $this->postJson(route('favorites.cards'), ['items' => []], [
                'X-Forwarded-For' => '10.0.0.1',
            ])->assertStatus(422);
        }

        // 10.0.0.1 的額度用完了，第 61 次要被限流擋掉（429），不是驗證錯誤
        $this->postJson(route('favorites.cards'), ['items' => []], [
            'X-Forwarded-For' => '10.0.0.1',
        ])->assertStatus(429);

        // 10.0.0.2 是另一個來源，額度是獨立的，不受 10.0.0.1 剛剛用完的影響
        $this->postJson(route('favorites.cards'), ['items' => []], [
            'X-Forwarded-For' => '10.0.0.2',
        ])->assertStatus(422);
    }
}
