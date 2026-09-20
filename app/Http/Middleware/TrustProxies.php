<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * 這個屬性留空，實際信任清單由 proxies() 提供（見下方）。
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * Cloudflare 官方公布的邊緣節點 IP 範圍（IPv4 + IPv6）。
     *
     * 來源：https://www.cloudflare.com/ips-v4、https://www.cloudflare.com/ips-v6
     * （2026-09-21 核對）。Cloudflare 改動這份清單不頻繁，但仍是人工維護，
     * 之後要更新時對照這兩個網址重新貼一份即可。
     *
     * 這裡刻意列出實際範圍、不用 '*'（信任所有代理）：Cloudflare 對
     * X-Forwarded-For 是「附加」不是「覆寫」——訪客自己在請求裡塞
     * X-Forwarded-For: 1.2.3.4，經過 Cloudflare 之後會變成
     * X-Forwarded-For: 1.2.3.4, <Cloudflare 觀察到的真實來源 IP>，假的值留在
     * 前面、真的值被加在最後面。Symfony 的信任代理演算法（見下方 $headers
     * 的說明）會把信任清單裡「直接連到我們的那一台」對應的 IP 從表頭尾端摘掉，
     * 取摘掉之後最後一個值當成真實來源；只要信任清單只放 Cloudflare 的邊緣
     * 節點（不是 '*'），這個演算法就會自動忽略訪客自己塞的假值，取到
     * Cloudflare 附加的那個真實 IP。
     *
     * 如果信任清單填 '*'，等於整條表頭都信，訪客自己塞的假 IP 會被直接當真
     * ——換一個假 IP 就換一份新額度，favorites.cards（每分鐘 60 次）、search
     * 與 categories（每分鐘 30 次）的限流形同虛設；如果有人查到來源伺服器的
     * 真實 IP、直接繞過 Cloudflare 打進來，'*' 也會讓他能任意偽造來源 IP。
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
     * 要相信的表頭維持框架預設（含 X-Forwarded-For），沒有另外信任
     * CF-Connecting-IP。Cloudflare 對 CF-Connecting-IP 是覆寫、理論上比
     * X-Forwarded-For 更省事、更不需要處理整條表頭的邏輯，但這個專案用的
     * Symfony 版本（vendor/symfony/http-foundation/Request.php 的
     * TRUSTED_HEADERS 常數）只認 Forwarded、X-Forwarded-For、
     * X-Forwarded-Host、X-Forwarded-Proto、X-Forwarded-Port、
     * X-Forwarded-Prefix 這幾個固定表頭，沒有開放信任任意表頭名稱的介面，
     * 要用 CF-Connecting-IP 得另外寫一個 middleware 手動改寫 REMOTE_ADDR。
     * 上面 CLOUDFLARE_PROXIES 的說明已經證明「信任清單 = Cloudflare 範圍」時
     * X-Forwarded-For 本身就能正確擋掉訪客偽造的值，所以先不加這個新
     * middleware，列為未來可以做的加強、不是這次的範圍。
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * 正式站的信任清單固定是 CLOUDFLARE_PROXIES，不受任何環境變數影響。
     * 本機沒有 Cloudflare，要模擬「代理已經在 X-Forwarded-For 加上真實 IP」
     * 的情境時，才用 config/trustedproxy.php 讀進來的 TRUSTED_PROXIES_EXTRA
     * 環境變數，額外加測試用的來源（例如本機連線來源 127.0.0.1）。正式站不要
     * 設這個變數，以免放寬信任範圍。
     *
     * @return array<int, string>
     */
    protected function proxies()
    {
        return array_merge(self::CLOUDFLARE_PROXIES, config('trustedproxy.extra_proxies', []));
    }
}
