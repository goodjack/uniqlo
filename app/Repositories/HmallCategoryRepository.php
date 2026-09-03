<?php

namespace App\Repositories;

use App\Enums\Brand;
use App\Enums\CategoryLevel;
use App\Models\HmallCategory;
use Illuminate\Support\Collection;

class HmallCategoryRepository extends Repository
{
    protected $model;

    public function __construct(HmallCategory $model)
    {
        $this->model = $model;
    }

    /**
     * 取出還有商品在售的分類，附上商品數。
     *
     * 分類主檔只增不減——爬蟲觀測到就寫進去，商品全部下架後那個分類仍留在表裡。
     * 與其定期清理，不如讓所有對外查詢都經過在售商品過濾：分類頁、導覽與 sitemap
     * 就不會出現點進去空無一物的頁面，而且反映的是當下而不是上次清理時的狀態。
     *
     * 帶 $parentCode 時一定要一起帶 $brand：分類的身分是品牌加 code，光靠
     * parent_code 會把另一家同名分類的子分類也撈進來。
     *
     * @param  array<int, CategoryLevel>  $levels
     * @return Collection<int, HmallCategory>
     */
    public function getCategoriesWithProducts(
        array $levels,
        ?string $parentCode = null,
        ?Brand $brand = null
    ): Collection {
        return $this->model
            ->select('hmall_categories.*')
            ->selectRaw('COUNT(DISTINCT hmall_products.id) as hmall_products_count')
            ->when($parentCode !== null, fn ($query) => $query->where('hmall_categories.parent_code', $parentCode))
            ->when($brand !== null, fn ($query) => $query->where('hmall_categories.brand', $brand->value))
            ->join(
                'hmall_category_hmall_product as category_pivot',
                'category_pivot.hmall_category_id',
                '=',
                'hmall_categories.id'
            )
            ->join('hmall_products', 'hmall_products.id', '=', 'category_pivot.hmall_product_id')
            ->whereIn('hmall_categories.level', array_map(fn (CategoryLevel $level) => $level->value, $levels))
            ->where('hmall_products.stock', 'Y')
            ->whereNull('hmall_products.stockout_at')
            ->groupBy('hmall_categories.id')
            ->orderBy('hmall_categories.code')
            ->get();
    }

    /**
     * 找出可以開頁面的分類，找不到就回 null 讓呼叫端出 404。
     *
     * 分類的身分是品牌加 code，兩者都要對上。只認 levelOne 與 levelTwo：
     * 頂層太廣（「全商品」等於整個站），levelThree 是官方的錨點細分、
     * 名稱像「男裝/男女適穿」，當成頁面標題不成句。
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
     * 這個分類目前還有沒有買得到的商品。
     *
     * 分類頁的 404 要看這個，不能看「套完篩選後有沒有結果」——那會讓
     * 有商品但篩選後為空的情況也變成 404。
     */
    public function hasAvailableProducts(int $categoryId): bool
    {
        return $this->model
            ->join(
                'hmall_category_hmall_product as category_pivot',
                'category_pivot.hmall_category_id',
                '=',
                'hmall_categories.id'
            )
            ->join('hmall_products', 'hmall_products.id', '=', 'category_pivot.hmall_product_id')
            ->where('hmall_categories.id', $categoryId)
            ->where('hmall_products.stock', 'Y')
            ->whereNull('hmall_products.stockout_at')
            ->exists();
    }
}
