<?php

namespace Tests\Unit\Services;

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
