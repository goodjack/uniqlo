<?php

namespace Tests\Unit\Services;

use App\Enums\CrawlOutcome;
use App\Repositories\JapanProductRepository;
use App\Services\JapanProductService;
use App\Support\ProductSaveResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JapanProductServiceTest extends TestCase
{
    private JapanProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test user agents
        Config::set('app.user_agents', [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36',
        ]);
        Config::set('cache.default', 'array');
        Config::set('app.crawler.page_sizes.japan_products', 36);
        Config::set('app.crawler.delay.min', 0);
        Config::set('app.crawler.delay.max', 0);
        Config::set('app.crawler.retry.sleep_min', 0);
        Config::set('app.crawler.retry.sleep_max', 0);
        Config::set('app.crawler.batch_rest.offset.interval', 0);

        // Mock repository
        $mockRepository = $this->createMock(JapanProductRepository::class);
        $mockRepository->method('saveProducts')->willReturn(new ProductSaveResult);

        $this->service = new JapanProductService($mockRepository);
    }

    public function test_fetch_all_products_stops_on_403_error()
    {
        // Pre-set checkpoint (simulating partial completion)
        Cache::set('japan_products:offset:UNIQLO', 36);
        $checkpointBefore = Cache::get('japan_products:offset:UNIQLO');

        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllProducts('UNIQLO');

        // Checkpoint must not be overwritten on 403
        $checkpointAfter = Cache::get('japan_products:offset:UNIQLO');
        $this->assertEquals(36, $checkpointBefore);
        $this->assertEquals(36, $checkpointAfter, 'Checkpoint must not be overwritten on 403');
    }

    public function test_fetch_all_products_saves_checkpoint()
    {
        Http::fake([
            '*' => Http::response([
                'result' => [
                    'items' => [],
                    'pagination' => ['total' => 0],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        Cache::flush();

        $this->service->fetchAllProducts('UNIQLO');

        // Checkpoint should be cleared after successful completion
        $this->assertNull(Cache::get('japan_products:offset:UNIQLO'));
    }

    public function test_fetch_all_products_resumes_from_checkpoint()
    {
        $initialOffset = 72;
        Cache::set('japan_products:offset:UNIQLO', $initialOffset);

        Http::fake([
            '*' => Http::response([
                'result' => [
                    'items' => [],
                    'pagination' => ['total' => 0],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        $this->service->fetchAllProducts('UNIQLO');

        // Checkpoint should be cleared after completion
        $this->assertNull(Cache::get('japan_products:offset:UNIQLO'));
    }

    public function test_fetch_all_products_fresh_ignores_checkpoint()
    {
        Cache::set('japan_products:offset:UNIQLO', 100);

        Http::fake([
            '*' => Http::response([
                'result' => [
                    'items' => [],
                    'pagination' => ['total' => 0],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        $this->service->fetchAllProducts('UNIQLO', fresh: true);

        // Checkpoint should be cleared
        $this->assertNull(Cache::get('japan_products:offset:UNIQLO'));
    }

    public function test_uses_configured_retry_count()
    {
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        $requestCount = 0;
        // Use wildcard to match GET URLs with query parameters (e.g. ?offset=0&limit=36&...)
        Http::fake(['https://api.example.com/products*' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response([], 500);
        }]);

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllProducts('UNIQLO');

        // retry(3) = 3 total attempts; total stays 0 → loop exits after 1 iteration
        $this->assertEquals(3, $requestCount, 'Should make exactly 3 HTTP attempts (1 initial + 2 retries)');
    }

    public function test_all_pages_fail_preserves_checkpoint_and_skips_stockout()
    {
        // Bug #57: When all pages fail with non-403, the loop exits with total=0
        // and incorrectly clears checkpoint + runs stockout as if completed successfully.
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        // Pre-set checkpoint
        Cache::put('japan_products:offset:UNIQLO', 72);

        Http::fake([
            'https://api.example.com/products*' => Http::response([], 500),
        ]);

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllProducts('UNIQLO');

        // Checkpoint must be preserved — not cleared
        $this->assertEquals(
            72,
            Cache::get('japan_products:offset:UNIQLO'),
            'Checkpoint must not be cleared when no batches succeeded'
        );
    }

    /**
     * 從頭開始、每一批件數都對得上總數，才是完整掃描，這時才做缺貨判定。
     */
    public function test_a_full_clean_crawl_marks_products_as_stocked_out()
    {
        Cache::flush();
        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        Http::fake(['https://api.example.com/products*' => fn ($request) => Http::response(
            $this->listResponse($request->data()['offset'] === 0 ? 36 : 4, 40)
        )]);

        $service = $this->serviceExpectingStockout($this->once());

        $this->assertSame(CrawlOutcome::Succeeded, $service->fetchAllProducts('UNIQLO')->outcome);
    }

    /**
     * 看到的目錄可能不完整時一律不做缺貨判定，否則沒看到的那一段會被整批標成下架。
     */
    #[DataProvider('incompleteCatalogs')]
    public function test_an_incomplete_catalog_never_marks_products_as_stocked_out(
        ?int $checkpoint,
        array $batchesByOffset,
        string $expectedNote
    ) {
        Cache::flush();
        if ($checkpoint !== null) {
            Cache::put('japan_products:offset:UNIQLO', $checkpoint);
        }

        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        Http::fake(['https://api.example.com/products*' => function ($request) use ($batchesByOffset) {
            [$count, $total] = $batchesByOffset[$request->data()['offset']] ?? [0, 0];

            return Http::response($this->listResponse($count, $total));
        }]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->serviceExpectingStockout($this->never())->fetchAllProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame($expectedNote, $result->note);
    }

    public static function incompleteCatalogs(): array
    {
        $gap = '未執行缺貨判定，目錄有缺頁（最早在第 2 頁）';

        return [
            'an empty catalog' => [null, [0 => [0, 0]], '未執行缺貨判定，這一輪一件商品都沒看到'],
            'later batches soft blocked with the same total' => [null, [0 => [36, 100], 36 => [0, 100], 72 => [0, 100]], $gap],
            'the total dropping to zero partway' => [null, [0 => [36, 100], 36 => [0, 0]], $gap],
            'a resumed crawl' => [36, [36 => [4, 40]], '未執行缺貨判定，這一輪是從上次中斷的地方接著跑'],
            // 續跑點停在目錄尾端：只會抓到一批空的，舊版會把整個品牌標成下架
            'a checkpoint left at the end of the catalog' => [72, [72 => [0, 40]], '未執行缺貨判定，這一輪是從上次中斷的地方接著跑'],
        ];
    }

    private function serviceExpectingStockout($invocationRule): JapanProductService
    {
        $repository = $this->createMock(JapanProductRepository::class);
        $repository->method('saveProducts')->willReturn(new ProductSaveResult);
        $repository->expects($invocationRule)->method('setStockoutProducts');

        return new JapanProductService($repository);
    }

    /**
     * 一批回 200 加空清單常是一時的軟擋：先重打，重打後件數對得上就照常做缺貨判定。
     */
    public function test_a_batch_that_comes_back_short_once_is_retried()
    {
        Cache::flush();
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');

        $requests = 0;
        Http::fake(['https://api.example.com/products*' => function () use (&$requests) {
            return Http::response($this->listResponse(++$requests === 1 ? 0 : 4, 4));
        }]);

        $result = $this->serviceExpectingStockout($this->once())->fetchAllProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::Succeeded, $result->outcome);
        $this->assertSame(2, $requests);
    }

    public function test_seeing_far_fewer_products_than_are_in_stock_skips_stockout()
    {
        Cache::flush();
        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');
        Http::fake(['https://api.example.com/products*' => Http::response($this->listResponse(4, 4))]);

        $repository = $this->createMock(JapanProductRepository::class);
        $repository->method('saveProducts')->willReturn(new ProductSaveResult);
        $repository->method('countInStockProducts')->willReturn(900);
        $repository->expects($this->never())->method('setStockoutProducts');

        $result = (new JapanProductService($repository))->fetchAllProducts('UNIQLO');

        $this->assertSame('未執行缺貨判定，這一輪只看到 4 件，目前在售 900 件', $result->note);
    }

    public function test_skipping_stockout_for_too_many_days_needs_attention()
    {
        Cache::flush();
        Cache::forever('stockout:last-run:japan:UNIQLO', today()->subDays(3)->toDateString());
        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');
        Http::fake(['https://api.example.com/products*' => Http::response($this->listResponse(0, 0))]);

        $result = $this->serviceExpectingStockout($this->never())->fetchAllProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::StockoutOverdue, $result->outcome);
        $this->assertStringContainsString('已經 3 天沒有執行缺貨判定', $result->note);
    }

    /**
     * 有幾件寫不進去時目錄其實看完了：缺貨判定照做，但要排除那幾件，
     * 否則它們會因為 updated_at 沒更新而被冤枉下架。
     */
    public function test_products_that_failed_to_save_are_excluded_from_stockout()
    {
        Cache::flush();
        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');
        Http::fake(['https://api.example.com/products*' => Http::response($this->listResponse(4, 4))]);

        $repository = $this->createMock(JapanProductRepository::class);
        $repository->method('saveProducts')->willReturn(new ProductSaveResult(['483870']));
        $repository->expects($this->once())
            ->method('setStockoutProducts')
            ->with('UNIQLO', null, ['483870']);

        $result = (new JapanProductService($repository))->fetchAllProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame('已執行缺貨判定，排除 1 件寫入失敗商品', $result->note);
    }

    /**
     * 寫不進去、連 l1Id 都拿不到的那一筆排除不了，只能整輪不做缺貨判定。
     */
    public function test_a_failure_without_an_l1_id_skips_stockout()
    {
        Cache::flush();
        Config::set('uniqlo.api.product_list.jp', 'https://api.example.com/products');
        Http::fake(['https://api.example.com/products*' => Http::response($this->listResponse(4, 4))]);

        $repository = $this->createMock(JapanProductRepository::class);
        $repository->method('saveProducts')->willReturn(new ProductSaveResult([], 1));
        $repository->expects($this->never())->method('setStockoutProducts');

        $result = (new JapanProductService($repository))->fetchAllProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame('未執行缺貨判定，1 件失敗資料缺少商品識別', $result->note);
    }

    private function listResponse(int $itemCount, int $total): array
    {
        return [
            'result' => [
                'items' => array_fill(0, $itemCount, ['l1Id' => 'x']),
                'pagination' => ['total' => $total],
            ],
        ];
    }
}
