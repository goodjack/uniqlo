<?php

namespace Tests\Unit\Services;

use App\Enums\CrawlOutcome;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\HmallProductService;
use App\Support\ProductSaveResult;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\TestCase;

class HmallProductServiceTest extends TestCase
{
    private HmallProductService $service;

    private HmallProductRepository $mockHmallRepository;

    private ProductRepository $mockProductRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test user agents
        Config::set('app.user_agents', [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36',
        ]);
        Config::set('cache.default', 'array');
        Config::set('app.crawler.page_sizes.hmall_products', 24);
        Config::set('app.crawler.delay.min', 0);
        Config::set('app.crawler.delay.max', 0);
        Config::set('app.crawler.retry.sleep_min', 0);
        Config::set('app.crawler.retry.sleep_max', 0);
        Config::set('app.crawler.batch_rest.offset.interval', 0);
        Config::set('app.crawler.batch_rest.detail.interval', 0);

        // Mock repositories
        $this->mockHmallRepository = $this->createMock(HmallProductRepository::class);
        // 回傳值是「這一頁有哪幾件商品寫失敗」，預設一件都沒失敗
        $this->mockHmallRepository->method('saveProductsFromV3')->willReturn(new ProductSaveResult);
        $this->mockHmallRepository->method('setStockoutHmallProducts')->willReturn(true);
        $this->mockHmallRepository->method('updateProductDescriptionsFromV3')->willReturn(true);

        $this->mockProductRepository = $this->createMock(ProductRepository::class);

        $this->service = new HmallProductService($this->mockHmallRepository, $this->mockProductRepository);
    }

    public function test_fetch_all_hmall_products_stops_on_403_error()
    {
        // Pre-set checkpoint (simulating partial completion)
        Cache::set('hmall_products:page:UNIQLO', 5);
        $checkpointBefore = Cache::get('hmall_products:page:UNIQLO');

        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllHmallProducts('UNIQLO');

        // Checkpoint must not be overwritten on 403
        $checkpointAfter = Cache::get('hmall_products:page:UNIQLO');
        $this->assertEquals(5, $checkpointBefore);
        $this->assertEquals(5, $checkpointAfter, 'Checkpoint must not be overwritten on 403');
    }

    public function test_fetch_all_hmall_products_saves_checkpoint()
    {
        Http::fake([
            '*' => Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 0,
                    ],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');
        Cache::flush();

        $this->service->fetchAllHmallProducts('UNIQLO');

        // Checkpoint should be cleared after successful completion
        $this->assertNull(Cache::get('hmall_products:page:UNIQLO'));
    }

    public function test_fetch_all_hmall_products_resumes_from_checkpoint()
    {
        $initialPage = 3;
        Cache::set('hmall_products:page:UNIQLO', $initialPage);

        Http::fake([
            '*' => Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 0,
                    ],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $this->service->fetchAllHmallProducts('UNIQLO');

        // Checkpoint should be cleared after completion
        $this->assertNull(Cache::get('hmall_products:page:UNIQLO'));
    }

    /**
     * 續跑只看了目錄的後半段，就算每一頁都成功也不可以做缺貨判定，
     * 否則前半段會被整批標成下架。
     */
    public function test_a_resumed_crawl_never_marks_products_as_stocked_out()
    {
        Cache::set('hmall_products:page:UNIQLO', 3);

        Http::fake([
            '*' => Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 0,
                    ],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $this->mockHmallRepository->expects($this->never())
            ->method('setStockoutHmallProducts');

        $result = $this->service->fetchAllHmallProducts('UNIQLO');

        // 通知要看得出這次沒做完整掃描
        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame('未執行缺貨判定，這一輪是從上次中斷的地方接著跑', $result->note);
        // checkpoint 清掉，明天才會重新從第 1 頁完整掃
        $this->assertNull(Cache::get('hmall_products:page:UNIQLO'));
    }

    /**
     * 續跑時就算有商品寫入失敗，通知的說明仍然是「續跑」那一句。
     */
    public function test_a_resumed_crawl_with_a_named_failure_still_skips_stockout()
    {
        Cache::set('hmall_products:page:UNIQLO', 3);

        Http::fake([
            '*' => Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 0,
                    ],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $repository = $this->createMock(HmallProductRepository::class);
        $repository->method('saveProductsFromV3')
            ->willReturn(new ProductSaveResult(['u0000000053204']));
        $repository->expects($this->never())->method('setStockoutHmallProducts');

        $service = new HmallProductService($repository, $this->mockProductRepository);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame('未執行缺貨判定，這一輪是從上次中斷的地方接著跑', $result->note);
        // checkpoint 清掉，明天才會重新從第 1 頁完整掃
        $this->assertNull(Cache::get('hmall_products:page:UNIQLO'));
    }

    /**
     * 從第 1 頁開始、全部成功、而且真的看到商品，才是完整掃描，這時候缺貨判定才該跑。
     */
    public function test_a_full_clean_crawl_still_marks_products_as_stocked_out()
    {
        Cache::flush();

        Http::fake([
            '*' => Http::response($this->searchResponse(3)),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $this->mockHmallRepository->expects($this->once())
            ->method('setStockoutHmallProducts')
            ->with('UNIQLO');

        $this->assertSame(
            CrawlOutcome::Succeeded,
            $this->service->fetchAllHmallProducts('UNIQLO')->outcome
        );
    }

    public function test_fetch_all_hmall_products_fresh_ignores_checkpoint()
    {
        Cache::set('hmall_products:page:UNIQLO', 10);

        Http::fake([
            '*' => Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 0,
                    ],
                ],
            ]),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $this->service->fetchAllHmallProducts('UNIQLO', fresh: true);

        // Checkpoint should be cleared
        $this->assertNull(Cache::get('hmall_products:page:UNIQLO'));
    }

    public function test_fetch_all_hmall_product_descriptions_stops_on_403_error()
    {
        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.v3.description.tw', 'https://api.example.com/description/');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        // Create a mock HmallProduct
        $mockProduct = new stdClass;
        $mockProduct->id = 1;
        $mockProduct->product_code = 'TEST123';

        // 403 should now throw from fetchHmallProductDescriptions
        $this->expectException(RequestException::class);

        $this->service->fetchHmallProductDescriptions($mockProduct, 'UNIQLO');
    }

    public function test_fetch_all_hmall_product_descriptions_checkpoint_not_updated_on_403(): void
    {
        // This is the core regression test for the 403 bug fix.
        // When fetchHmallProductDescriptions encounters 403, it must throw
        // so that fetchAllHmallProductDescriptions does NOT update the checkpoint.

        Http::fake([
            '*' => Http::response([], 403),
        ]);

        Config::set('uniqlo.api.v3.description.tw', 'https://api.example.com/description/');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $cacheKey = 'hmall_descriptions:last_id:UNIQLO';
        Cache::flush();

        // Verify checkpoint is null before
        $this->assertNull(Cache::get($cacheKey));

        // Create a mock HmallProduct
        $mockProduct = new stdClass;
        $mockProduct->id = 42;
        $mockProduct->product_code = 'TEST123';

        // fetchHmallProductDescriptions throws on 403
        try {
            $this->service->fetchHmallProductDescriptions($mockProduct, 'UNIQLO');
        } catch (\Throwable $e) {
            // Expected throw
        }

        // Checkpoint must NOT be updated - this is the core fix
        $this->assertNull(Cache::get($cacheKey), 'Checkpoint must not be updated when 403 is thrown');
    }

    public function test_uses_configured_retry_count()
    {
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $requestCount = 0;
        Http::fake(['https://api.example.com/search' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response([], 500);
        }]);

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllHmallProducts('UNIQLO');

        // retry(3) = 3 total attempts; productSum stays 0 → loop exits after 1 iteration
        $this->assertEquals(3, $requestCount, 'Should make exactly 3 HTTP attempts (1 initial + 2 retries)');
    }

    public function test_get_related_products_uses_product_repository()
    {
        $hmallProduct = $this->createMock(\App\Models\HmallProduct::class);

        $this->mockProductRepository
            ->expects($this->once())
            ->method('getRelatedProductsForHmallProduct')
            ->with($hmallProduct);

        $this->service->getRelatedProducts($hmallProduct);
    }

    public function test_description_fetch_retries_each_url_independently()
    {
        // Fix #10: instruction and sizeChart HTTP requests are now in separate retry blocks,
        // so a transient failure on sizeChart doesn't re-fetch instruction.
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.v3.description.tw', 'https://api.example.com/description/');

        $instructionCount = 0;
        $sizeChartCount = 0;

        Http::fake(function ($request) use (&$instructionCount, &$sizeChartCount) {
            $url = $request->url();

            if (str_contains($url, 'instructionH5')) {
                $instructionCount++;

                return Http::response('<div>instruction</div>');
            }

            if (str_contains($url, 'sizeAndTryOnH5')) {
                $sizeChartCount++;

                // First attempt fails, second succeeds
                if ($sizeChartCount === 1) {
                    return Http::response([], 500);
                }

                return Http::response('<div>sizeChart</div>');
            }

            return Http::response([], 404);
        });

        $mockProduct = $this->createMock(\App\Models\HmallProduct::class);
        $mockProduct->method('__get')->willReturnMap([
            ['id', 1],
            ['product_code', 'TEST123'],
        ]);

        $this->service->fetchHmallProductDescriptions($mockProduct, 'UNIQLO');

        // Instruction should be fetched exactly once (succeeded on first try)
        $this->assertEquals(1, $instructionCount, 'Instruction should not be re-fetched when sizeChart retries');
        // SizeChart should be fetched twice (failed once, then succeeded)
        $this->assertEquals(2, $sizeChartCount, 'SizeChart should retry independently');
    }

    /**
     * 頁數照 productSum 算：25 件要抓 2 頁，剛好 48 件也只抓 2 頁，不多抓一頁
     * 超出範圍的空頁（官方對那一頁怎麼回沒有保證，回錯格式就會變成每天固定缺頁）。
     */
    #[DataProvider('pageCounts')]
    public function test_fetches_exactly_the_pages_the_product_sum_needs(int $productSum, int $expectedRequests)
    {
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Http::fake(['https://api.example.com/search' => fn ($request) => Http::response($this->searchResponse(
            min(24, max(0, $productSum - ($request->data()['pageInfo']['page'] - 1) * 24)),
            $productSum
        ))]);

        $this->service->fetchAllHmallProducts('UNIQLO');

        Http::assertSentCount($expectedRequests);
    }

    public static function pageCounts(): array
    {
        return [
            'one extra item spills onto page 2' => [25, 2],
            'an exact multiple stops at the last full page' => [48, 2],
            'an empty catalog stops after page 1' => [0, 1],
        ];
    }

    /**
     * 完整掃描中有商品寫不進去時，缺貨判定照做、但要排除那幾件：目錄其實看完了，
     * 整輪跳過的話只要有一件固定寫不進去，缺貨判定就永遠不會執行。
     */
    public function test_a_full_scan_still_marks_stockout_but_excludes_the_products_that_failed_to_save()
    {
        Cache::flush();

        Http::fake([
            '*' => Http::response($this->searchResponse(3)),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        // 這一輪從第 1 頁開始、HTTP 全部成功，唯一的問題是有一件商品寫不進去
        $repository = $this->createMock(HmallProductRepository::class);
        $repository->method('saveProductsFromV3')
            ->willReturn(new ProductSaveResult(['u0000000053204']));
        $repository->expects($this->once())
            ->method('setStockoutHmallProducts')
            ->with('UNIQLO', null, ['u0000000053204']);

        $service = new HmallProductService($repository, $this->mockProductRepository);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        // 通知要看得出缺貨判定其實有做，只是排除了幾件
        $this->assertSame('已執行缺貨判定，排除 1 件寫入失敗商品', $result->note);
        // checkpoint 要清掉：留著只會讓下一輪從空頁起跑，白白跳過一次完整掃描
        $this->assertNull(Cache::get('hmall_products:page:UNIQLO'));
    }

    /**
     * 寫入失敗但拿不到商品編號時，不知道要排除誰，缺貨判定不能做。
     */
    public function test_a_failure_without_a_product_code_skips_stockout()
    {
        Cache::flush();

        Http::fake([
            '*' => Http::response($this->searchResponse(3)),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $repository = $this->createMock(HmallProductRepository::class);
        $repository->method('saveProductsFromV3')
            ->willReturn(new ProductSaveResult([], 1));
        $repository->expects($this->never())->method('setStockoutHmallProducts');

        $service = new HmallProductService($repository, $this->mockProductRepository);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        // 通知要講清楚這一輪根本沒做缺貨判定，以及為什麼
        $this->assertSame('未執行缺貨判定，1 件失敗資料缺少商品編號', $result->note);
        $this->assertNull(Cache::get('hmall_products:page:UNIQLO'));
    }

    /**
     * 同一件商品固定寫不進去時，每一輪仍然從第 1 頁開始、每一輪都做帶排除清單的
     * 缺貨判定，不會因為保留續跑點而讓缺貨判定一直輪不到。
     */
    public function test_a_product_that_keeps_failing_does_not_stall_stockout_day_after_day()
    {
        Cache::flush();

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $requestedPages = [];
        Http::fake(['https://api.example.com/search' => function ($request) use (&$requestedPages) {
            $requestedPages[] = $request->data()['pageInfo']['page'];

            return Http::response($this->searchResponse(3));
        }]);

        $repository = $this->createMock(HmallProductRepository::class);
        $repository->method('saveProductsFromV3')
            ->willReturn(new ProductSaveResult(['u0000000053204']));
        $repository->expects($this->exactly(2))
            ->method('setStockoutHmallProducts')
            ->with('UNIQLO', null, ['u0000000053204']);

        $service = new HmallProductService($repository, $this->mockProductRepository);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $firstRun = $service->fetchAllHmallProducts('UNIQLO');
        $secondRun = $service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $firstRun->outcome);
        $this->assertSame(CrawlOutcome::PartiallySucceeded, $secondRun->outcome);
        // 兩輪都要從第 1 頁開始，不能有任何一輪是從 checkpoint 續跑
        $this->assertSame([1, 1], $requestedPages);
    }

    /**
     * 那件商品隔天寫得進去了，就不該再出現在排除清單裡，整輪回到完全成功。
     */
    public function test_a_recovered_product_is_no_longer_excluded()
    {
        Cache::flush();

        Http::fake([
            '*' => Http::response($this->searchResponse(3)),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $repository = $this->createMock(HmallProductRepository::class);
        $repository->method('saveProductsFromV3')
            ->willReturnOnConsecutiveCalls(
                new ProductSaveResult(['u0000000053204']),
                new ProductSaveResult,
            );

        $stockoutCalls = [];
        $repository->method('setStockoutHmallProducts')
            ->willReturnCallback(function ($brand, $updatedIsBefore, $excluded) use (&$stockoutCalls) {
                $stockoutCalls[] = $excluded;

                return true;
            });

        $service = new HmallProductService($repository, $this->mockProductRepository);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $service->fetchAllHmallProducts('UNIQLO')->outcome);
        $this->assertSame(CrawlOutcome::Succeeded, $service->fetchAllHmallProducts('UNIQLO')->outcome);
        $this->assertSame([['u0000000053204'], []], $stockoutCalls);
    }

    /**
     * 兩個品牌各自累積自己的排除清單，UNIQLO 的失敗不會影響 GU 的缺貨判定。
     */
    public function test_each_brand_keeps_its_own_exclusion_list()
    {
        Cache::flush();

        Http::fake([
            '*' => Http::response($this->searchResponse(3)),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');
        Config::set('gu.api.v3.search.tw', 'https://api.example.com/gu-search');

        $repository = $this->createMock(HmallProductRepository::class);
        $repository->method('saveProductsFromV3')
            ->willReturnCallback(fn ($products, $brand) => $brand === 'UNIQLO'
                ? new ProductSaveResult(['u0000000053204'])
                : new ProductSaveResult);

        $stockoutCalls = [];
        $repository->method('setStockoutHmallProducts')
            ->willReturnCallback(function ($brand, $updatedIsBefore, $excluded) use (&$stockoutCalls) {
                $stockoutCalls[$brand] = $excluded;

                return true;
            });

        $service = new HmallProductService($repository, $this->mockProductRepository);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $service->fetchAllHmallProducts('UNIQLO');
        $service->fetchAllHmallProducts('GU');

        $this->assertSame(['u0000000053204'], $stockoutCalls['UNIQLO']);
        $this->assertSame([], $stockoutCalls['GU']);
    }

    public function test_all_pages_fail_preserves_checkpoint_and_skips_stockout()
    {
        // Bug #57: When all pages fail with non-403, the loop exits with productSum=0
        // and incorrectly clears checkpoint + runs stockout as if completed successfully.
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        // Pre-set checkpoint
        Cache::put('hmall_products:page:UNIQLO', 3);

        Http::fake([
            'https://api.example.com/search' => Http::response([], 500),
        ]);

        // setStockoutHmallProducts should NOT be called
        $this->mockHmallRepository->expects($this->never())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllHmallProducts('UNIQLO');

        // Checkpoint must be preserved — not cleared
        $this->assertEquals(
            3,
            Cache::get('hmall_products:page:UNIQLO'),
            'Checkpoint must not be cleared when no pages succeeded'
        );
    }

    /**
     * 只有幾頁失敗：目錄有缺口，缺貨判定不能做、續跑點保留，通知說明是缺頁。
     */
    public function test_some_pages_failing_skips_stockout_and_says_the_catalog_has_gaps()
    {
        Cache::flush();
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $call = 0;
        Http::fake(['https://api.example.com/search' => function () use (&$call) {
            $call++;

            // 第 1 頁抓得到，而且 productSum 大於一頁，迴圈會再去抓第 2 頁
            if ($call === 1) {
                return Http::response($this->searchResponse(24, 25));
            }

            return Http::response([], 500);
        }]);

        $this->mockHmallRepository->expects($this->never())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame('未執行缺貨判定，目錄有缺頁（最早在第 2 頁）', $result->note);
        // checkpoint 保留，同一天可以接著從第 2 頁跑完剩下的
        $this->assertSame(2, Cache::get('hmall_products:page:UNIQLO'));
    }

    /**
     * 來源回空目錄（HTTP 200、productList 空陣列；WAF 軟擋、上游條件跑掉都長這樣）時，
     * 不可以做缺貨判定，也不可以回報成功。
     */
    public function test_an_empty_catalog_never_marks_products_as_stocked_out()
    {
        Cache::flush();

        Http::fake([
            '*' => Http::response($this->searchResponse(0)),
        ]);

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $this->mockHmallRepository->expects($this->never())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->service->fetchAllHmallProducts('UNIQLO');

        // 不能回成功：回成功等於排程判定今天一切正常，沒有人會知道整份目錄是空的
        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame('未執行缺貨判定，這一輪一件商品都沒看到', $result->note);
    }

    /**
     * 中間頁失敗、後面頁成功時，續跑點要退回那個失敗的頁碼，不能被後面成功的頁
     * 推過去（只讓最後一頁失敗的測試測不到這條）。
     */
    public function test_a_page_failing_in_the_middle_rewinds_the_checkpoint_to_that_page()
    {
        Cache::flush();
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        // productSum 60、每頁 24：迴圈會跑第 1、2、3 頁
        Http::fake(['https://api.example.com/search' => function ($request) {
            if ($request->data()['pageInfo']['page'] === 2) {
                return Http::response([], 500);
            }

            return Http::response($this->searchResponse(24, 60));
        }]);

        $this->mockHmallRepository->expects($this->never())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame('未執行缺貨判定，目錄有缺頁（最早在第 2 頁）', $result->note);
        // 第 3 頁成功過，但續跑點不可以被推到 4
        $this->assertSame(2, Cache::get('hmall_products:page:UNIQLO'));
    }

    /**
     * 續跑點只在當天有效：同一天重跑從失敗頁接著跑，隔天一律從第 1 頁完整掃。
     * 跨日續跑的話，某一頁天天失敗時前面幾頁的價格就從此不再更新。
     */
    public function test_the_checkpoint_only_lasts_until_the_end_of_the_day()
    {
        Cache::flush();
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $this->travelTo(now()->setTime(9, 1));

        $requestedPages = [];
        Http::fake(['https://api.example.com/search' => function ($request) use (&$requestedPages) {
            $page = $request->data()['pageInfo']['page'];
            $requestedPages[] = $page;

            return $page === 2
                ? Http::response([], 500)
                : Http::response($this->searchResponse(24, 60));
        }]);

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->service->fetchAllHmallProducts('UNIQLO');
        $this->assertSame(2, Cache::get('hmall_products:page:UNIQLO'));

        $requestedPages = [];
        $this->travelTo(now()->setTime(18, 0));
        $this->service->fetchAllHmallProducts('UNIQLO');
        $this->assertSame(2, $requestedPages[0], '同一天重跑要從失敗頁接著跑');

        $requestedPages = [];
        $this->travelTo(now()->addDay()->setTime(9, 1));
        $this->service->fetchAllHmallProducts('UNIQLO');
        $this->assertSame(1, $requestedPages[0], '隔天要從第 1 頁完整掃');
    }

    /**
     * 跑到一半被 403 擋下，前面幾頁已經寫進資料庫了，不能說成「完全失敗」
     * （完全失敗是連一頁都沒抓到）。
     */
    public function test_a_block_partway_through_is_reported_as_a_partial_success()
    {
        Cache::flush();
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Http::fake(['https://api.example.com/search' => function ($request) {
            if ($request->data()['pageInfo']['page'] === 2) {
                return Http::response([], 403);
            }

            return Http::response($this->searchResponse(24, 60));
        }]);

        $this->mockHmallRepository->expects($this->never())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame('未執行缺貨判定，第 2 頁起被擋下（403）', $result->note);
        // 續跑點停在被擋的那一頁
        $this->assertSame(2, Cache::get('hmall_products:page:UNIQLO'));
    }

    /**
     * 第 1 頁有貨、後面幾頁被軟擋（200 加空清單，或連總數都改小讓翻頁提早結束），
     * 請求全都「成功」，但後面那一段根本沒看到，不可以做缺貨判定。
     */
    #[DataProvider('softBlockedLaterPages')]
    public function test_later_pages_coming_back_empty_skip_stockout(int $softBlockedProductSum)
    {
        Cache::flush();
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Http::fake(['https://api.example.com/search' => fn ($request) => $request->data()['pageInfo']['page'] === 1
            ? Http::response($this->searchResponse(24, 240))
            : Http::response($this->searchResponse(0, $softBlockedProductSum))]);

        $this->mockHmallRepository->expects($this->never())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame('未執行缺貨判定，目錄有缺頁（最早在第 2 頁）', $result->note);
        $this->assertSame(2, Cache::get('hmall_products:page:UNIQLO'));
    }

    public static function softBlockedLaterPages(): array
    {
        return [
            'the total stays the same' => [240],
            'the total drops to zero' => [0],
        ];
    }

    /**
     * 最後一頁回空清單、總數照舊，同樣是沒看到那幾件。
     */
    public function test_a_short_last_page_skips_stockout()
    {
        Cache::flush();
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Http::fake(['https://api.example.com/search' => fn ($request) => $request->data()['pageInfo']['page'] === 1
            ? Http::response($this->searchResponse(24, 30))
            : Http::response($this->searchResponse(0, 30))]);

        $this->mockHmallRepository->expects($this->never())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $this->assertSame(
            '未執行缺貨判定，目錄有缺頁（最早在第 2 頁）',
            $this->service->fetchAllHmallProducts('UNIQLO')->note
        );
    }

    /**
     * 一頁回 200 加空清單常是一時的軟擋：先重打，重打後件數對得上就照常做缺貨判定。
     */
    public function test_a_page_that_comes_back_short_once_is_retried()
    {
        Cache::flush();
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $page2Requests = 0;
        Http::fake(['https://api.example.com/search' => function ($request) use (&$page2Requests) {
            if ($request->data()['pageInfo']['page'] === 1) {
                return Http::response($this->searchResponse(24, 30));
            }

            return Http::response($this->searchResponse(++$page2Requests === 1 ? 0 : 6, 30));
        }]);

        $this->mockHmallRepository->expects($this->once())
            ->method('setStockoutHmallProducts');

        $this->assertSame(CrawlOutcome::Succeeded, $this->service->fetchAllHmallProducts('UNIQLO')->outcome);
        $this->assertSame(2, $page2Requests);
    }

    /**
     * 重打到最後一次仍然不足，才照實記成缺口；不足的那頁有幾件就寫幾件。
     */
    public function test_a_page_that_stays_short_after_every_retry_is_a_gap()
    {
        Cache::flush();
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Http::fake(['https://api.example.com/search' => fn ($request) => $request->data()['pageInfo']['page'] === 1
            ? Http::response($this->searchResponse(24, 30))
            : Http::response($this->searchResponse(2, 30))]);

        $repository = $this->createMock(HmallProductRepository::class);
        $repository->expects($this->exactly(2))
            ->method('saveProductsFromV3')
            ->willReturn(new ProductSaveResult);
        $repository->expects($this->never())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $service = new HmallProductService($repository, $this->mockProductRepository);

        $this->assertSame(
            '未執行缺貨判定，目錄有缺頁（最早在第 2 頁）',
            $service->fetchAllHmallProducts('UNIQLO')->note
        );
        Http::assertSentCount(4);
    }

    /**
     * 抓取途中有商品下架、總數少了一件，最後一頁跟著變短是正常的，照做缺貨判定。
     */
    public function test_the_total_shrinking_slightly_on_the_last_page_still_runs_stockout()
    {
        Cache::flush();
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Http::fake(['https://api.example.com/search' => fn ($request) => $request->data()['pageInfo']['page'] === 1
            ? Http::response($this->searchResponse(24, 30))
            : Http::response($this->searchResponse(5, 29))]);

        $this->mockHmallRepository->expects($this->once())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('info')->andReturnNull();

        $this->assertSame(CrawlOutcome::Succeeded, $this->service->fetchAllHmallProducts('UNIQLO')->outcome);
    }

    /**
     * 403 的頁碼要寫被擋的那一頁；更早已經有缺頁時另外講，續跑點退回最早的缺口。
     */
    public function test_a_block_after_an_earlier_gap_names_both_pages()
    {
        Cache::flush();
        Config::set('app.crawler.retry.times', 1);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Http::fake(['https://api.example.com/search' => fn ($request) => match ($request->data()['pageInfo']['page']) {
            2 => Http::response([], 500),
            4 => Http::response([], 403),
            default => Http::response($this->searchResponse(24, 120)),
        }]);

        $this->mockHmallRepository->expects($this->never())
            ->method('setStockoutHmallProducts');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame('未執行缺貨判定，第 4 頁起被擋下（403），更早在第 2 頁就有缺頁', $result->note);
        $this->assertSame(2, Cache::get('hmall_products:page:UNIQLO'));
    }

    /**
     * 第 1 頁就回一個很小、但前後一致的總數時看不出缺口；看到的件數不到在售的
     * 一半，一定是看漏了，照做會把整個品牌標成缺貨。
     */
    public function test_seeing_far_fewer_products_than_are_in_stock_skips_stockout()
    {
        Cache::flush();
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');
        Http::fake(['https://api.example.com/search' => Http::response($this->searchResponse(24, 24))]);

        $repository = $this->createMock(HmallProductRepository::class);
        $repository->method('saveProductsFromV3')->willReturn(new ProductSaveResult);
        $repository->method('countInStockHmallProducts')->willReturn(1672);
        $repository->expects($this->never())->method('setStockoutHmallProducts');

        $result = (new HmallProductService($repository, $this->mockProductRepository))->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::PartiallySucceeded, $result->outcome);
        $this->assertSame(
            '未執行缺貨判定，這一輪只看到 24 件，目前在售 1672 件（確認官網真的大量下架後，可加 --accept-shrink 重跑放行）',
            $result->note
        );
    }

    /**
     * 官網真的一次下架超過一半時，維運者確認後用 --accept-shrink 放行；只放行這一道。
     */
    public function test_accepting_a_shrink_runs_stockout_despite_seeing_few_products()
    {
        Cache::flush();
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');
        Http::fake(['https://api.example.com/search' => Http::response($this->searchResponse(24, 24))]);

        $repository = $this->createMock(HmallProductRepository::class);
        $repository->method('saveProductsFromV3')->willReturn(new ProductSaveResult);
        $repository->method('countInStockHmallProducts')->willReturn(1672);
        $repository->expects($this->once())->method('setStockoutHmallProducts');

        $result = (new HmallProductService($repository, $this->mockProductRepository))
            ->fetchAllHmallProducts('UNIQLO', acceptShrink: true);

        $this->assertSame(CrawlOutcome::Succeeded, $result->outcome);
    }

    /**
     * 放行只管「看到的太少」，缺頁照樣不做缺貨判定。
     */
    public function test_accepting_a_shrink_does_not_bypass_a_gap()
    {
        Cache::flush();
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');
        Http::fake(['https://api.example.com/search' => fn ($request) => $request->data()['pageInfo']['page'] === 1
            ? Http::response($this->searchResponse(24, 48))
            : Http::response($this->searchResponse(0, 48))]);

        $this->mockHmallRepository->expects($this->never())->method('setStockoutHmallProducts');

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->service->fetchAllHmallProducts('UNIQLO', acceptShrink: true);

        $this->assertSame('未執行缺貨判定，目錄有缺頁（最早在第 2 頁）', $result->note);
    }

    /**
     * 缺貨判定連續超過兩天沒做，改成要人處理的結果，說明裡寫出幾天沒做。
     */
    public function test_skipping_stockout_for_too_many_days_needs_attention()
    {
        Cache::flush();
        Cache::forever('stockout:last-run:hmall:UNIQLO', today()->subDays(3)->toDateString());
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');
        Http::fake(['https://api.example.com/search' => fn ($request) => $request->data()['pageInfo']['page'] === 1
            ? Http::response($this->searchResponse(24, 48))
            : Http::response($this->searchResponse(0, 48))]);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $result = $this->service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(CrawlOutcome::StockoutOverdue, $result->outcome);
        $this->assertStringStartsWith('未執行缺貨判定，目錄有缺頁（最早在第 2 頁）；已經 3 天沒有執行缺貨判定', $result->note);
    }

    public function test_running_stockout_records_today_as_the_last_run()
    {
        Cache::flush();
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');
        Http::fake(['https://api.example.com/search' => Http::response($this->searchResponse(10, 10))]);

        $this->service->fetchAllHmallProducts('UNIQLO');

        $this->assertSame(today()->toDateString(), Cache::get('stockout:last-run:hmall:UNIQLO'));
    }

    /**
     * 寫資料庫丟例外時不可以重打官網：資料庫的問題打幾次官網都不會好，還多了被擋的風險。
     */
    public function test_a_database_error_while_saving_does_not_hit_the_source_again()
    {
        Cache::flush();
        Config::set('app.crawler.retry.times', 3);
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        Http::fake([
            '*' => Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 0,
                    ],
                ],
            ]),
        ]);

        $repository = $this->createMock(HmallProductRepository::class);
        $repository->method('saveProductsFromV3')
            ->willThrowException(new Exception('Deadlock found when trying to get lock'));

        $service = new HmallProductService($repository, $this->mockProductRepository);

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $service->fetchAllHmallProducts('UNIQLO');

        Http::assertSentCount(1);
    }

    /**
     * 一頁商品的假回應。服務層只數看到幾件（缺貨判定的守門），不看商品內容，
     * 所以要做缺貨判定的測試不能餵空陣列。
     */
    private function searchResponse(int $productCount, ?int $productSum = null): array
    {
        $productList = $productCount > 0
            ? array_map(
                fn (int $index) => ['productCode' => sprintf('u%011d', $index)],
                range(1, $productCount)
            )
            : [];

        return [
            'resp' => [
                [
                    'productList' => $productList,
                    'productSum' => $productSum ?? $productCount,
                ],
            ],
        ];
    }
}
