<?php

namespace App\Services;

use App\Enums\SocialPostStatus;
use App\Enums\SocialSyncStatus;
use App\Exceptions\SocialPostUnavailableException;
use App\Models\HmallProduct;
use App\Models\SocialPost;
use App\Models\SocialPostCode;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

class SocialPostService
{
    // 剛加進清單的列可能還在整理，一組裡每一列都放超過這麼久才合併
    private const MERGE_AFTER_MINUTES = 30;

    // 合併不了時寫在重複那列名稱的提示開頭；合併時不把這種名稱搬去留下的那列
    private const DUPLICATE_HINT = '重複：';

    // 判斷重複那列頁面內文時最多讀幾個區塊，讀不完就當作有內容
    private const PAGE_BLOCK_LIMIT = 10;

    // 只放貼文網址、不算使用者內容的區塊
    private const LINK_BLOCKS = ['embed', 'bookmark', 'link_preview'];

    // 「商品編號」裡的一個貨號，可接影片時間。長度跟資料表的 code 欄一致；只差大小寫的當同一件，資料庫比對本來就不分大小寫
    private const CODE_TOKEN = '/^([A-Za-z0-9-]{1,20})(?:[@＠](\d+(?::\d{1,2}){0,2}))?$/u';

    public function __construct(
        private NotionClient $notion,
        private SocialPostFetcher $fetcher,
    ) {}

    /**
     * @return Collection<int, SocialPost>
     */
    public function getForProduct(HmallProduct $hmallProduct): Collection
    {
        $codes = fn ($query) => $query->where('code', $hmallProduct->code);

        return SocialPost::visible()
            ->whereHas('codes', $codes)
            ->addSelect(['start_seconds' => SocialPostCode::select('start_seconds')
                ->whereColumn('social_post_id', 'social_posts.id')
                ->where('code', $hmallProduct->code)
                ->limit(1)])
            ->displayOrder()
            ->get()
            // 同一篇在 Notion 清單裡貼了不只一次，只留排在最前面的那則；沒有正式網址的認不出是哪篇，各自留著
            ->unique(fn (SocialPost $post) => $post->canonical_url ?? "id:{$post->id}")
            ->values();
    }

    /**
     * 把 Notion 清單同步進來。平常只處理新加或改過的；$full 時全部重抓，
     * 用來發現刪文、轉私人，並更新內文與預覽圖。處理完再一列一列把正式網址、名稱與同步狀態寫回 Notion。
     *
     * @return array{fetched: int, failed: int, skipped: int, removed: int}
     */
    public function sync(bool $full = false): array
    {
        $stats = ['fetched' => 0, 'failed' => 0, 'skipped' => 0, 'removed' => 0];
        $pages = $this->notion->queryAll((string) config('services.notion.social_data_source_id'));
        $existing = SocialPost::all()->keyBy('notion_page_id');
        $rows = collect($pages)->map(fn (array $page) => $this->parsePage($page))->keyBy('notion_page_id');

        $unsaved = [];
        foreach ($rows as $id => $row) {
            $post = $existing->get($id);

            // Notion 的編輯時間只到分鐘，同一分鐘內改了網址看起來像沒改，所以網址也要比
            if (! $full && $post && $post->notion_edited_at->equalTo($row['notion_edited_at'])
                && ! $this->urlReplaced($post, $row) && ! $this->coverExpiringSoon($post)) {
                $stats['skipped']++;

                continue;
            }

            // 整批重抓時一則一則隔開，不要一口氣打到平台被限流
            if ($full && $stats['fetched'] + $stats['failed'] > 0) {
                Sleep::for(2)->seconds();
            }

            // 一則存不進去只算這則失敗，後面的頁面與移除照樣處理
            try {
                $this->syncPage($row, $post) ? $stats['fetched']++ : $stats['failed']++;
            } catch (\Throwable $e) {
                Log::error('Social post save failed', [
                    'notion_page_id' => $row['notion_page_id'],
                    'exception' => $e->getMessage(),
                ]);
                $stats['failed']++;
                $unsaved[$id] = true;
            }
        }

        $stats['removed'] = SocialPost::whereIn('notion_page_id', $existing->keys()->diff($rows->keys()))->delete();

        // 重複要跟所有列比，包括這次沒改過、跳過的。能合併的併進留下的那列、重複那列移到垃圾桶；
        // 合併不了的標出來。不重複的寫回上次抓到的結果，寫回失敗的下一輪自然補送
        foreach ($this->duplicateGroups() as [$survivor, $duplicates]) {
            $this->mergeDuplicates($survivor, $duplicates, $rows, $unsaved);
        }

        $primaries = [];
        foreach ($this->duplicateGroups() as [$survivor, $duplicates]) {
            foreach ($duplicates as $duplicate) {
                $primaries[$duplicate->notion_page_id] = $survivor;
            }
        }

        $posts = SocialPost::all()->keyBy('notion_page_id');
        foreach ($rows as $id => $row) {
            if (! $post = $posts->get($id)) {
                continue;
            }

            if ($primary = $primaries[$id] ?? null) {
                [$status, $name] = $this->duplicateOf($row, $rows[$primary->notion_page_id], $primary);
                $this->writeBack($post, $row, $status, $name);
            } else {
                $this->writeBack($post, $row, $post->sync_status);
            }
        }

        return $stats;
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
        $name = self::DUPLICATE_HINT.($primaryRow['name'] ?: $primary->canonical_url);

        return $extra
            ? [SocialSyncStatus::DuplicateWithExtraCodes, $name.'，這列多了 '.implode('、', $extra)]
            : [SocialSyncStatus::Duplicate, $name];
    }

    /**
     * 同一篇貼文出現在不只一列時，挑一列留下：網站上顯示中的優先，其次是 Notion 上最早建立的，
     * 通常是整理過名稱與排序的那列。跟網站上的排序（新的在前）刻意不同，合併後只剩一列，兩邊自然一致。
     *
     * @return array<int, array{SocialPost, Collection<int, SocialPost>}> 每組的留下那列與重複的那幾列
     */
    private function duplicateGroups(): array
    {
        return SocialPost::whereNotNull('canonical_url')
            ->orderByRaw('status = ? desc', [SocialPostStatus::Ok->value])
            ->orderBy('notion_created_at')
            ->orderBy('id')
            ->get()
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
     * @param  Collection<string, array>  $rows  合併後的值會寫回這裡，後面寫回同步狀態時才比得對
     * @param  array<string, true>  $unsaved  這輪存檔失敗的列，資料庫裡的網址可能還是上一篇
     */
    private function mergeDuplicates(SocialPost $survivor, Collection $duplicates, Collection $rows, array $unsaved): void
    {
        $settled = now()->subMinutes(self::MERGE_AFTER_MINUTES);
        if ($duplicates->concat([$survivor])->contains(fn (SocialPost $post) => $rows[$post->notion_page_id]['notion_created_at']->isAfter($settled))) {
            return;
        }

        // Notion 上的網址要是上次抓的那個、或我們寫回去的正式網址，而且這輪有存進去，才確定存的正式網址就是這列現在指的那篇
        $certain = fn (SocialPost $post) => $post->canonical_url !== null && ! isset($unsaved[$post->notion_page_id])
            && ! $this->urlReplaced($post, $rows[$post->notion_page_id]);
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
                $stored = $this->parsePage($page);
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
            DB::transaction(fn () => $this->savePost($survivor, $stored['codes']));
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
            $fresh = $this->parsePage($page);
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

        $isHint = fn (string $name) => str_starts_with($name, self::DUPLICATE_HINT);
        $name = $isHint($row['name']) ? '' : $row['name'];
        if ($duplicate['name'] !== '' && ! $isHint($duplicate['name']) && $duplicate['name'] !== $name) {
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

    /** Notion 上的網址既不是上次抓的、也不是寫回去的正式網址，就是改填了別篇 */
    private function urlReplaced(SocialPost $post, array $row): bool
    {
        $url = $row['url'] ?? '';

        return $url !== $post->source_url && $url !== $post->canonical_url;
    }

    /** 預覽圖網址幾小時內就失效的，平常的同步也要重抓，不等整批重抓那一次 */
    private function coverExpiringSoon(SocialPost $post): bool
    {
        return $post->status === SocialPostStatus::Ok
            && $post->cover_expires_at !== null
            && $post->cover_expires_at->isBefore(now()->addHours(6));
    }

    /**
     * 抓到可以顯示就回 true。抓取結果記在 sync_status，寫回 Notion 用；暫時的失敗不改它。
     */
    private function syncPage(array $row, ?SocialPost $post): bool
    {
        $post ??= new SocialPost(['notion_page_id' => $row['notion_page_id']]);
        // 這列改填了別篇：原本存的正式網址與內容是上一篇的，不能拿來寫回 Notion，也不能因為暫時抓不到就繼續顯示
        $replaced = $post->exists && $this->urlReplaced($post, $row);
        if ($replaced) {
            $post->canonical_url = null;
        }
        $post->fill([
            'notion_created_at' => $row['notion_created_at'],
            'notion_edited_at' => $row['notion_edited_at'],
            'sort' => $row['sort'],
            'source_url' => $row['url'] ?? '',
        ]);

        $noPreview = false;

        try {
            if (! $row['url']) {
                throw new \InvalidArgumentException('沒有網址');
            }

            // 分享短網址先換成正式網址；後面抓不到也記下來，Notion 上的網址照樣換掉，再貼同一篇也比對得出重複
            $post->canonical_url = $this->fetcher->canonicalUrl($row['url']);
            $data = $this->fetcher->fetch($post->canonical_url);

            // 原本有內容、重抓時作者、內文、預覽圖卻全空，多半是被導去登入頁，不拿空的蓋掉原本存好的
            $hasPreview = fn (array $values) => filled($values['author']) || filled($values['text']) || filled($values['cover_url']);
            if ($post->exists && ! $replaced && $hasPreview($post->only(['author', 'text', 'cover_url'])) && ! $hasPreview($data)) {
                $noPreview = true;

                throw new \RuntimeException('頁面沒有預覽資料');
            }

            $post->fill($data);
            $post->status = SocialPostStatus::Ok;
            $post->sync_status = $post->embeddable ? SocialSyncStatus::Ok : SocialSyncStatus::NotEmbeddable;
            $post->fetched_at = now();
        } catch (\Throwable $e) {
            Log::error('Social post fetch failed', [
                'notion_page_id' => $row['notion_page_id'],
                'url' => $row['url'],
                'exception' => $e->getMessage(),
            ]);
            // 連不上或平台暫時擋下可能只是暫時的，原本正常顯示的就先留著，隔天整批重抓再判斷
            $temporary = $noPreview || SocialPostFetcher::isTemporaryFailure($e);
            if (! ($temporary && $post->exists && ! $replaced)) {
                $post->status = SocialPostStatus::Failed;
            }
            // 換了別篇時即使只是暫時的錯誤也已經下架，記成失敗讓 Notion 看得出來，下次抓到就會改掉
            if (! $temporary || $replaced) {
                $post->sync_status = $e instanceof SocialPostUnavailableException ? $e->reason : SocialSyncStatus::Failed;
            }

            DB::transaction(fn () => $this->savePost($post, $row['codes']));

            return false;
        }

        DB::transaction(fn () => $this->savePost($post, $row['codes']));

        return true;
    }

    private function savePost(SocialPost $post, array $codes): void
    {
        $post->save();
        $post->codes()->delete();
        $post->codes()->createMany(array_map(fn (array $code) => Arr::only($code, ['code', 'start_seconds']), $codes));
    }

    /**
     * 只補空白的名稱（我們寫的重複提示也算空白）與換掉非正式網址，不覆蓋人填的；同步狀態沒變就不送，資料庫沒有這欄就略過。
     * 正式網址要是從這列現在的網址算出來的才寫回，免得 Notion 剛改的網址被上一篇的蓋掉。
     * 寫回會改到 last_edited_time，要記下新的時間，下次才不會又重抓；跟 Notion 上一樣的值不送，免得每次都改到時間。
     */
    private function writeBack(SocialPost $post, array $row, ?SocialSyncStatus $status, ?string $duplicateName = null): void
    {
        $properties = [];

        if ($post->canonical_url && $post->source_url === $row['url'] && $post->canonical_url !== $row['url']) {
            $properties['網址'] = ['url' => $post->canonical_url];
        }

        // 我們寫的「重複：…」提示當作空白：還是重複就跟著主列更新，不重複了就換回自動名稱，沒有就清掉
        $hinted = str_starts_with($row['name'], self::DUPLICATE_HINT);
        $auto = $post->status === SocialPostStatus::Ok && filled($post->author)
            ? Str::limit($post->author.'：'.Str::of((string) $post->text)->squish(), 60)
            : null;
        $name = match (true) {
            $row['name'] !== '' && ! $hinted => null,
            $duplicateName !== null => $duplicateName,
            $hinted => $auto ?? '',
            default => $auto,
        };
        // 跟 Notion 上一樣就不送，免得每輪都改到編輯時間（例如作者就叫「重複」，自動名稱看起來像提示）
        if ($name !== null && $name !== $row['name']) {
            $properties['名稱'] = ['title' => $name === '' ? [] : [['text' => ['content' => $name]]]];
        }

        if ($row['has_sync_status'] && $status !== null && $status->value !== $row['sync_status']) {
            $properties['同步狀態'] = ['select' => ['name' => $status->value]];
        }

        if (! $properties) {
            return;
        }

        try {
            $page = $this->notion->updatePage($post->notion_page_id, $properties);
            $post->update(['notion_edited_at' => $this->notionTime($page['last_edited_time'])]);
        } catch (\Throwable $e) {
            Log::error('Social post write back failed', [
                'notion_page_id' => $post->notion_page_id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 「479123@1:23, 470001」：貨號後面可接影片講到這件的時間（分:秒、時:分:秒或秒數）。
     *
     * 原本的寫法也留著，合併重複列時照使用者的寫法接上去。
     *
     * @return array<int, array{code: string, start_seconds: ?int, token: string}>
     */
    private function parseCodes(string $value): array
    {
        return collect($this->parseCodeTokens($value))->unique(fn (array $code) => strtoupper($code['code']))->values()->all();
    }

    /**
     * 每個認得的貨號都列出來，同一件寫了幾次就有幾筆；合併前檢查重複那列有沒有自己的內容要全部看過。
     *
     * @return array<int, array{code: string, start_seconds: ?int, token: string}>
     */
    private function parseCodeTokens(string $value): array
    {
        $codes = [];

        foreach ($this->codeTokens($value) as $token) {
            if (preg_match(self::CODE_TOKEN, $token, $m)) {
                $seconds = isset($m[2]) ? array_reduce(explode(':', $m[2]), fn ($carry, $part) => $carry * 60 + (int) $part, 0) : null;
                $codes[] = ['code' => $m[1], 'start_seconds' => $seconds, 'token' => $token];
            }
        }

        return $codes;
    }

    /** @return array<int, string> */
    private function codeTokens(string $value): array
    {
        return preg_split('/[\s,，、]+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    /** Notion 給 UTC；Eloquent 存時間不換時區，不先換成網站設定的時區，讀回來會差 8 小時 */
    private function notionTime(string $time): Carbon
    {
        return Carbon::parse($time)->setTimezone(config('app.timezone'));
    }

    /**
     * @return array{notion_page_id: string, notion_created_at: Carbon, notion_edited_at: Carbon,
     *     name: string, url: ?string, codes: array<int, array{code: string, start_seconds: ?int, token: string}>,
     *     code_tokens: array<int, array{code: string, start_seconds: ?int, token: string}>, codes_text: string,
     *     unknown_codes: bool, sort: ?int,
     *     has_sync_status: bool, sync_status: ?string}
     */
    private function parsePage(array $page): array
    {
        $properties = $page['properties'];
        $text = fn (string $key) => collect($properties[$key][$properties[$key]['type'] ?? 'rich_text'] ?? [])
            ->pluck('plain_text')
            ->implode('');

        $sort = $properties['排序']['number'] ?? null;

        return [
            'notion_page_id' => $page['id'],
            'notion_created_at' => $this->notionTime($page['created_time']),
            'notion_edited_at' => $this->notionTime($page['last_edited_time']),
            'name' => trim($text('名稱')),
            'url' => $properties['網址']['url'] ?? null,
            'codes' => $this->parseCodes($text('商品編號')),
            'code_tokens' => $this->parseCodeTokens($text('商品編號')),
            // 原文留著，合併時只在後面接，不改使用者寫的；有認不得的文字就當作有自己的內容
            'codes_text' => $text('商品編號'),
            'unknown_codes' => collect($this->codeTokens($text('商品編號')))->contains(fn (string $token) => ! preg_match(self::CODE_TOKEN, $token)),
            'sort' => $sort === null ? null : (int) $sort,
            // 讀出目前的值，寫回前比對，一樣就不送
            'has_sync_status' => array_key_exists('同步狀態', $properties),
            'sync_status' => $properties['同步狀態']['select']['name'] ?? null,
        ];
    }
}
