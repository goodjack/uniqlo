<?php

namespace App\Services;

use App\Enums\SocialPostStatus;
use App\Enums\SocialSyncStatus;
use App\Exceptions\SocialPostUnavailableException;
use App\Models\HmallProduct;
use App\Models\SocialPost;
use App\Models\SocialPostCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

class SocialPostService
{
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
            }
        }

        $stats['removed'] = SocialPost::whereIn('notion_page_id', $existing->keys()->diff($rows->keys()))->delete();

        // 重複要跟所有列比，包括這次沒改過、跳過的；不重複的寫回上次抓到的結果，寫回失敗的下一輪自然補送
        $primaries = $this->duplicatePrimaries();
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
        $name = '重複：'.($primaryRow['name'] ?: $primary->canonical_url);

        return $extra
            ? [SocialSyncStatus::DuplicateWithExtraCodes, $name.'，這列多了 '.implode('、', $extra)]
            : [SocialSyncStatus::Duplicate, $name];
    }

    /**
     * 同一篇貼文出現在不只一列時，每一列重複的對到它那組的主列。主列是網站上會顯示的那列：
     * 顯示中的優先，其次照網站的排序。
     *
     * @return array<string, SocialPost> 重複那列的 notion_page_id => 主列
     */
    private function duplicatePrimaries(): array
    {
        $primaries = [];
        $groups = SocialPost::whereNotNull('canonical_url')
            ->orderByRaw('status = ? desc', [SocialPostStatus::Ok->value])
            ->displayOrder()
            ->orderBy('id')
            ->get()
            ->groupBy('canonical_url');

        foreach ($groups as $group) {
            foreach ($group->slice(1) as $duplicate) {
                $primaries[$duplicate->notion_page_id] = $group->first();
            }
        }

        return $primaries;
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
        $post->codes()->createMany($codes);
    }

    /**
     * 只補空白的名稱與換掉非正式網址，不覆蓋人填的；同步狀態沒變就不送，資料庫沒有這欄就略過。
     * 正式網址要是從這列現在的網址算出來的才寫回，免得 Notion 剛改的網址被上一篇的蓋掉。
     * 寫回會改到 last_edited_time，要記下新的時間，下次才不會又重抓；跟 Notion 上一樣的值不送，免得每次都改到時間。
     */
    private function writeBack(SocialPost $post, array $row, ?SocialSyncStatus $status, ?string $duplicateName = null): void
    {
        $properties = [];

        if ($post->canonical_url && $post->source_url === $row['url'] && $post->canonical_url !== $row['url']) {
            $properties['網址'] = ['url' => $post->canonical_url];
        }

        $name = match (true) {
            $row['name'] !== '' => null,
            $duplicateName !== null => $duplicateName,
            $post->status === SocialPostStatus::Ok && filled($post->author) => Str::limit($post->author.'：'.Str::of((string) $post->text)->squish(), 60),
            default => null,
        };
        if ($name !== null) {
            $properties['名稱'] = ['title' => [['text' => ['content' => $name]]]];
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
     * @return array<int, array{code: string, start_seconds: ?int}>
     */
    private function parseCodes(string $value): array
    {
        $codes = [];

        foreach (preg_split('/[\s,，、]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            // 長度跟資料表的 code 欄一致；只差大小寫的當同一件，資料庫比對本來就不分大小寫
            if (! preg_match('/^([A-Za-z0-9-]{1,20})(?:[@＠](\d+(?::\d{1,2}){0,2}))?$/u', $token, $m)) {
                continue;
            }

            $seconds = isset($m[2]) ? array_reduce(explode(':', $m[2]), fn ($carry, $part) => $carry * 60 + (int) $part, 0) : null;
            $codes[strtoupper($m[1])] ??= ['code' => $m[1], 'start_seconds' => $seconds];
        }

        return array_values($codes);
    }

    /** Notion 給 UTC；Eloquent 存時間不換時區，不先換成網站設定的時區，讀回來會差 8 小時 */
    private function notionTime(string $time): Carbon
    {
        return Carbon::parse($time)->setTimezone(config('app.timezone'));
    }

    /**
     * @return array{notion_page_id: string, notion_created_at: Carbon, notion_edited_at: Carbon,
     *     name: string, url: ?string, codes: array<int, array{code: string, start_seconds: ?int}>, sort: ?int,
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
            'sort' => $sort === null ? null : (int) $sort,
            // 讀出目前的值，寫回前比對，一樣就不送
            'has_sync_status' => array_key_exists('同步狀態', $properties),
            'sync_status' => $properties['同步狀態']['select']['name'] ?? null,
        ];
    }
}
