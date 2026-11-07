<?php

namespace App\Support;

class ClientKey
{
    /**
     * 限流用的來源代號。IPv6 一般一個家庭或一支手機就拿到整段 /64，只看完整位址的話
     * 換個位址就等於換一個人，所以合併到 /64；IPv4 維持完整位址，行動網路常有很多人
     * 共用同一段，合併會擋到不相干的人。
     */
    public static function fromIp(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return 'unknown';
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip;
        }

        $packed = inet_pton($ip);

        return inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
