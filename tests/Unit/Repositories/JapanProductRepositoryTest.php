<?php

namespace Tests\Unit\Repositories;

use App\Models\JapanProduct;
use App\Repositories\JapanProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JapanProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_listed_again_is_no_longer_marked_as_stocked_out(): void
    {
        $repository = app(JapanProductRepository::class);
        $repository->saveProducts([$this->item()]);
        JapanProduct::where('l1Id', '483870')->update(['stockout_at' => now()->subDays(3)]);

        $repository->saveProducts([$this->item()]);

        $this->assertNull(JapanProduct::where('l1Id', '483870')->firstOrFail()->stockout_at);
    }

    /**
     * 欄位格式跟資料庫回傳的一致（評分 4.5000），重存時沒有任何欄位會變。
     */
    private function item(): object
    {
        return json_decode(json_encode([
            'l1Id' => '483870',
            'productId' => 'E483870-000',
            'name' => '牛津襯衫',
            'genderCategory' => 'WOMEN',
            'rating' => ['average' => '4.5000', 'count' => 12],
            'priceGroup' => '00',
            'prices' => ['base' => ['value' => 2990]],
            'images' => ['main' => [], 'sub' => []],
        ]));
    }
}
