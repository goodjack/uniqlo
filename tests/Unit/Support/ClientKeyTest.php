<?php

namespace Tests\Unit\Support;

use App\Support\ClientKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClientKeyTest extends TestCase
{
    #[DataProvider('addresses')]
    public function test_it_keys_ipv6_by_its_64_prefix_and_keeps_ipv4_whole(?string $ip, string $expected): void
    {
        $this->assertSame($expected, ClientKey::fromIp($ip));
    }

    public static function addresses(): array
    {
        return [
            'IPv4 原樣' => ['203.0.113.7', '203.0.113.7'],
            '同一段 IPv4 不合併' => ['203.0.113.8', '203.0.113.8'],
            'IPv6 合併到 /64' => ['2001:db8:1:2:aaaa:bbbb:cccc:dddd', '2001:db8:1:2::/64'],
            '同一段 IPv6 換位址仍是同一個' => ['2001:db8:1:2::1', '2001:db8:1:2::/64'],
            '縮寫的 IPv6' => ['2001:db8::1', '2001:db8::/64'],
            '沒有 IP' => [null, 'unknown'],
        ];
    }
}
