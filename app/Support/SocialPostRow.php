<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Notion 社群熱推清單的一列：讀出同步要用的欄位，以及我們寫在名稱上的重複提示。
 */
final class SocialPostRow
{
    // 合併不了時寫在重複那列名稱的提示開頭；合併時不把這種名稱搬去留下的那列
    public const DUPLICATE_HINT = '重複：';

    // 「商品編號」裡的一個貨號，可接影片時間。長度跟資料表的 code 欄一致；只差大小寫的當同一件，資料庫比對本來就不分大小寫
    private const CODE_TOKEN = '/^([A-Za-z0-9-]{1,20})(?:[@＠](\d+(?::\d{1,2}){0,2}))?$/u';

    /**
     * @return array{notion_page_id: string, notion_created_at: Carbon, notion_edited_at: Carbon,
     *     name: string, url: ?string, codes: array<int, array{code: string, start_seconds: ?int, token: string}>,
     *     code_tokens: array<int, array{code: string, start_seconds: ?int, token: string}>, codes_text: string,
     *     unknown_codes: bool, sort: ?int, has_sync_status: bool, sync_status: ?string}
     */
    public static function fromPage(array $page): array
    {
        $properties = $page['properties'];
        $text = fn (string $key) => collect($properties[$key][$properties[$key]['type'] ?? 'rich_text'] ?? [])
            ->pluck('plain_text')
            ->implode('');

        $sort = $properties['排序']['number'] ?? null;
        $codes = self::parseCodes($text('商品編號'));

        return [
            'notion_page_id' => $page['id'],
            'notion_created_at' => self::notionTime($page['created_time']),
            'notion_edited_at' => self::notionTime($page['last_edited_time']),
            'name' => trim($text('名稱')),
            'url' => $properties['網址']['url'] ?? null,
            // 同一件寫了幾次只算一次，存進資料庫與比對貨號用
            'codes' => collect($codes['tokens'])->unique(fn (array $code) => strtoupper($code['code']))->values()->all(),
            // 同一件寫了幾次就有幾筆；合併前檢查重複那列有沒有自己的內容要全部看過
            'code_tokens' => $codes['tokens'],
            // 原文留著，合併時只在後面接，不改使用者寫的；有認不得的文字就當作有自己的內容
            'codes_text' => $codes['text'],
            'unknown_codes' => $codes['unknown'],
            'sort' => $sort === null ? null : (int) $sort,
            // 讀出目前的值，寫回前比對，一樣就不送
            'has_sync_status' => array_key_exists('同步狀態', $properties),
            'sync_status' => $properties['同步狀態']['select']['name'] ?? null,
        ];
    }

    public static function isDuplicateHint(string $name): bool
    {
        return str_starts_with($name, self::DUPLICATE_HINT);
    }

    /** Notion 給 UTC；Eloquent 存時間不換時區，不先換成網站設定的時區，讀回來會差 8 小時 */
    public static function notionTime(string $time): Carbon
    {
        return Carbon::parse($time)->setTimezone(config('app.timezone'));
    }

    /**
     * 「479123@1:23, 470001」：貨號後面可接影片講到這件的時間（分:秒、時:分:秒或秒數）。
     * 原本的寫法也留著，合併重複列時照使用者的寫法接上去。
     *
     * @return array{text: string, tokens: array<int, array{code: string, start_seconds: ?int, token: string}>, unknown: bool}
     */
    private static function parseCodes(string $text): array
    {
        $tokens = [];
        $unknown = false;

        foreach (preg_split('/[\s,，、]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            if (! preg_match(self::CODE_TOKEN, $token, $m)) {
                $unknown = true;

                continue;
            }

            $seconds = isset($m[2]) ? array_reduce(explode(':', $m[2]), fn ($carry, $part) => $carry * 60 + (int) $part, 0) : null;
            $tokens[] = ['code' => $m[1], 'start_seconds' => $seconds, 'token' => $token];
        }

        return ['text' => $text, 'tokens' => $tokens, 'unknown' => $unknown];
    }
}
