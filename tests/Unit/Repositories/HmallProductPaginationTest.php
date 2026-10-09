<?php

namespace Tests\Unit\Repositories;

use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 排序鍵同分時，MySQL 在 LIMIT/OFFSET 下不保證每頁的順序一致，翻頁會重複或
 * 漏掉商品。每支分頁查詢最後都要有唯一鍵。
 */
class HmallProductPaginationTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCT_COUNT = 140;

    private const PER_PAGE = 24;

    public function test_category_pages_list_every_product_exactly_once_despite_ties(): void
    {
        $category = HmallCategory::create([
            'brand' => 'UNIQLO',
            'code' => 'all_women-shirts',
            'name' => '襯衫',
            'parent_code' => 'all_women',
            'level' => CategoryLevel::Two->value,
        ]);

        $products = $this->createProductsInTiedPairs();

        // 同 code 的商品 sort 也相同，排序鍵整組同分
        $products->shuffle(7)->each(
            fn (HmallProduct $product) => $product->categories()->attach(
                $category->id,
                ['sort' => sprintf('%03d', (int) $product->code % 7)]
            )
        );

        $repository = app(HmallProductRepository::class);

        $this->assertPagesListEveryProductOnce(
            $products,
            fn () => $repository->getProductsByCategoryId($category->id, perPage: self::PER_PAGE)
        );
    }

    public function test_keyword_search_pages_list_every_product_exactly_once_despite_ties(): void
    {
        $products = $this->createProductsInTiedPairs();
        $repository = app(HmallProductRepository::class);

        $this->assertPagesListEveryProductOnce(
            $products,
            fn () => $repository->searchByKeywords(['同分'], perPage: self::PER_PAGE)
        );
    }

    /**
     * 清單快取不分頁，但首頁只取前幾件；同分時取到哪幾件不能每次重建都不同。
     */
    public function test_list_caches_order_ties_by_newest_id(): void
    {
        $ids = collect(range(1, 3))->map(
            fn (int $index) => HmallProduct::unguarded(fn () => HmallProduct::create([
                'brand' => 'UNIQLO',
                'name' => '同分特價',
                'code' => '483870',
                'product_code' => sprintf('u%011d', $index),
                'identity' => '["concessional_rate"]',
                'stock' => 'Y',
                'min_price' => 590,
                'highest_record_price' => 990,
                'evaluation_count' => 10,
                'score' => 4.5,
                'created_at' => '2026-10-01 00:00:00',
            ]))->id
        );

        app(HmallProductRepository::class)->setSaleHmallProductsCache();

        $this->assertSame($ids->reverse()->values()->all(), Cache::get('hmall_product:sale')->pluck('id')->all());
    }

    /**
     * 每兩件 code 與評價相同，彼此只差 id。
     *
     * @return Collection<int, HmallProduct>
     */
    private function createProductsInTiedPairs(): Collection
    {
        return collect(range(1, self::PRODUCT_COUNT))->shuffle(11)->map(
            fn (int $index) => HmallProduct::unguarded(fn () => HmallProduct::create([
                'brand' => 'UNIQLO',
                'name' => '同分襯衫',
                'code' => (string) (483000 + intdiv($index, 2) % 20),
                'product_code' => sprintf('u%011d', $index),
                'identity' => '[]',
                'stock' => 'Y',
                'min_price' => 990,
                'evaluation_count' => intdiv($index, 2) % 5,
                'score' => 4.5,
            ]))
        );
    }

    /**
     * @param  callable(): LengthAwarePaginator  $currentPage
     */
    private function assertPagesListEveryProductOnce(Collection $products, callable $currentPage): void
    {
        $seen = collect(range(1, (int) ceil(self::PRODUCT_COUNT / self::PER_PAGE)))
            ->flatMap(function (int $page) use ($currentPage) {
                Paginator::currentPageResolver(fn () => $page);

                return $currentPage()->getCollection()->pluck('id');
            });

        $this->assertSame(
            $products->pluck('id')->sort()->values()->all(),
            $seen->sort()->values()->all()
        );
    }
}
