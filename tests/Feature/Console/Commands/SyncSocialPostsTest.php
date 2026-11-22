<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Enums\SocialPostStatus;
use App\Enums\SocialSyncStatus;
use App\Exceptions\SocialPostUnavailableException;
use App\Models\SocialPost;
use App\Models\SocialPostCode;
use App\Services\SocialPostFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SyncSocialPostsTest extends TestCase
{
    use RefreshDatabase;

    private const DATA_SOURCE = 'ds-1';

    private const SHORT_URL = 'https://www.threads.com/share/AbCdEf1234/';

    private const CANONICAL_URL = 'https://www.threads.com/@meta/post/DDzbnVKx57R';

    private int $fetchCount = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
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
            // false 是資料庫還沒加「同步狀態」欄；null 是有這欄但還沒選
            'status' => false,
        ], $overrides);

        $properties = [
            '名稱' => ['type' => 'title', 'title' => $o['name'] === '' ? [] : [['plain_text' => $o['name']]]],
            '網址' => ['type' => 'url', 'url' => $o['url']],
            '商品編號' => ['type' => 'rich_text', 'rich_text' => [['plain_text' => $o['codes']]]],
            '排序' => ['type' => 'number', 'number' => $o['sort']],
        ];
        if ($o['status'] !== false) {
            $properties['同步狀態'] = ['type' => 'select', 'select' => $o['status'] === null ? null : ['name' => $o['status']]];
        }

        return [
            'id' => $id,
            'created_time' => $o['created'],
            'last_edited_time' => $o['edited'],
            'properties' => $properties,
        ];
    }

    private function fakeNotion(array $pages): void
    {
        Http::fake([
            'api.notion.com/v1/data_sources/*' => Http::response(['results' => $pages, 'has_more' => false, 'next_cursor' => null]),
            'api.notion.com/v1/pages/*' => Http::response(['last_edited_time' => '2026-10-18T02:00:00.000Z']),
        ]);
    }

    /** $canonical 沒給時網址原樣當正式網址，不追轉址 */
    private function fakeFetcher(?callable $fetch = null, ?callable $canonical = null): void
    {
        $canonical ??= fn (string $url) => $url;
        $fetch ??= fn (string $url) => [
            'platform' => SocialPlatform::Threads,
            'kind' => SocialPostKind::Post,
            'canonical_url' => self::CANONICAL_URL,
            'author' => 'Meta ü (@meta)',
            'text' => "今天穿這件\n真的很好穿",
            'youtube_id' => null,
            'cover_url' => 'https://scontent.cdninstagram.com/p.jpg',
            'cover_expires_at' => null,
        ];

        $this->app->instance(SocialPostFetcher::class, new class($fetch, $canonical, $this->fetchCount) extends SocialPostFetcher
        {
            public function __construct(private $fetchUsing, private $canonicalUsing, private int &$count) {}

            public function canonicalUrl(string $url): string
            {
                return ($this->canonicalUsing)($url);
            }

            public function fetch(string $url): array
            {
                $this->count++;

                return ($this->fetchUsing)($url);
            }
        });
    }

    /** 換一組新的假連線，前面假造的回應與紀錄都清掉，沒假造的請求照樣擋下 */
    private function resetHttp(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
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
        $this->assertSame(SocialPostStatus::Ok, $post->status);
        $this->assertEqualsCanonicalizing(['479123', '470001'], $post->codes->pluck('code')->all());
        // 預覽圖只記網址，不下載；顯示時經自己的網址轉送
        $this->assertSame('https://scontent.cdninstagram.com/p.jpg', $post->cover_url);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'cdninstagram.com'));

        $this->assertSame([[
            '網址' => ['url' => self::CANONICAL_URL],
            '名稱' => ['title' => [['text' => ['content' => 'Meta ü (@meta)：今天穿這件 真的很好穿']]]],
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
            'platform' => SocialPlatform::Threads, 'kind' => SocialPostKind::Post, 'canonical_url' => self::CANONICAL_URL,
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

        $this->assertSame(SocialPostStatus::Failed, SocialPost::sole()->status);
        $this->assertSame(0, SocialPost::visible()->count());
        $this->assertSame([], $this->notionUpdates());
    }

    public function test_pages_removed_from_notion_are_removed_with_their_codes(): void
    {
        $this->fakeNotion([$this->page('page-1')]);
        $this->fakeFetcher();
        $this->artisan('social-post:sync');

        $this->resetHttp();
        $this->fakeNotion([]);
        $this->artisan('social-post:sync');

        $this->assertSame(0, SocialPost::count());
        $this->assertSame(0, SocialPostCode::count());
    }

    private function fetched(array $overrides = []): array
    {
        return array_merge([
            'platform' => SocialPlatform::Threads, 'kind' => SocialPostKind::Post, 'canonical_url' => self::CANONICAL_URL,
            'author' => 'a', 'text' => 't', 'youtube_id' => null,
            'cover_url' => 'https://scontent.cdninstagram.com/p.jpg', 'cover_expires_at' => null, 'embeddable' => true,
        ], $overrides);
    }

    public function test_one_page_failing_to_save_does_not_stop_the_others_or_the_removals(): void
    {
        $this->fakeNotion([$this->page('page-0')]);
        $this->fakeFetcher(fn () => $this->fetched());
        $this->artisan('social-post:sync');

        $this->resetHttp();
        $this->fakeNotion([$this->page('page-1'), $this->page('page-2'), $this->page('page-3')]);
        $calls = 0;
        $this->fakeFetcher(function () use (&$calls) {
            // 第二則的作者超過欄位長度，存檔時資料庫會擋下
            return $this->fetched(['author' => ++$calls === 2 ? str_repeat('a', 300) : 'a']);
        });

        $this->artisan('social-post:sync')
            ->expectsOutput('社群熱推同步完成：抓取 2、失敗 1、沒變跳過 0、移除 1')
            ->assertExitCode(0);

        $this->assertEqualsCanonicalizing(['page-1', 'page-3'], SocialPost::pluck('notion_page_id')->all());
    }

    public function test_codes_longer_than_the_column_are_ignored_and_case_only_duplicates_are_merged(): void
    {
        $this->fakeNotion([$this->page('page-1', ['codes' => '479123, ABC-1, abc-1, 123456789012345678901'])]);
        $this->fakeFetcher(fn () => $this->fetched());

        $this->artisan('social-post:sync');

        $this->assertEqualsCanonicalizing(['479123', 'ABC-1'], SocialPost::sole()->codes->pluck('code')->all());
    }

    public function test_rate_limits_and_server_errors_keep_a_post_that_was_showing(): void
    {
        $this->fakeNotion([$this->page('page-1')]);
        $this->fakeFetcher(fn () => $this->fetched());
        $this->artisan('social-post:sync');

        foreach ([429, 503] as $status) {
            $this->fakeFetcher(fn () => throw new RequestException(new Response(new \GuzzleHttp\Psr7\Response($status))));
            $this->artisan('social-post:sync --full');

            $this->assertSame(SocialPostStatus::Ok, SocialPost::sole()->status, "HTTP {$status}");
        }
    }

    public function test_refetch_without_any_preview_data_keeps_what_was_stored(): void
    {
        $this->fakeNotion([$this->page('page-1')]);
        $this->fakeFetcher(fn () => $this->fetched(['author' => '原作者', 'text' => '原內文']));
        $this->artisan('social-post:sync');

        foreach ([null, ''] as $empty) {
            $this->fakeFetcher(fn () => $this->fetched(['author' => $empty, 'text' => $empty, 'cover_url' => $empty]));
            $this->artisan('social-post:sync --full');

            $post = SocialPost::sole();
            $this->assertSame(SocialPostStatus::Ok, $post->status);
            $this->assertSame('原作者', $post->author);
            $this->assertSame('原內文', $post->text);
        }
    }

    public function test_failed_posts_are_not_refetched_just_because_their_old_preview_is_expiring(): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的備註', 'url' => self::CANONICAL_URL])]);
        $this->fakeFetcher(fn () => $this->fetched(['cover_expires_at' => now()->addHours(30)]));
        $this->artisan('social-post:sync');
        SocialPost::sole()->update(['status' => SocialPostStatus::Failed]);

        $this->travel(26)->hours();
        $this->artisan('social-post:sync');

        $this->assertSame(1, $this->fetchCount);
    }

    public function test_threads_post_that_cannot_be_embedded_is_shown_from_its_page_preview(): void
    {
        $this->fakeNotion([$this->page('page-1', ['url' => self::CANONICAL_URL.'?hl=zh-tw'])]);
        Http::fake([
            'graph.threads.net/*' => Http::response(['error' => ['message' => 'The requested resource does not exist', 'code' => 24]], 400),
            'www.threads.com/*' => Http::response('<meta property="og:url" content="https://www.threads.com/&#064;meta/post/DDzbnVKx57R" />'
                .'<meta property="og:title" content="Meta (&#064;meta) on Threads" />'
                .'<meta property="og:description" content="範例內文" />'
                .'<meta property="og:image" content="https://scontent.cdninstagram.com/p.jpg" />'),
        ]);

        $this->artisan('social-post:sync')->assertExitCode(0);

        $post = SocialPost::sole();
        $this->assertSame(SocialPostStatus::Ok, $post->status);
        $this->assertFalse($post->embeddable);
        $this->assertSame([[
            '網址' => ['url' => self::CANONICAL_URL],
            '名稱' => ['title' => [['text' => ['content' => 'Meta (@meta)：範例內文']]]],
        ]], $this->notionUpdates());
    }

    public function test_post_that_becomes_embeddable_again_goes_back_to_the_platform_embed(): void
    {
        $this->fakeNotion([$this->page('page-1')]);
        $this->fakeFetcher(fn () => $this->fetched(['embeddable' => false]));
        $this->artisan('social-post:sync');
        $this->assertFalse(SocialPost::sole()->embeddable);

        $this->fakeFetcher(fn () => $this->fetched(['embeddable' => true]));
        $this->artisan('social-post:sync --full');

        $this->assertTrue(SocialPost::sole()->embeddable);
    }

    public function test_same_post_listed_twice_marks_the_later_row_as_a_duplicate(): void
    {
        $pages = [
            $this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'sort' => 1, 'status' => null]),
            $this->page('page-2', ['url' => self::SHORT_URL, 'codes' => '479123', 'status' => null]),
        ];
        $this->fakeNotion($pages);
        $this->fakeFetcher(fn () => $this->fetched(), fn (string $url) => self::CANONICAL_URL);

        $this->artisan('social-post:sync');

        $this->assertSame([
            ['同步狀態' => ['select' => ['name' => '正常']]],
            [
                '網址' => ['url' => self::CANONICAL_URL],
                '名稱' => ['title' => [['text' => ['content' => '重複：我的穿搭']]]],
                '同步狀態' => ['select' => ['name' => '重複']],
            ],
        ], $this->notionUpdates());
        // 網站上本來就只放一張，資料庫不用刪
        $this->assertSame(2, SocialPost::count());

        // Notion 上已經是寫回去的值，再同步一次不能又送一次，不然編輯時間一直變、每小時都重抓
        $this->resetHttp();
        $this->fakeNotion([
            $this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'sort' => 1, 'status' => '正常', 'edited' => '2026-10-18T02:00:00.000Z']),
            $this->page('page-2', ['name' => '重複：我的穿搭', 'url' => self::CANONICAL_URL, 'codes' => '479123', 'status' => '重複', 'edited' => '2026-10-18T02:00:00.000Z']),
        ]);
        $this->artisan('social-post:sync');
        $this->artisan('social-post:sync --full');

        $this->assertSame([], $this->notionUpdates());
    }

    public function test_duplicate_of_a_row_without_a_name_is_named_after_its_url_and_named_rows_are_left_alone(): void
    {
        $this->fakeNotion([
            $this->page('page-1', ['url' => self::CANONICAL_URL, 'sort' => 1, 'status' => null]),
            $this->page('page-2', ['name' => '人填的', 'url' => self::CANONICAL_URL, 'status' => null]),
            $this->page('page-3', ['url' => self::CANONICAL_URL, 'status' => null]),
        ]);
        // 主列抓不到作者，不會補名稱
        $this->fakeFetcher(fn () => $this->fetched(['author' => null]));

        $this->artisan('social-post:sync');

        $this->assertSame([
            ['同步狀態' => ['select' => ['name' => '正常']]],
            ['同步狀態' => ['select' => ['name' => '重複']]],
            [
                '名稱' => ['title' => [['text' => ['content' => '重複：'.self::CANONICAL_URL]]]],
                '同步狀態' => ['select' => ['name' => '重複']],
            ],
        ], $this->notionUpdates());
    }

    public function test_duplicate_row_kept_is_the_one_the_site_shows(): void
    {
        $this->fakeNotion([
            $this->page('page-1', ['name' => '排在前面', 'url' => self::SHORT_URL, 'sort' => 1, 'status' => null]),
            $this->page('page-2', ['name' => '排在後面', 'url' => self::CANONICAL_URL, 'status' => null]),
        ]);
        // 排在前面那列這次沒抓到，網站上顯示的是後面那列，所以標重複的是前面那列
        $calls = 0;
        $this->fakeFetcher(
            function () use (&$calls) {
                return ++$calls === 1 ? throw new RuntimeException('平台改版') : $this->fetched();
            },
            fn () => self::CANONICAL_URL,
        );

        $this->artisan('social-post:sync');

        $this->assertSame([
            ['網址' => ['url' => self::CANONICAL_URL], '同步狀態' => ['select' => ['name' => '重複']]],
            ['同步狀態' => ['select' => ['name' => '正常']]],
        ], $this->notionUpdates());
    }

    public function test_duplicate_row_with_codes_the_kept_row_lacks_is_flagged_so_it_is_not_deleted_blindly(): void
    {
        $this->fakeNotion([
            $this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'codes' => '479123, ABC-1', 'sort' => 1, 'status' => '正常']),
            // 大小寫不同的貨號算同一件，只有 470001、888888 是這列多出來的
            $this->page('page-2', ['url' => self::CANONICAL_URL, 'codes' => '470001, abc-1, 479123, 888888', 'status' => null]),
        ]);
        $this->fakeFetcher(fn () => $this->fetched());

        $this->artisan('social-post:sync');

        // 照「重複」刪掉這列，商品頁 470001、888888 就少了這篇，要另外標出來
        $this->assertSame([[
            '名稱' => ['title' => [['text' => ['content' => '重複：我的穿搭，這列多了 470001、888888']]]],
            '同步狀態' => ['select' => ['name' => '重複，貨號不同']],
        ]], $this->notionUpdates());
    }

    public function test_duplicates_are_found_among_rows_skipped_this_time(): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'sort' => 1, 'status' => '正常'])]);
        $this->fakeFetcher(fn () => $this->fetched());
        $this->artisan('social-post:sync');

        // 第一列沒改過、這次跳過，後來加的那列照樣要比對到它
        $this->resetHttp();
        $this->fakeNotion([
            $this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'sort' => 1, 'status' => '正常']),
            $this->page('page-2', ['name' => '又貼一次', 'url' => self::CANONICAL_URL, 'status' => null]),
        ]);
        $this->artisan('social-post:sync');

        $this->assertSame(2, $this->fetchCount);
        $this->assertSame([['同步狀態' => ['select' => ['name' => '重複']]]], $this->notionUpdates());
    }

    public function test_row_no_longer_duplicated_gets_its_own_status_back(): void
    {
        $this->fakeNotion([
            $this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'sort' => 1, 'status' => '正常']),
            $this->page('page-2', ['name' => '又貼一次', 'url' => self::CANONICAL_URL, 'status' => null]),
        ]);
        $this->fakeFetcher(fn () => $this->fetched(['embeddable' => false]));
        $this->artisan('social-post:sync');

        // 主列從 Notion 刪掉了；重複那列沒改過，不用重抓，改回自己上次抓到的結果
        $this->resetHttp();
        $this->fakeNotion([
            $this->page('page-2', ['name' => '又貼一次', 'url' => self::CANONICAL_URL, 'status' => '重複', 'edited' => '2026-10-18T02:00:00.000Z']),
        ]);
        $this->artisan('social-post:sync')
            ->expectsOutput('社群熱推同步完成：抓取 0、失敗 0、沒變跳過 1、移除 1');

        $this->assertSame(2, $this->fetchCount);
        $this->assertSame([['同步狀態' => ['select' => ['name' => '無法嵌入']]]], $this->notionUpdates());
    }

    public function test_write_back_that_failed_is_sent_again_next_time_even_if_the_row_is_skipped(): void
    {
        // 先登記的假回應優先，寫回這次一律失敗
        Http::fake(['api.notion.com/v1/pages/*' => Http::response(['message' => 'conflict'], 409)]);
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'status' => null])]);
        $this->fakeFetcher(fn () => $this->fetched());
        $this->artisan('social-post:sync');

        // 沒寫成功，Notion 的編輯時間沒變，這列下次會被跳過；同步狀態照樣要補送
        $this->resetHttp();
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'status' => null])]);
        $this->artisan('social-post:sync');

        $this->assertSame(1, $this->fetchCount);
        $this->assertSame([['同步狀態' => ['select' => ['name' => '正常']]]], $this->notionUpdates());
    }

    public function test_nothing_is_sent_when_nothing_changed_between_runs(): void
    {
        $page = $this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'status' => '正常']);
        $this->fakeNotion([$page]);
        $this->fakeFetcher(fn () => $this->fetched());

        $this->artisan('social-post:sync');
        $this->artisan('social-post:sync');

        $this->assertSame(1, $this->fetchCount);
        $this->assertSame([], $this->notionUpdates());
    }

    public function test_url_changed_within_the_same_minute_is_not_overwritten_with_the_old_post(): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => self::SHORT_URL, 'status' => '正常'])]);
        $this->fakeFetcher(fn () => $this->fetched(), fn () => self::CANONICAL_URL);
        $this->artisan('social-post:sync');

        // Notion 的編輯時間只到分鐘：寫回之後同一分鐘內又改成另一篇，編輯時間看起來沒變
        $other = 'https://www.threads.com/@meta/post/C1';
        $this->resetHttp();
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => $other, 'status' => '正常', 'edited' => '2026-10-18T02:00:00.000Z'])]);
        $this->fakeFetcher(fn (string $url) => $this->fetched(['canonical_url' => $url]), fn (string $url) => $url);
        $this->artisan('social-post:sync');

        $this->assertNotContains(['url' => self::CANONICAL_URL], array_column($this->notionUpdates(), '網址'));
        $this->assertSame($other, SocialPost::sole()->canonical_url);
    }

    public static function failuresAfterTheUrlChanged(): array
    {
        return [
            '新網址不支援' => [fn () => throw new \InvalidArgumentException('不支援的網址')],
            '新網址暫時連不上' => [fn () => throw new RequestException(new Response(new \GuzzleHttp\Psr7\Response(503)))],
        ];
    }

    #[DataProvider('failuresAfterTheUrlChanged')]
    public function test_url_changed_to_one_that_cannot_be_resolved_does_not_bring_back_the_old_post(\Closure $canonical): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'status' => '正常'])]);
        $this->fakeFetcher(fn () => $this->fetched());
        $this->artisan('social-post:sync');

        $this->resetHttp();
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => 'https://www.threads.com/share/NewOne/', 'status' => '正常', 'edited' => '2026-10-18T03:00:00.000Z'])]);
        $this->fakeFetcher(fn () => $this->fetched(), $canonical);
        $this->artisan('social-post:sync');

        // 舊貼文已經不是這列要的，不再顯示，也不把舊網址寫回去蓋掉剛填的；暫時的錯誤也要讓 Notion 看得出沒在顯示
        $post = SocialPost::sole();
        $this->assertNull($post->canonical_url);
        $this->assertSame(0, SocialPost::visible()->count());
        $this->assertSame([['同步狀態' => ['select' => ['name' => '抓取失敗']]]], $this->notionUpdates());
    }

    public static function syncResults(): array
    {
        return [
            '可以嵌入' => [fn () => ['embeddable' => true], '正常'],
            '無法嵌入' => [fn () => ['embeddable' => false], '無法嵌入'],
            '沒登入看不到' => [fn () => throw new SocialPostUnavailableException('只給登入的人看', SocialSyncStatus::LoginRequired), '沒登入看不到'],
            '已刪除' => [fn () => throw new SocialPostUnavailableException('已刪除', SocialSyncStatus::Deleted), '已刪除'],
            '其他抓不到' => [fn () => throw new RuntimeException('平台改版'), '抓取失敗'],
            '不支援的網址' => [fn () => throw new \InvalidArgumentException('不支援的網址'), '抓取失敗'],
        ];
    }

    #[DataProvider('syncResults')]
    public function test_each_row_reports_its_sync_status_to_notion(\Closure $result, string $status): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'status' => null])]);
        $this->fakeFetcher(fn () => $this->fetched($result()));

        $this->artisan('social-post:sync');

        $this->assertSame([['同步狀態' => ['select' => ['name' => $status]]]], $this->notionUpdates());
    }

    public function test_row_without_a_url_is_reported_as_failed(): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => null, 'status' => null])]);
        $this->fakeFetcher();

        $this->artisan('social-post:sync');

        $this->assertSame([['同步狀態' => ['select' => ['name' => '抓取失敗']]]], $this->notionUpdates());
    }

    public function test_temporary_failures_leave_the_sync_status_alone(): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL, 'status' => '已刪除'])]);
        $this->fakeFetcher(fn () => throw new RequestException(new Response(new \GuzzleHttp\Psr7\Response(503))));

        $this->artisan('social-post:sync');

        $this->assertSame([], $this->notionUpdates());
    }

    public function test_sync_status_is_skipped_when_the_notion_database_has_no_such_property(): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'url' => self::CANONICAL_URL])]);
        $this->fakeFetcher(fn () => throw new RuntimeException('平台改版'));

        $this->artisan('social-post:sync')->assertExitCode(0);

        $this->assertSame([], $this->notionUpdates());
    }

    public function test_failed_share_link_row_still_gets_the_post_url_written_back(): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'status' => null])]);
        Http::fake([
            'www.threads.com/share/*' => Http::response('<meta property="og:url" content="https://www.threads.com/&#064;meta/post/DDzbnVKx57R" />'),
            'graph.threads.net/*' => Http::response(['error' => ['message' => 'The requested resource does not exist', 'code' => 24]], 400),
            'www.threads.com/*' => Http::response('<meta property="og:title" content="Threads &#x2022; Log in" />'
                .'<meta property="og:url" content="https://www.threads.com/" />'),
        ]);

        $this->artisan('social-post:sync')->assertExitCode(0);

        $post = SocialPost::sole();
        $this->assertSame(SocialPostStatus::Failed, $post->status);
        // 下次再貼同一篇才比對得出重複
        $this->assertSame(self::CANONICAL_URL, $post->canonical_url);
        $this->assertSame([[
            '網址' => ['url' => self::CANONICAL_URL],
            '同步狀態' => ['select' => ['name' => '已刪除']],
        ]], $this->notionUpdates());
    }

    public function test_share_link_to_a_deleted_post_is_reported_as_deleted_with_the_post_url(): void
    {
        $this->fakeNotion([$this->page('page-1', ['name' => '我的穿搭', 'status' => null])]);
        Http::fake([
            'www.threads.com/share/*' => Http::response('', 302, ['Location' => self::CANONICAL_URL.'?xmt=abc']),
            'www.threads.com/@meta/post/*' => Http::response('', 302, ['Location' => 'https://www.threads.com/?error=invalid_post']),
            'graph.threads.net/*' => Http::response(['error' => ['message' => 'The requested resource does not exist', 'code' => 24]], 400),
            'www.threads.com/*' => Http::response('<meta property="og:url" content="https://www.threads.com/" />'),
        ]);

        $this->artisan('social-post:sync')->assertExitCode(0);

        $this->assertSame(self::CANONICAL_URL, SocialPost::sole()->canonical_url);
        $this->assertSame([[
            '網址' => ['url' => self::CANONICAL_URL],
            '同步狀態' => ['select' => ['name' => '已刪除']],
        ]], $this->notionUpdates());
    }

    public function test_full_refetch_pauses_between_posts(): void
    {
        $this->fakeNotion([$this->page('page-1'), $this->page('page-2'), $this->page('page-3')]);
        $this->fakeFetcher(fn () => $this->fetched());

        $this->artisan('social-post:sync --full');

        Sleep::assertSleptTimes(2);
    }
}
