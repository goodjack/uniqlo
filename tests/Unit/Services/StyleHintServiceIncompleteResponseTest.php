<?php

namespace Tests\Unit\Services;

use App\Repositories\StyleHintRepository;
use App\Services\StyleHintService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

/**
 * 官網回傳的穿搭清單少了欄位時，只跳過那一頁或那一筆，其他頁照常抓完。
 */
class StyleHintServiceIncompleteResponseTest extends TestCase
{
    private const LIST_URL = 'https://api.example.com/style-hint-list';

    private const DETAIL_URL = 'https://api.example.com/style-hint-detail/';

    private const UGC_URL = 'https://api.example.com/ugc-style-hints';

    private const RETRY_TIMES = 2;

    private StyleHintRepository&MockObject $repository;

    private StyleHintService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.user_agents', ['Mozilla/5.0']);
        Config::set('cache.default', 'array');
        Config::set('app.crawler.page_sizes.ugc_style_hints', 50);
        Config::set('app.crawler.delay.min', 0);
        Config::set('app.crawler.delay.max', 0);
        Config::set('app.crawler.retry.times', self::RETRY_TIMES);
        Config::set('app.crawler.retry.sleep_min', 0);
        Config::set('app.crawler.retry.sleep_max', 0);
        Config::set('app.crawler.batch_rest.offset.interval', 0);
        Config::set('app.crawler.batch_rest.detail.interval', 0);
        Config::set('uniqlo.api.style_hint_list.us', self::LIST_URL);
        Config::set('uniqlo.api.style_hint_detail.us', self::DETAIL_URL);
        Config::set('uniqlo.api.ugc_style_hint_list.tw', self::UGC_URL);

        Log::shouldReceive('info', 'warning', 'error')->andReturnNull();

        $this->repository = $this->createMock(StyleHintRepository::class);
        $this->repository->method('getExistStyleHintOutfitIds')->willReturn([]);

        $this->service = new StyleHintService($this->repository);
    }

    public function test_first_page_missing_images_is_skipped_and_later_pages_are_still_fetched(): void
    {
        $this->fakeList(fn (int $offset) => $offset === 0
            ? ['result' => ['pagination' => ['total' => 100]]]
            : ['result' => ['images' => [], 'pagination' => ['total' => 100]]]);

        $this->assertTrue($this->service->fetchAllStyleHints('us'));
        $this->assertSame([0, 0, 50, 100], $this->listOffsets());
    }

    public function test_backfill_checkpoint_moves_past_a_skipped_page(): void
    {
        $this->fakeList(fn (int $offset) => $offset === 0
            ? ['result' => ['pagination' => ['total' => 100]]]
            : null);

        $this->assertFalse($this->service->fetchAllStyleHints('us', backfill: true));
        $this->assertSame([0, 0, 50], $this->listOffsets());
        $this->assertSame(50, Cache::get('style_hint:offset:us'));
    }

    public function test_full_pages_without_total_stop_after_a_few_pages(): void
    {
        $this->fakeList(fn (int $offset) => ['result' => ['images' => $this->summaries(range($offset + 1, $offset + 50))]]);
        $this->fakeDetail();

        $this->service->fetchAllStyleHints('us', backfill: true);

        $this->assertSame([0, 0, 50, 50, 100, 100], $this->listOffsets());
    }

    public function test_ugc_full_pages_without_total_stop_after_a_few_pages(): void
    {
        $this->fakeUgc(fn () => ['content_list' => array_fill(0, 50, ['id_ugc_dist_content' => 'x'])]);

        $this->service->fetchAllStyleHintsFromUgc(isManual: false);

        $this->assertSame([1, 1, 2, 2, 3, 3], $this->ugcPagesForGender('1'));
    }

    public function test_page_missing_total_still_moves_on_while_the_page_is_full(): void
    {
        $this->fakeList(fn (int $offset) => $offset === 0
            ? ['result' => ['images' => $this->summaries(range(1, 50))]]
            : ['result' => ['images' => $this->summaries([51]), 'pagination' => ['total' => 51]]]);
        $this->fakeDetail();

        $this->assertTrue($this->service->fetchAllStyleHints('us'));
        $this->assertSame([0, 0, 50], $this->listOffsets());
        $this->assertCount(51, $this->requestsTo('/style-hint-detail/'));
    }

    public function test_page_with_neither_images_nor_total_is_treated_as_unavailable(): void
    {
        $this->fakeList(fn () => ['result' => []]);

        $this->assertFalse($this->service->fetchAllStyleHints('us'));
        $this->assertSame([0, 0], $this->listOffsets());
    }

    public function test_summary_without_outfit_id_is_skipped_and_the_rest_of_the_page_is_saved(): void
    {
        $this->fakeList(fn () => ['result' => [
            'images' => [['outfitId' => 1], ['styleImageUrl' => 'no-id.jpg'], ['outfitId' => 3]],
            'pagination' => ['total' => 3],
        ]]);
        $this->fakeDetail();

        $this->repository->expects($this->exactly(2))->method('saveStyleHints');

        $this->assertTrue($this->service->fetchAllStyleHints('us'));
        $this->assertSame(
            [self::DETAIL_URL.'1/details', self::DETAIL_URL.'3/details'],
            $this->requestsTo('/style-hint-detail/')->map(fn (Request $request) => strtok($request->url(), '?'))->all()
        );
    }

    public function test_ugc_first_page_missing_content_list_is_skipped_and_later_pages_are_still_fetched(): void
    {
        $this->fakeUgc(fn (int $page) => $page === 1
            ? ['total_result_count' => 100]
            : ['total_result_count' => 100, 'content_list' => []]);

        $this->assertTrue($this->service->fetchAllStyleHintsFromUgc(isManual: false));
        $this->assertSame([1, 1, 2, 3], $this->ugcPagesForGender('1'));
    }

    public function test_ugc_page_missing_total_still_moves_on_while_the_page_is_full(): void
    {
        $this->fakeUgc(fn (int $page) => $page === 1
            ? ['content_list' => array_fill(0, 50, ['id_ugc_dist_content' => 'x'])]
            : ['total_result_count' => 50, 'content_list' => []]);

        $this->assertTrue($this->service->fetchAllStyleHintsFromUgc(isManual: false));
        $this->assertSame([1, 1, 2], $this->ugcPagesForGender('1'));
    }

    public function test_incomplete_page_that_comes_back_complete_on_retry_is_saved(): void
    {
        $this->fakeList(fn (int $offset, int $attempt) => $attempt === 1
            ? ['result' => ['pagination' => ['total' => 1]]]
            : ['result' => ['images' => $this->summaries([1]), 'pagination' => ['total' => 1]]]);
        $this->fakeDetail();

        $this->repository->expects($this->once())->method('saveStyleHints');

        $this->assertTrue($this->service->fetchAllStyleHints('us', backfill: true));
        $this->assertSame([0, 0], $this->listOffsets());
        $this->assertNull(Cache::get('style_hint:offset:us'));
    }

    public function test_ugc_incomplete_page_is_only_saved_once_it_comes_back_complete(): void
    {
        $contentList = [['id_ugc_dist_content' => 'x']];
        $this->fakeUgc(fn (int $page, int $attempt) => $attempt === 1
            ? ['content_list' => $contentList]
            : ['total_result_count' => 1, 'content_list' => $contentList]);

        $this->repository->expects($this->exactly(5))->method('saveStyleHintsFromUgc');

        $this->assertTrue($this->service->fetchAllStyleHintsFromUgc(isManual: false));
        $this->assertSame([1, 1], $this->ugcPagesForGender('1'));
    }

    /**
     * @param  callable(int $offset, int $attempt): ?array  $bodyForOffset  回 null 代表被擋（403）
     */
    private function fakeList(callable $bodyForOffset): void
    {
        $attempts = [];

        Http::fake([self::LIST_URL.'*' => function (Request $request) use ($bodyForOffset, &$attempts) {
            $offset = (int) $request['offset'];
            $attempts[$offset] = ($attempts[$offset] ?? 0) + 1;
            $body = $bodyForOffset($offset, $attempts[$offset]);

            return is_null($body) ? Http::response([], 403) : Http::response($body);
        }]);
    }

    private function fakeDetail(): void
    {
        Http::fake([self::DETAIL_URL.'*' => Http::response(['result' => ['gender' => 'MEN']])]);
    }

    /**
     * @param  callable(int $page, int $attempt): array  $bodyForPage  每個性別各自計算第幾次請求
     */
    private function fakeUgc(callable $bodyForPage): void
    {
        $attempts = [];

        Http::fake([self::UGC_URL.'*' => function (Request $request) use ($bodyForPage, &$attempts) {
            $page = (int) $request['page'];
            $key = implode(',', (array) $request['style_gender']).":{$page}";
            $attempts[$key] = ($attempts[$key] ?? 0) + 1;

            return Http::response($bodyForPage($page, $attempts[$key]));
        }]);
    }

    /**
     * @param  int[]  $outfitIds
     */
    private function summaries(array $outfitIds): array
    {
        return array_map(fn (int $outfitId) => ['outfitId' => $outfitId], $outfitIds);
    }

    /**
     * @return int[]
     */
    private function listOffsets(): array
    {
        return $this->requestsTo('/style-hint-list')->map(fn (Request $request) => (int) $request['offset'])->all();
    }

    /**
     * @return int[]
     */
    private function ugcPagesForGender(string $gender): array
    {
        return $this->requestsTo('/ugc-style-hints')
            ->filter(fn (Request $request) => $request['style_gender'] === [$gender])
            ->map(fn (Request $request) => (int) $request['page'])
            ->values()
            ->all();
    }

    private function requestsTo(string $pathPrefix): Collection
    {
        return Http::recorded()
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $request) => str_starts_with((string) parse_url($request->url(), PHP_URL_PATH), $pathPrefix))
            ->values();
    }
}
