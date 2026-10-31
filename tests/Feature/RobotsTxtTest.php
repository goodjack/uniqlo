<?php

namespace Tests\Feature;

use App\Models\HmallProduct;
use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * robots.txt 是白名單：頁面要用的樣式、程式、圖示沒放行，Google 畫出來的頁面就沒有排版。
 */
class RobotsTxtTest extends TestCase
{
    use RefreshDatabase;

    public function test_googlebot_can_fetch_every_local_asset_the_product_page_uses(): void
    {
        HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => 'UNIQLO', 'name' => '短袖上衣', 'product_name' => '短袖上衣', 'code' => '990001',
            'product_code' => 'u990001', 'sex' => '女裝', 'gender' => '女裝', 'identity' => '[]', 'stock' => 'Y',
            'min_price' => 990, 'lowest_record_price' => 990, 'highest_record_price' => 990,
        ]));
        // 社群熱推的預覽圖經本站網址轉送，也要放行
        $post = SocialPost::create([
            'notion_page_id' => 'page-1', 'notion_created_at' => now(), 'notion_edited_at' => now(),
            'source_url' => 'https://www.threads.com/@a/post/1', 'canonical_url' => 'https://www.threads.com/@a/post/1',
            'platform' => 'threads', 'kind' => '貼文', 'cover_url' => 'https://scontent.cdninstagram.com/p.jpg', 'status' => 'ok',
        ]);
        $post->codes()->create(['code' => '990001']);
        $html = $this->get(route('uniqlo-hmall-products.show', ['uniqlo_product_code' => 'u990001']))->assertOk()->getContent();

        // 畫頁面用得到的標籤都算；manifest 只給安裝成 App 用，canonical 之類是指向頁面，不算
        preg_match_all('#<(?:script|img|link)\b[^>]*>#i', $html, $tags);
        $paths = [];
        foreach ($tags[0] as $tag) {
            if (preg_match('#\brel="(?:manifest|canonical|alternate|prev|next)"#i', $tag)) {
                continue;
            }
            if (preg_match('#\b(?:href|src)="'.preg_quote(url('/'), '#').'(/[^"?]*)#', $tag, $m)) {
                $paths[] = $m[1];
            }
        }
        $this->assertContains('/css/app.css', $paths);
        $this->assertContains("/social-img/{$post->id}.jpg", $paths);

        foreach (array_unique($paths) as $path) {
            $this->assertTrue($this->googlebotAllows($path), $path);
        }
    }

    public function test_googlebot_can_read_the_favorites_page_noindex_but_not_its_data_endpoint(): void
    {
        $this->assertTrue($this->googlebotAllows('/favorites'));
        $this->assertFalse($this->googlebotAllows('/favorites/cards'));
    }

    /**
     * Google 的判法：Googlebot 那一組裡最長的符合規則勝出，一樣長時 Allow 勝。
     */
    private function googlebotAllows(string $path): bool
    {
        $best = ['length' => -1, 'allow' => true];
        $inGooglebotGroup = false;
        $groupHasRules = false;

        foreach (file(public_path('robots.txt'), FILE_IGNORE_NEW_LINES) as $line) {
            if (! preg_match('/^\s*(user-agent|allow|disallow)\s*:\s*(\S*)/i', $line, $m)) {
                continue;
            }
            $field = strtolower($m[1]);

            if ($field === 'user-agent') {
                if ($groupHasRules) {
                    $inGooglebotGroup = false;
                    $groupHasRules = false;
                }
                $inGooglebotGroup = $inGooglebotGroup || strcasecmp($m[2], 'Googlebot') === 0;

                continue;
            }

            $groupHasRules = true;
            $regex = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($m[2], '#')).'#';

            if ($inGooglebotGroup && $m[2] !== '' && preg_match($regex, $path)) {
                $length = strlen($m[2]);
                if ($length > $best['length'] || ($length === $best['length'] && $field === 'allow')) {
                    $best = ['length' => $length, 'allow' => $field === 'allow'];
                }
            }
        }

        return $best['allow'];
    }
}
