<?php

namespace Tests\Unit\Repositories;

use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HmallProductDetailRefetchSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_listing_as_yesterday_leaves_the_detail_alone(): void
    {
        $this->repository()->saveProductsFromV3($this->listing());
        $this->createDetails();

        $this->repository()->saveProductsFromV3($this->listing());

        $this->assertSame([null, null], $this->staleSince());
    }

    public static function listingChanges(): array
    {
        return [
            'price' => ['minPrice', 590],
            'color' => ['colorNums', ['COL05']],
            'size' => ['size', ['SMA001']],
            'stock' => ['stock', 'N'],
            'first picture' => ['mainPic', '/hmall/test/u0000000053204/main/first/561/9.jpg'],
        ];
    }

    #[DataProvider('listingChanges')]
    public function test_a_changed_price_color_size_or_stock_marks_the_detail_for_refetch(string $field, mixed $value): void
    {
        $this->repository()->saveProductsFromV3($this->listing());
        $this->createDetails();

        $products = $this->listing();
        $products[0]->{$field} = $value;
        $this->repository()->saveProductsFromV3($products);

        [$changed, $untouched] = $this->staleSince();
        $this->assertNotNull($changed);
        $this->assertNull($untouched);
    }

    public function test_a_restocked_product_is_marked_for_refetch(): void
    {
        $this->repository()->saveProductsFromV3($this->listing());
        $this->createDetails();
        HmallProduct::where('product_code', 'u0000000053204')->update(['stockout_at' => now()->subDays(3)]);

        $this->repository()->saveProductsFromV3($this->listing());

        $this->assertNotNull($this->staleSince()[0]);
    }

    public function test_an_already_marked_detail_keeps_its_original_time(): void
    {
        $this->freezeSecond();
        $this->repository()->saveProductsFromV3($this->listing());
        $this->createDetails(['stale_since' => now()->subDays(2)->startOfSecond()]);
        $products = $this->listing();
        $products[0]->minPrice = 590;

        $this->repository()->saveProductsFromV3($products);

        $this->assertEquals(now()->subDays(2)->startOfSecond(), $this->staleSince()[0]);
    }

    private function createDetails(array $attributes = []): void
    {
        HmallProduct::orderBy('product_code')->get()->each(fn (HmallProduct $product) => $product->detail()->create($attributes + [
            'fetched_at' => now(),
            'next_fetch_at' => now()->addDays(30),
        ]));
    }

    /**
     * @return array<int, \Carbon\Carbon|null>
     */
    private function staleSince(): array
    {
        return HmallProduct::with('detail')->orderBy('product_code')->get()
            ->map(fn (HmallProduct $product) => $product->detail->stale_since)
            ->all();
    }

    private function listing(): array
    {
        return json_decode(file_get_contents(base_path('tests/stubs/hmall-search-v3-response.json')))->resp[0]->productList;
    }

    private function repository(): HmallProductRepository
    {
        return app(HmallProductRepository::class);
    }
}
