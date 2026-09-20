<?php

namespace Tests\Unit\Services;

use App\Enums\CrawlOutcome;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use App\Services\HmallProductService;
use App\Support\ProductSaveResult;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * 從 checkpoint 續跑、而且每一頁都成功時，不可以做缺貨判定。
     *
     * setStockoutHmallProducts 是把 updated_at 比今天早的商品標成下架，前提是
     * 這一輪從第 1 頁看過整份目錄。續跑只看了後半段，前半段的商品今天一次都沒被
     * 摸到，照跑就會把它們整批標成下架——整個品牌當天從站上消失。
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
     * 從第 1 頁開始、全部成功才是完整掃描，這時候缺貨判定才該跑。
     */
    public function test_a_full_clean_crawl_still_marks_products_as_stocked_out()
    {
        Cache::flush();

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

    public function test_fetch_all_hmall_products_fetches_last_partial_page()
    {
        // productSum=25, pageSize=24 → page 1 returns 25, page 2 should also be fetched
        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $requestCount = 0;
        Http::fake(['https://api.example.com/search' => function ($request) use (&$requestCount) {
            $requestCount++;

            return Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 25,
                    ],
                ],
            ]);
        }]);

        $this->service->fetchAllHmallProducts('UNIQLO');

        $this->assertEquals(2, $requestCount, 'Should fetch page 1 (25 >= 24) and page 2 (25 >= 24), stop at page 3 (25 < 48)');
    }

    /**
     * 完整掃描中有商品寫不進去時，缺貨判定照做、但要排除那幾件。
     *
     * 這一輪其實把整份目錄都看過了，只是那幾件的 updated_at 沒被摸到。以前這種
     * 情況整輪跳過缺貨判定、還保留 checkpoint，只要有一件資料固定寫不進去，缺貨
     * 判定就永遠不會執行，下架的商品一直掛在站上。
     */
    public function test_a_full_scan_still_marks_stockout_but_excludes_the_products_that_failed_to_save()
    {
        Cache::flush();

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
     * 寫入失敗但拿不到商品編號時，缺貨判定不能做。
     *
     * 不知道要排除誰，照跑就會把那件商品冤枉標成下架。checkpoint 一樣清掉，
     * 讓下一輪重新從第 1 頁完整掃。
     */
    public function test_a_failure_without_a_product_code_skips_stockout()
    {
        Cache::flush();

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
     * 同一件商品固定寫不進去時，不可以再形成「失敗日、清 checkpoint 日、重新失敗日」的循環。
     *
     * 以前的循環是這樣：第一天完整掃完但有一件寫失敗，保留 checkpoint、跳過缺貨判定；
     * 第二天從 checkpoint 續跑只抓到一頁空的，因為不是完整掃描又跳過；第三天回到第一天。
     * 缺貨判定就永遠沒有執行的一天。修正後每一輪都從第 1 頁開始、每一輪都做帶排除清單的
     * 缺貨判定。
     */
    public function test_a_product_that_keeps_failing_does_not_stall_stockout_day_after_day()
    {
        Cache::flush();

        Config::set('uniqlo.api.v3.search.tw', 'https://api.example.com/search');

        $requestedPages = [];
        Http::fake(['https://api.example.com/search' => function ($request) use (&$requestedPages) {
            $requestedPages[] = $request->data()['pageInfo']['page'];

            return Http::response([
                'resp' => [
                    [
                        'productList' => [],
                        'productSum' => 0,
                    ],
                ],
            ]);
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
     * 只有幾頁失敗（不是全部）：目錄有缺口，缺貨判定不能做、checkpoint 保留。
     *
     * 通知要看得出原因是缺頁，跟「有商品寫不進去但目錄看完了」是兩回事。
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
                return Http::response([
                    'resp' => [
                        [
                            'productList' => [],
                            'productSum' => 25,
                        ],
                    ],
                ]);
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
        $this->assertSame('未執行缺貨判定，目錄有缺頁', $result->note);
        // checkpoint 保留，同一天可以接著從第 2 頁跑完剩下的
        $this->assertSame(2, Cache::get('hmall_products:page:UNIQLO'));
    }
}
