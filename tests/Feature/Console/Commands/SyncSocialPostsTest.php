<?php

namespace Tests\Feature\Console\Commands;

use App\Models\SocialPost;
use App\Models\SocialPostCode;
use App\Services\SocialPostFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SyncSocialPostsTest extends TestCase
{
    use RefreshDatabase;

    private const DATA_SOURCE = 'ds-1';

    private const SHORT_URL = 'https://www.threads.com/share/BAXKotUx6Y/';

    private const CANONICAL_URL = 'https://www.threads.com/@geelou0406/post/DWMAZ2UgRQi';

    private int $fetchCount = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.notion.social_data_source_id', self::DATA_SOURCE);
    }

    private function page(string $id, array $overrides = []): array
    {
        $o = array_merge([
            'name' => '',
            'url' => self::SHORT_URL,
            'codes' => '479123, 470001',
            'sort' => null,
            'edited' => '2026-10-18T01:00:00.000Z',
            'created' => '2026-10-17T17:00:00.000Z',
        ], $overrides);

        return [
            'id' => $id,
            'created_time' => $o['created'],
            'last_edited_time' => $o['edited'],
            'properties' => [
                '名稱' => ['type' => 'title', 'title' => $o['name'] === '' ? [] : [['plain_text' => $o['name']]]],
                '網址' => ['type' => 'url', 'url' => $o['url']],
                '商品編號' => ['type' => 'rich_text', 'rich_text' => [['plain_text' => $o['codes']]]],
                '排序' => ['type' => 'number', 'number' => $o['sort']],
            ],
        ];
    }

    private function fakeNotion(array $pages): void
    {
        Http::fake([
            'api.notion.com/v1/data_sources/*' => Http::response(['results' => $pages, 'has_more' => false, 'next_cursor' => null]),
            'api.notion.com/v1/pages/*' => Http::response(['last_edited_time' => '2026-10-18T02:00:00.000Z']),
        ]);
    }

    private function fakeFetcher(?callable $fetch = null): void
    {
        $fetch ??= fn (string $url) => [
            'platform' => 'threads',
            'kind' => '貼文',
            'canonical_url' => self::CANONICAL_URL,
            'author' => 'geelo ü (@geelou0406)',
            'text' => "今天穿這件\n真的很好穿",
            'youtube_id' => null,
            'cover_url' => 'https://scontent.cdninstagram.com/p.jpg',
            'cover_expires_at' => null,
        ];

        $this->app->instance(SocialPostFetcher::class, new class($fetch, $this->fetchCount) extends SocialPostFetcher
        {
            public function __construct(private $fetchUsing, private int &$count) {}

            public function fetch(string $url): array
            {
                $this->count++;

                return ($this->fetchUsing)($url);
            }
        });
    }

    private function notionUpdates(): array
    {
        return Http::recorded(fn (Request $request) => $request->method() === 'PATCH')
            ->map(fn ($pair) => $pair[0]->data()['properties'])
            ->values()
            ->all();
    }

    public function test_new_page_is_stored_with_codes_and_written_back(): void
    {
        $this->fakeNotion([$this->page('page-1')]);
        $this->fakeFetcher();

        $this->artisan('social-post:sync')->assertExitCode(0);

        $post = SocialPost::with('codes')->sole();
        $this->assertSame('ok', $post->status);
        $this->assertEqualsCanonicalizing(['479123', '470001'], $post->codes->pluck('code')->all());
        // 預覽圖只記網址，不下載；顯示時經自己的網址轉送
        $this->assertSame('https://scontent.cdninstagram.com/p.jpg', $post->cover_url);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'cdninstagram.com'));

        $this->assertSame([[
            '網址' => ['url' => self::CANONICAL_URL],
            '名稱' => ['title' => [['text' => ['content' => 'geelo ü (@geelou0406)：今天穿這件 真的很好穿']]]],
        ]], $this->notionUpdates());
        $this->assertTrue($post->notion_edited_at->equalTo('2026-10-18T02:00:00Z'));
    }

    public function test_codes_can_carry_the_time_the_video_talks_about_them(): void
    {
        $this->fakeNotion([$this->page('page-1', ['codes' => '479123@1:23, 470001＠1:02:03、999999@75 888888'])]);
        $this->fakeFetcher();

        $this->artisan('social-post:sync');

        $this->assertEquals(
            ['470001' => 3723, '479123' => 83, '888888' => null, '999999' => 75],
            SocialPost::sole()->codes->pluck('start_seconds', 'code')->sortKeys()->all(),
        );
    }

    public function test_write_back_leaves_what_jack_already_filled_alone(): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的備註', 'url' => self::CANONICAL_URL])]);
        $this->fakeFetcher();

        $this->artisan('social-post:sync');

        $this->assertSame([], $this->notionUpdates());
    }

    public function test_unchanged_pages_are_skipped_unless_full(): void
    {
        $page = $this->page('page-1', ['name' => '我的備註', 'url' => self::CANONICAL_URL]);
        $this->fakeNotion([$page]);
        $this->fakeFetcher();

        $this->artisan('social-post:sync');
        $this->artisan('social-post:sync');
        $this->assertSame(1, $this->fetchCount);

        $this->artisan('social-post:sync --full');
        $this->assertSame(2, $this->fetchCount);
    }

    public function test_preview_images_close_to_expiring_are_refetched_without_waiting_for_the_daily_run(): void
    {
        $page = $this->page('page-1', ['name' => '我的備註', 'url' => self::CANONICAL_URL]);
        $this->fakeNotion([$page]);
        $expiresAt = now()->addHours(30);
        $this->fakeFetcher(fn () => [
            'platform' => 'threads', 'kind' => '貼文', 'canonical_url' => self::CANONICAL_URL,
            'author' => 'a', 'text' => 't', 'youtube_id' => null,
            'cover_url' => 'https://scontent.cdninstagram.com/p.jpg', 'cover_expires_at' => $expiresAt,
        ]);

        $this->artisan('social-post:sync');
        $this->artisan('social-post:sync');
        $this->assertSame(1, $this->fetchCount);

        $this->travel(26)->hours();
        $this->artisan('social-post:sync');
        $this->assertSame(2, $this->fetchCount);
    }

    public function test_failed_fetch_hides_the_post(): void
    {
        $this->fakeNotion([$this->page('page-1')]);
        $this->fakeFetcher(fn () => throw new RuntimeException('貼文已刪除'));

        $this->artisan('social-post:sync')->assertExitCode(0);

        $this->assertSame('failed', SocialPost::sole()->status);
        $this->assertSame(0, SocialPost::visible()->count());
        $this->assertSame([], $this->notionUpdates());
    }

    public function test_pages_removed_from_notion_are_removed_with_their_codes(): void
    {
        $this->fakeNotion([$this->page('page-1')]);
        $this->fakeFetcher();
        $this->artisan('social-post:sync');

        Http::swap(new \Illuminate\Http\Client\Factory);
        $this->fakeNotion([]);
        $this->artisan('social-post:sync');

        $this->assertSame(0, SocialPost::count());
        $this->assertSame(0, SocialPostCode::count());
    }
}
