<?php

namespace App\Services;

use App\Enums\SocialPostStatus;
use App\Enums\SocialSyncStatus;
use App\Models\SocialPost;
use App\Support\SocialPostRow;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 同一篇貼文在 Notion 清單裡貼了不只一列時，把重複那幾列併進留下的那列、移到 Notion 垃圾桶；
 * 合併不了的，告訴同步要在那幾列標什麼狀態與提示名稱。
 */
class SocialPostDuplicateMerger
{
    // 剛加進清單的列可能還在整理，一組裡每一列都放超過這麼久才合併
    private const MERGE_AFTER_MINUTES = 30;

    // 判斷重複那列頁面內文時最多讀幾個區塊，讀不完就當作有內容
    private const PAGE_BLOCK_LIMIT = 10;

    // 只放貼文網址、不算使用者內容的區塊
    private const LINK_BLOCKS = ['embed', 'bookmark', 'link_preview'];

    public function __construct(
        private NotionClient $notion,
        private SocialPostFetcher $fetcher,
    ) {}

    /**
     * 能合併的合併，回傳還留著的重複列要寫回的狀態與提示名稱。
     * 重複要跟所有列比，包括這輪沒改過、跳過的。
     *
     * @param  Collection<string, array>  $rows  這輪讀到的清單；合併後留下那列的新值會寫回這裡、移走的列會拿掉，後面寫回時才比得對
     * @param  array<string, true>  $unsaved  這輪存檔失敗的列，資料庫裡的網址可能還是上一篇
     * @return array<string, array{SocialSyncStatus, string}> 重複列的 notion_page_id => [同步狀態, 提示名稱]
     */
    public function merge(Collection $rows, array $unsaved): array
    {
        foreach ($this->duplicateGroups($rows) as [$survivor, $duplicates]) {
            $this->mergeGroup($survivor, $duplicates, $rows, $unsaved);
        }

        $marks = [];
        foreach ($this->duplicateGroups($rows) as [$survivor, $duplicates]) {
            foreach ($duplicates as $duplicate) {
                $marks[$duplicate->notion_page_id] = $this->duplicateOf($rows[$duplicate->notion_page_id], $rows[$survivor->notion_page_id], $survivor);
            }
        }

        return $marks;
    }

    /**
     * 重複那列要寫回的狀態與名稱。這列有主列沒有的貨號時另外標出來，免得照「重複」刪掉後那幾個商品頁少了這篇。
     *
     * @return array{SocialSyncStatus, string}
     */
    private function duplicateOf(array $row, array $primaryRow, SocialPost $primary): array
    {
        $primaryCodes = array_map(fn (array $code) => strtoupper($code['code']), $primaryRow['codes']);
        $extra = array_column(array_filter($row['codes'], fn (array $code) => ! in_array(strtoupper($code['code']), $primaryCodes, true)), 'code');
        $name = SocialPostRow::DUPLICATE_HINT.($primaryRow['name'] ?: $primary->canonical_url);

        return $extra
            ? [SocialSyncStatus::DuplicateWithExtraCodes, $name.'，這列多了 '.implode('、', $extra)]
            : [SocialSyncStatus::Duplicate, $name];
    }

    /**
     * 同一篇貼文出現在不只一列時，挑一列留下：網站上顯示中的優先，其次是 Notion 上最早建立的，
     * 通常是整理過名稱與排序的那列。跟網站上的排序（新的在前）刻意不同，合併後只剩一列，兩邊自然一致。
     *
     * 只看這輪讀到的列：同步途中另一輪剛存進來的列不在清單上，這輪不碰，下一輪再處理。
     *
     * @param  Collection<string, array>  $rows
     * @return array<int, array{SocialPost, Collection<int, SocialPost>}> 每組的留下那列與重複的那幾列
     */
    private function duplicateGroups(Collection $rows): array
    {
        return SocialPost::whereNotNull('canonical_url')
            ->orderByRaw('status = ? desc', [SocialPostStatus::Ok->value])
            ->orderBy('notion_created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (SocialPost $post) => $rows->has($post->notion_page_id))
            ->groupBy('canonical_url')
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(fn (Collection $group) => [$group->first(), $group->slice(1)->values()])
            ->values()
            ->all();
    }

    /**
     * 重複那幾列多出來的貨號、留下那列空著的名稱與排序，補進留下那列，寫成功了才把重複那幾列移到垃圾桶。
     * 只要有一點不確定就不刪，留著標「重複」給人處理：網址不是這輪確定的、清單讀完之後又被改過、
     * 重複那列有自己的內容，或 Notion 存下的跟送出的不一樣。
     *
     * @param  Collection<int, SocialPost>  $duplicates
     */
    private function mergeGroup(SocialPost $survivor, Collection $duplicates, Collection $rows, array $unsaved): void
    {
        $settled = now()->subMinutes(self::MERGE_AFTER_MINUTES);
        if ($duplicates->concat([$survivor])->contains(fn (SocialPost $post) => $rows[$post->notion_page_id]['notion_created_at']->isAfter($settled))) {
            return;
        }

        // Notion 上的網址要是上次抓的那個、或我們寫回去的正式網址，而且這輪有存進去，才確定存的正式網址就是這列現在指的那篇
        $certain = fn (SocialPost $post) => $post->canonical_url !== null && ! isset($unsaved[$post->notion_page_id])
            && ! $post->urlReplacedBy($rows[$post->notion_page_id]['url']);
        if (! $certain($survivor)) {
            return;
        }

        // 先做不用連網的檢查，有列單獨跟留下那列合得來，才去 Notion 重讀頁面與內文。
        // 這裡一列一列各自比，不能依序累積：前面那列之後可能因為頁面有內文被留下，它的值不該擋到後面的
        $row = $rows[$survivor->notion_page_id];
        $candidates = $duplicates->filter($certain)
            ->filter(fn (SocialPost $duplicate) => $this->mergedRow($row, $rows[$duplicate->notion_page_id]) !== null)
            ->values();
        if ($candidates->isEmpty() || ! $this->unchangedSinceRead($candidates->concat([$survivor]), $rows)) {
            return;
        }

        // 頁面沒有內文、可以刪的那幾列，才依序併進來
        [$merged, $trash] = $this->foldDuplicates($row, $candidates->reject(
            fn (SocialPost $duplicate) => $this->hasPageContent($duplicate->notion_page_id, $survivor->canonical_url),
        ), $rows);

        $properties = array_filter([
            '商品編號' => $merged['codes_text'] === $row['codes_text'] ? null : ['rich_text' => [['text' => ['content' => $merged['codes_text']]]]],
            '名稱' => $merged['name'] === $row['name'] ? null : ['title' => $merged['name'] === '' ? [] : [['text' => ['content' => $merged['name']]]]],
            '排序' => $merged['sort'] === $row['sort'] ? null : ['number' => $merged['sort']],
        ]);

        if ($trash && $properties) {
            try {
                $page = $this->notion->updatePage($survivor->notion_page_id, $properties);
                $stored = SocialPostRow::fromPage($page);
            } catch (\Throwable $e) {
                Log::error('Social post merge failed', ['notion_page_id' => $survivor->notion_page_id, 'exception' => $e->getMessage()]);

                return;
            }

            // 存下的要跟送出的一樣，而且這段時間網址沒被改、留下那列也沒被移走
            if (($page['in_trash'] ?? false) || Arr::only($stored, ['url', 'codes_text', 'name', 'sort']) !== Arr::only($merged, ['url', 'codes_text', 'name', 'sort'])) {
                Log::error('Social post merge not stored as sent', ['notion_page_id' => $survivor->notion_page_id]);

                return;
            }

            // 貨號直接用合併後的存，不等下一輪重抓，商品頁這一輪結束就看得到併過來的貨號
            $survivor->fill(['notion_edited_at' => $stored['notion_edited_at'], 'sort' => $stored['sort']]);
            $survivor->saveWithCodes($stored['codes']);
            $rows->put($survivor->notion_page_id, $stored);
        }

        foreach ($trash as $duplicate) {
            try {
                $this->notion->trashPage($duplicate->notion_page_id);
            } catch (\Throwable $e) {
                Log::error('Social post trash failed', ['notion_page_id' => $duplicate->notion_page_id, 'exception' => $e->getMessage()]);

                continue;
            }

            $duplicate->delete();
            $rows->forget($duplicate->notion_page_id);
        }
    }

    /**
     * 依序把重複那幾列併進留下那列，跟已經併進來的值衝突的跳過。
     *
     * @param  iterable<int, SocialPost>  $duplicates
     * @return array{array, array<int, SocialPost>} 合併後的留下那列，與併進來的重複列
     */
    private function foldDuplicates(array $row, iterable $duplicates, Collection $rows): array
    {
        $folded = [];
        foreach ($duplicates as $duplicate) {
            $next = $this->mergedRow($row, $rows[$duplicate->notion_page_id]);
            if ($next !== null) {
                $row = $next;
                $folded[] = $duplicate;
            }
        }

        return [$row, $folded];
    }

    /** 動手前重讀每一列：清單讀完之後被改過、移走或讀不到，這輪就整組不動 */
    private function unchangedSinceRead(Collection $posts, Collection $rows): bool
    {
        foreach ($posts as $post) {
            try {
                $page = $this->notion->getPage($post->notion_page_id);
            } catch (\Throwable $e) {
                Log::error('Social post reread failed', ['notion_page_id' => $post->notion_page_id, 'exception' => $e->getMessage()]);

                return false;
            }

            $row = $rows[$post->notion_page_id];
            $fresh = SocialPostRow::fromPage($page);
            if (($page['in_trash'] ?? false) || ($page['archived'] ?? false) || ! $fresh['notion_edited_at']->equalTo($row['notion_edited_at'])
                || Arr::only($fresh, ['url', 'codes_text', 'name', 'sort']) !== Arr::only($row, ['url', 'codes_text', 'name', 'sort'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 頁面內文有使用者寫的東西就當作有自己的內容。從手機分享進 Notion 時內文會自動放一個貼文網址的嵌入，
     * 只有這種、而且都指向同一篇的不算；其他區塊、別篇、追不到、讀不到或內容太多，寧可不刪。
     */
    private function hasPageContent(string $pageId, string $canonical): bool
    {
        try {
            $blocks = $this->notion->pageBlocks($pageId, self::PAGE_BLOCK_LIMIT);
            if ($blocks['has_more']) {
                return true;
            }

            foreach ($blocks['results'] as $block) {
                $data = $block[$block['type']];
                $url = $data['url'] ?? null;
                // 嵌入或書籤底下寫了說明，就是使用者自己的內容
                $caption = trim(collect($data['caption'] ?? [])->pluck('plain_text')->implode(''));
                if (! in_array($block['type'], self::LINK_BLOCKS, true) || $caption !== '' || ! is_string($url)
                    || $this->fetcher->canonicalUrl($url) !== $canonical) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('Social post content check failed', ['notion_page_id' => $pageId, 'exception' => $e->getMessage()]);

            return true;
        }
    }

    /**
     * 把一列重複的併進來；重複那列有自己的內容、刪了會弄丟時回 null。
     * 留下那列的「商品編號」原文一字不改，多出來的貨號照重複那列的寫法接在後面，所以同一件的時間不一樣、
     * 或只有重複那列寫了時間，都算重複那列自己的內容。名稱與排序只補空白的；我們寫的「重複：…」提示不算名稱。
     */
    private function mergedRow(array $row, array $duplicate): ?array
    {
        if ($duplicate['unknown_codes']) {
            return null;
        }

        $kept = collect($row['codes'])->keyBy(fn (array $code) => strtoupper($code['code']));
        $added = [];
        foreach ($duplicate['code_tokens'] as $code) {
            $same = $kept->get(strtoupper($code['code']));
            if ($same === null) {
                $added[] = $code;
                $kept->put(strtoupper($code['code']), $code);
            } elseif ($code['start_seconds'] !== null && $code['start_seconds'] !== $same['start_seconds']) {
                return null;
            }
        }

        $name = SocialPostRow::isDuplicateHint($row['name']) ? '' : $row['name'];
        if ($duplicate['name'] !== '' && ! SocialPostRow::isDuplicateHint($duplicate['name']) && $duplicate['name'] !== $name) {
            if ($name !== '') {
                return null;
            }
            $name = $duplicate['name'];
        }

        if ($row['sort'] !== null && $duplicate['sort'] !== null && $row['sort'] !== $duplicate['sort']) {
            return null;
        }

        $tokens = implode(', ', array_column($added, 'token'));

        return array_merge($row, [
            'codes' => $kept->values()->all(),
            'codes_text' => $tokens === '' || $row['codes_text'] === '' ? $row['codes_text'].$tokens : "{$row['codes_text']}, {$tokens}",
            'name' => $name,
            'sort' => $row['sort'] ?? $duplicate['sort'],
        ]);
    }
}
