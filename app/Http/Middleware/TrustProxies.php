<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * 實際信任清單由 proxies() 提供。
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * Cloudflare 公布的邊緣節點 IP 範圍，2026-09-21 對照
     * https://www.cloudflare.com/ips-v4 與 https://www.cloudflare.com/ips-v6。
     *
     * 刻意不用 '*'：Cloudflare 對 X-Forwarded-For 是附加不是覆寫，訪客自己
     * 塞的假 IP 會留在表頭前段。只信任 Cloudflare 範圍時，框架取的是
     * Cloudflare 附加在最後的真實來源；信任 '*' 就會把假 IP 當真，限流形同虛設。
     *
     * @var array<int, string>
     */
    private const CLOUDFLARE_PROXIES = [
        // IPv4
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        // IPv6
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * 只信任 Cloudflare 會自己寫入的兩個表頭。X-Forwarded-Host／Port 是
     * Cloudflare 原封不動轉送的訪客輸入，信任它們會讓訪客改掉頁面上產生的
     * 網址網域（若 HTML 被 Cloudflare 快取，會影響其他訪客）。
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO;

    /**
     * TRUSTED_PROXIES_EXTRA 只給本機模擬代理用，正式站不要設。
     *
     * @return array<int, string>
     */
    protected function proxies()
    {
        return array_merge(self::CLOUDFLARE_PROXIES, config('trustedproxy.extra_proxies', []));
    }
}
