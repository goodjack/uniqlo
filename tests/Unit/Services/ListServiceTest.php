<?php

namespace Tests\Unit\Services;

use App\Http\Requests\ListRequest;
use App\Models\HmallProduct;
use App\Services\ListService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ListServiceTest extends TestCase
{
    private ListService $listService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listService = app(ListService::class);
    }

    /**
     * @dataProvider productDataProvider
     */
    public function test_groups_hmall_products_correctly(array $productData, array $expectedGroups)
    {
        $products = $this->createProductCollection($productData);

        $groupedProducts = $this->listService->groupHmallProducts($products);

        $this->assertCount(4, $groupedProducts);
        $this->assertArrayHasKey('men', $groupedProducts);
        $this->assertArrayHasKey('women', $groupedProducts);
        $this->assertArrayHasKey('kids', $groupedProducts);
        $this->assertArrayHasKey('baby', $groupedProducts);

        foreach ($expectedGroups as $group => $count) {
            $this->assertCount($count, $groupedProducts[$group]);
        }
    }

    public function test_handles_empty_collection()
    {
        $products = new Collection();

        $groupedProducts = $this->listService->groupHmallProducts($products);

        $this->assertInstanceOf(Collection::class, $groupedProducts);
        $this->assertCount(4, $groupedProducts);
        $this->assertArrayHasKey('men', $groupedProducts);
        $this->assertArrayHasKey('women', $groupedProducts);
        $this->assertArrayHasKey('kids', $groupedProducts);
        $this->assertArrayHasKey('baby', $groupedProducts);
        $this->assertEmpty($groupedProducts['men']);
        $this->assertEmpty($groupedProducts['women']);
        $this->assertEmpty($groupedProducts['kids']);
        $this->assertEmpty($groupedProducts['baby']);
    }

    public function test_sorts_by_price_ascending_when_asked(): void
    {
        $products = $this->createProductCollection([
            ['sex' => '男裝', 'min_price' => '1000.00'],
            ['sex' => '男裝', 'min_price' => '299.00'],
            ['sex' => '男裝', 'min_price' => '90.00'],
        ]);

        $sorted = $this->listService->sortHmallProducts($products, $this->listRequest(['sort' => 'price-asc']));

        // 直接排 min_price 欄位會拿到字典順序（1000、299、90），這裡要的是數值順序
        $this->assertSame([90, 299, 1000], $sorted->map->price->all());
    }

    public function test_keeps_the_original_order_without_a_sort_parameter(): void
    {
        $products = $this->createProductCollection([
            ['sex' => '男裝', 'min_price' => '1000.00'],
            ['sex' => '男裝', 'min_price' => '90.00'],
        ]);

        $sorted = $this->listService->sortHmallProducts($products, $this->listRequest([]));

        $this->assertSame([1000, 90], $sorted->map->price->all());
    }

    /**
     * 男女適穿的商品會同時進男裝與女裝兩個群組，兩邊都要照價格排好。
     */
    public function test_sorting_survives_the_gender_grouping(): void
    {
        $products = $this->createProductCollection([
            ['sex' => '男女適穿', 'min_price' => '1000.00'],
            ['sex' => '女裝', 'min_price' => '500.00'],
            ['sex' => '男女適穿', 'min_price' => '90.00'],
            ['sex' => '男裝', 'min_price' => '300.00'],
        ]);

        $sorted = $this->listService->sortHmallProducts($products, $this->listRequest(['sort' => 'price-asc']));
        $grouped = $this->listService->groupHmallProducts($sorted);

        $this->assertSame([90, 300, 1000], $grouped['men']->map->price->values()->all());
        $this->assertSame([90, 500, 1000], $grouped['women']->map->price->values()->all());
    }

    /**
     * 一個標籤底下的多個條件是「任一成立」，要跟卡片上那個標籤的顯示判準一致。
     * 寫成「全部成立」的話，清單上標著「期間限定」的商品用同一個標籤會篩不到。
     */
    public function test_a_tag_matches_when_any_of_its_conditions_holds(): void
    {
        $products = $this->createProductCollection([
            ['sex' => '男裝', 'identity' => '["time_doptimal"]'],
            ['sex' => '男裝', 'identity' => '["APP"]'],
            ['sex' => '男裝', 'identity' => '["concessional_rate"]'],
        ]);

        $filtered = $this->listService->filterHmallProducts($products, $this->listRequest(['tags' => ['limited-offer']]));

        // 前兩件各符合期間限定的其中一種，第三件是一般特價
        $this->assertCount(2, $filtered);
    }

    public function test_multiple_tags_are_combined_with_or(): void
    {
        $products = $this->createProductCollection([
            ['sex' => '男裝', 'identity' => '["time_doptimal"]'],
            ['sex' => '男裝', 'identity' => '["concessional_rate"]'],
            ['sex' => '男裝', 'identity' => '["new_product"]'],
        ]);

        $filtered = $this->listService->filterHmallProducts(
            $products,
            $this->listRequest(['tags' => ['limited-offer', 'sale']])
        );

        $this->assertCount(2, $filtered);
    }

    /**
     * 「目前史上最低」是篩選專用的判準，跟卡片上那個「歷史新低價」標籤不同：
     * 標籤刻意排除官方標為特價的商品，避免特價期間整頁掛兩個標籤互相干擾，
     * 但使用者想在特價清單裡找的正是那些商品。
     */
    public function test_lowest_price_filter_includes_products_marked_as_on_sale(): void
    {
        $onSaleAtLowest = $this->createProductCollection([
            [
                'sex' => '男裝',
                'identity' => '["concessional_rate"]',
                'min_price' => '790.00',
                'lowest_record_price' => '790.00',
                'highest_record_price' => '990.00',
                'lowest_record_price_count' => 1,
            ],
        ])->first();

        $this->assertTrue($onSaleAtLowest->is_at_lowest_price, '篩選判準要找得到特價中的史上最低');
        $this->assertFalse($onSaleAtLowest->is_new_historical_low, '卡片標籤仍然不重複顯示');
    }

    public function test_a_product_that_never_changed_price_is_not_at_a_low(): void
    {
        $neverChanged = $this->createProductCollection([
            [
                'sex' => '男裝',
                'identity' => '[]',
                'min_price' => '790.00',
                'lowest_record_price' => '790.00',
                'highest_record_price' => '790.00',
            ],
        ])->first();

        $this->assertFalse($neverChanged->is_at_lowest_price);
    }

    private function listRequest(array $input): ListRequest
    {
        return ListRequest::create('/lists/sale', 'GET', $input);
    }

    public static function productDataProvider(): array
    {
        return [
            'group by sex' => [
                [
                    ['sex' => '男女兼用'],
                    ['sex' => '男女通用'],
                    ['sex' => '男女適用'],
                    ['sex' => '男女適穿'],
                    ['sex' => '男女適穿 '],
                    ['sex' => '男性'],
                    ['sex' => '男裝'],
                    ['sex' => '男裝 '],
                    ['sex' => '女裝'],
                    ['sex' => '女裝 '],
                    ['sex' => '女装'],
                    ['sex' => '童裝'],
                    ['sex' => '男童'],
                    ['sex' => '女童'],
                    ['sex' => '嬰幼兒'],
                    ['sex' => '新生兒'],
                    ['sex' => '女嬰幼兒'],
                    ['sex' => '男嬰幼兒'],
                    ['sex' => '女嬰'],
                ],
                [
                    'men' => 8,
                    'women' => 8,
                    'kids' => 3,
                    'baby' => 5,
                ],
            ],
            'group by gender' => [
                [
                    ['sex' => '未知', 'gender' => '男女適用'],
                    ['sex' => '未知', 'gender' => '男裝'],
                    ['sex' => '未知', 'gender' => '女裝'],
                    ['sex' => '未知', 'gender' => '童裝'],
                    ['sex' => '未知', 'gender' => '男童'],
                    ['sex' => '未知', 'gender' => '女童'],
                    ['sex' => '未知', 'gender' => '新生兒/嬰幼兒'],
                    ['sex' => '未知', 'gender' => '嬰幼兒'],
                    ['sex' => '未知', 'gender' => '嬰兒'],
                ],
                [
                    'men' => 2,
                    'women' => 2,
                    'kids' => 3,
                    'baby' => 3,
                ],
            ],
        ];
    }

    private function createProductCollection(array $productData): Collection
    {
        return Collection::make($productData)->map(function ($data) {
            return HmallProduct::unguarded(function () use ($data) {
                return new HmallProduct($data);
            });
        });
    }
}
