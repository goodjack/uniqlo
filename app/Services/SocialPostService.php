<?php

namespace App\Services;

use App\Enums\SocialPostStatus;
use App\Enums\SocialSyncStatus;
use App\Exceptions\SocialPostUnavailableException;
use App\Models\HmallProduct;
use App\Models\SocialPost;
use App\Models\SocialPostCode;
use App\Support\SocialPostRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

class SocialPostService
{
    public function __construct(
        private NotionClient $notion,
        private SocialPostFetcher $fetcher,
        private SocialPostDuplicateMerger $duplicates,
    ) {}

    /**
     * @return Collection<int, SocialPost>
     */
    public function getForProduct(HmallProduct $hmallProduct): Collection
    {
        $codes = fn ($query) => $query->where('code', $hmallProduct->code);

        $posts = SocialPost::visible()
            ->whereHas('codes', $codes)
            ->addSelect(['start_seconds' => SocialPostCode::select('start_seconds')
                ->whereColumn('social_post_id', 'social_posts.id')
                ->where('code', $hmallProduct->code)
                ->limit(1)])
            ->displayOrder()
            ->get();

        // 同一篇在 Notion 清單裡貼了不只一次（合併被擋下時），只放 Notion 上沒標重複的那列：
        // 跟 SocialPostDuplicateMerger 一樣挑最早建立的，再照網站排序擺。沒有正式網址的認不出是哪篇，各自留著
        $kept = $posts->groupBy(fn (SocialPost $post) => $post->canonical_url ?? "id:{$post->id}")
            ->map(fn (Collection $group) => $group->sortBy([['notion_created_at', 'asc'], ['id', 'asc']])->first()->id);

        return $posts->whereIn('id', $kept->values())->values();
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
        $rows = collect($pages)->map(fn (array $page) => SocialPostRow::fromPage($page))->keyBy('notion_page_id');

        $unsaved = [];
        foreach ($rows as $id => $row) {
            $post = $existing->get($id);

            // Notion 的編輯時間只到分鐘，同一分鐘內改了網址看起來像沒改，所以網址也要比
            if (! $full && $post && $post->notion_edited_at->equalTo($row['notion_edited_at'])
                && ! $post->urlReplacedBy($row['url']) && ! $this->coverExpiringSoon($post)) {
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

        // 能合併的重複列併進留下那列、移到垃圾桶，合併不了的標出來；
        // 不重複的寫回上次抓到的結果，寫回失敗的下一輪自然補送
        $marks = $this->duplicates->merge($rows, $unsaved);

        $posts = SocialPost::all()->keyBy('notion_page_id');
        foreach ($rows as $id => $row) {
            if ($post = $posts->get($id)) {
                [$status, $name] = $marks[$id] ?? [$post->sync_status, null];
                $this->writeBack($post, $row, $status, $name);
            }
        }

        return $stats;
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
        $replaced = $post->exists && $post->urlReplacedBy($row['url']);
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
            // 原本就有、也沒換成別篇的才沿用原狀態；新加的、或換了別篇的，就算只是暫時的錯誤也沒上網站，
            // 記成失敗讓 Notion 看得出來，下次抓到就會改掉
            $keepAsIs = ($noPreview || SocialPostFetcher::isTemporaryFailure($e)) && $post->exists && ! $replaced;
            if (! $keepAsIs) {
                $post->status = SocialPostStatus::Failed;
                $post->sync_status = $e instanceof SocialPostUnavailableException ? $e->reason : SocialSyncStatus::Failed;
            }

            $post->saveWithCodes($row['codes']);

            return false;
        }

        $post->saveWithCodes($row['codes']);

        return true;
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
        $hinted = SocialPostRow::isDuplicateHint($row['name']);
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
            $post->update(['notion_edited_at' => SocialPostRow::notionTime($page['last_edited_time'])]);
        } catch (\Throwable $e) {
            Log::error('Social post write back failed', [
                'notion_page_id' => $post->notion_page_id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
