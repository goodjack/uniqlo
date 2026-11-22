<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Models\HmallProduct;
use App\Models\SocialPost;
use App\Services\SocialPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListSocialPostCandidatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 只列有社群人氣話題標籤、在售、而且網站上還看不到任何一則貼文的；評論多的排前面。
     */
    public function test_it_lists_community_topics_that_have_no_visible_post_yet(): void
    {
        $this->createProduct('GU', '360889', ['HOT ITEM'], evaluationCount: 16);
        $this->createProduct('UNIQLO', '485562', ['COMMUNITY_TOPICS'], evaluationCount: 120);
        $this->createProduct('UNIQLO', '400001', ['COMMUNITY_TOPICS']);
        $this->createProduct('UNIQLO', '400002', ['COMMUNITY_TOPICS']);
        $this->createProduct('UNIQLO', '400003', ['COMMUNITY_TOPICS'], stockoutAt: now());
        $this->createProduct('UNIQLO', '400004', ['new_product']);

        // 已收錄、網站看得到：不用再找
        $this->createPost('400001', SocialPostStatus::Ok);
        // 收過但抓不到（刪文或轉私人）：網站上看不到，等於沒收
        $this->createPost('400002', SocialPostStatus::Failed);

        $candidates = app(SocialPostService::class)->getCommunityTopicsWithoutPosts();

        $this->assertSame(['485562', '360889', '400002'], $candidates->pluck('code')->all());

        $this->artisan('social-post:candidates')
            ->expectsOutputToContain('485562')
            ->expectsOutputToContain(route('gu-hmall-products.show', ['gu_product_code' => 'u360889']))
            ->expectsOutputToContain('共 3 件')
            ->assertSuccessful();
    }

    public function test_it_says_so_when_every_community_topic_already_has_a_post(): void
    {
        $this->createProduct('UNIQLO', '400001', ['COMMUNITY_TOPICS']);
        $this->createPost('400001', SocialPostStatus::Ok);

        $this->artisan('social-post:candidates')
            ->expectsOutputToContain('都已經有貼文')
            ->assertSuccessful();
    }

    private function createProduct(string $brand, string $code, array $identity, int $evaluationCount = 0, $stockoutAt = null): void
    {
        HmallProduct::unguarded(fn () => HmallProduct::create([
            'brand' => $brand,
            'name' => "商品 {$code}",
            'code' => $code,
            'product_code' => "u{$code}",
            'identity' => json_encode($identity),
            'stock' => 'Y',
            'stockout_at' => $stockoutAt,
            'evaluation_count' => $evaluationCount,
            'min_price' => 990,
            'lowest_record_price' => 990,
            'highest_record_price' => 990,
        ]));
    }

    private function createPost(string $code, SocialPostStatus $status): void
    {
        $post = SocialPost::create([
            'notion_page_id' => "page-{$code}",
            'notion_created_at' => now(),
            'notion_edited_at' => now(),
            'source_url' => "https://www.threads.com/@a/post/{$code}",
            'platform' => SocialPlatform::Threads,
            'status' => $status,
        ]);
        $post->codes()->create(['code' => $code]);
    }
}
