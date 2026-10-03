<?php

namespace App\Repositories;

use App\Enums\Brand;
use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HmallCategoryRepository extends Repository
{
    protected $model;

    public function __construct(HmallCategory $model)
    {
        $this->model = $model;
    }

    /**
     * 取出還有商品在售的分類，附上商品數，商品多的排前面。
     *
     * 分類主檔只增不減，所有對外查詢都經過在售商品過濾，不另外清理。
     * 帶 $parentCode 時要一起帶 $brand：分類的身分是品牌加 code。
     *
     * @param  array<int, CategoryLevel>  $levels
     * @return Collection<int, HmallCategory>
     */
    public function getCategoriesWithProducts(
        array $levels,
        ?string $parentCode = null,
        ?Brand $brand = null
    ): Collection {
        return $this->withAvailableProducts()
            ->select('hmall_categories.*')
            ->selectRaw('COUNT(DISTINCT hmall_products.id) as hmall_products_count')
            ->when($parentCode !== null, fn ($query) => $query->where('hmall_categories.parent_code', $parentCode))
            ->when($brand !== null, fn ($query) => $query->where('hmall_categories.brand', $brand->value))
            ->whereIn('hmall_categories.level', array_map(fn (CategoryLevel $level) => $level->value, $levels))
            ->groupBy('hmall_categories.id')
            ->orderByDesc('hmall_products_count')
            ->orderBy('hmall_categories.code')
            ->get();
    }

    /**
     * 找出可以開頁面的分類。只認 levelOne 與 levelTwo：頂層太廣（「全商品」等於
     * 整個站），levelThree 的名稱像「男裝/男女適穿」，當頁面標題不成句。
     */
    public function findPageable(Brand $brand, string $code): ?HmallCategory
    {
        return $this->model
            ->where('brand', $brand->value)
            ->where('code', $code)
            ->whereIn('level', [CategoryLevel::One->value, CategoryLevel::Two->value])
            ->first();
    }

    /**
     * 分類頁的 404 看這個，不看套完篩選後有沒有結果：篩選後為空不該變 404。
     */
    public function hasAvailableProducts(int $categoryId): bool
    {
        return $this->withAvailableProducts()
            ->where('hmall_categories.id', $categoryId)
            ->exists();
    }

    private function withAvailableProducts(): Builder
    {
        return $this->model
            ->join(
                'hmall_category_hmall_product as category_pivot',
                'category_pivot.hmall_category_id',
                '=',
                'hmall_categories.id'
            )
            ->join('hmall_products', 'hmall_products.id', '=', 'category_pivot.hmall_product_id')
            ->where('hmall_products.stock', 'Y')
            ->whereNull('hmall_products.stockout_at');
    }
}
