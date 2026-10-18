<?php

namespace App\Services;

use App\Models\HmallProduct;
use App\Models\SocialPost;
use App\Models\SocialPostCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->get();
    }

    /**
     * 把 Notion 清單同步進來。平常只處理新加或改過的；$full 時全部重抓，
     * 用來發現刪文、轉私人，並更新內文與預覽圖。
     *
     * @return array{fetched: int, failed: int, skipped: int, removed: int}
     */
    public function sync(bool $full = false): array
    {
        $stats = ['fetched' => 0, 'failed' => 0, 'skipped' => 0, 'removed' => 0];
        $pages = $this->notion->queryAll((string) config('services.notion.social_data_source_id'));
        $existing = SocialPost::all()->keyBy('notion_page_id');

        foreach ($pages as $page) {
            $row = $this->parsePage($page);
            $post = $existing->get($row['notion_page_id']);

            if (! $full && $post && $post->notion_edited_at->equalTo($row['notion_edited_at']) && ! $this->coverExpiringSoon($post)) {
                $stats['skipped']++;

                continue;
            }

            $this->syncPage($row, $post) ? $stats['fetched']++ : $stats['failed']++;
        }

        $removed = $existing->keys()->diff(collect($pages)->pluck('id'));
        $stats['removed'] = SocialPost::whereIn('notion_page_id', $removed)->delete();

        return $stats;
    }

    /** 預覽圖網址幾小時內就失效的，平常的同步也要重抓，不等整批重抓那一次 */
    private function coverExpiringSoon(SocialPost $post): bool
    {
        return $post->cover_expires_at !== null && $post->cover_expires_at->isBefore(now()->addHours(6));
    }

    private function syncPage(array $row, ?SocialPost $post): bool
    {
        $post ??= new SocialPost(['notion_page_id' => $row['notion_page_id']]);
        $post->fill([
            'notion_created_at' => $row['notion_created_at'],
            'notion_edited_at' => $row['notion_edited_at'],
            'sort' => $row['sort'],
            'source_url' => $row['url'] ?? '',
        ]);

        try {
            if (! $row['url']) {
                throw new \InvalidArgumentException('沒有網址');
            }

            $data = $this->fetcher->fetch($row['url']);
            $post->fill($data);
            $post->status = 'ok';
            $post->fetched_at = now();
        } catch (\Throwable $e) {
            Log::error('Social post fetch failed', [
                'notion_page_id' => $row['notion_page_id'],
                'url' => $row['url'],
                'exception' => $e->getMessage(),
            ]);
            // 連不上可能只是暫時的，原本正常顯示的就先留著，隔天整批重抓再判斷
            if (! ($e instanceof ConnectionException && $post->exists)) {
                $post->status = 'failed';
            }

            DB::transaction(fn () => $this->savePost($post, $row['codes']));

            return false;
        }

        DB::transaction(fn () => $this->savePost($post, $row['codes']));
        $this->writeBack($post, $row);

        return true;
    }

    private function savePost(SocialPost $post, array $codes): void
    {
        $post->save();
        $post->codes()->delete();
        $post->codes()->createMany($codes);
    }

    /** 只補空白的名稱與換掉非正式網址，不覆蓋人填的。寫回會改到 last_edited_time，要記下新的時間，下次才不會又重抓 */
    private function writeBack(SocialPost $post, array $row): void
    {
        $properties = [];

        if ($post->canonical_url && $post->canonical_url !== $row['url']) {
            $properties['網址'] = ['url' => $post->canonical_url];
        }

        if ($row['name'] === '' && $post->author) {
            $name = Str::limit($post->author.'：'.Str::of((string) $post->text)->squish(), 60);
            $properties['名稱'] = ['title' => [['text' => ['content' => $name]]]];
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
            if (! preg_match('/^([A-Za-z0-9-]+)(?:[@＠](\d+(?::\d{1,2}){0,2}))?$/u', $token, $m)) {
                continue;
            }

            $seconds = isset($m[2]) ? array_reduce(explode(':', $m[2]), fn ($carry, $part) => $carry * 60 + (int) $part, 0) : null;
            $codes[$m[1]] ??= ['code' => $m[1], 'start_seconds' => $seconds];
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
     *     name: string, url: ?string, codes: array<int, array{code: string, start_seconds: ?int}>, sort: ?int}
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
        ];
    }
}
