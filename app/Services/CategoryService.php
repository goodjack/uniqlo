<?php

namespace App\Services;

use App\Enums\Brand;
use App\Enums\CategoryLevel;
use App\Enums\ProductTag;
use App\Models\HmallCategory;
use App\Models\HmallProduct;
use App\Repositories\HmallCategoryRepository;
use App\Repositories\HmallProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CategoryService extends Service
{
    /**
     * 再多就從導覽變成雜訊。
     */
    private const CATEGORY_LINKS_ON_PRODUCT_PAGE = 5;

    /**
     * 商品性別對應的頂層分類 code。兩家的頂層 code 與名稱各成一套，只能用
     * code 對照；對不到的性別就不套性別條件。
     */
    private const GENDER_TOP_CATEGORIES = [
        '男裝' => ['all_men', 'men_all'],
        '女裝' => ['all_women', 'women_all', 'specialsize_w'],
        '童裝' => ['all_kids', 'kids_all'],
        '男童' => ['all_kids', 'kids_all'],
        '女童' => ['all_kids', 'kids_all'],
        '新生兒/嬰幼兒' => ['all_baby'],
    ];

    /**
     * 往上回溯的次數上限（樹最深四層）。parent_code 來自爬蟲，萬一兜成環
     * 也不會無窮迴圈。
     */
    private const MAX_CATEGORY_DEPTH = 5;

    /** @var HmallCategoryRepository */
    protected $repository;

    protected $hmallProductRepository;

    public function __construct(
        HmallCategoryRepository $repository,
        HmallProductRepository $hmallProductRepository
    ) {
        $this->repository = $repository;
        $this->hmallProductRepository = $hmallProductRepository;
    }

    /**
     * 分類總覽：頂層當標題，底下掛大類。品項層有三百多個，攤在同一頁反而
     * 找不到東西，留給各大類頁面。
     *
     * @return Collection<int, array{category: HmallCategory, children: Collection<int, HmallCategory>}>
     */
    public function getOverview(): Collection
    {
        $ones = $this->repository->getCategoriesWithProducts([CategoryLevel::One]);

        return $ones
            ->filter(fn (HmallCategory $one) => $one->parent_code !== null)
            ->groupBy(fn (HmallCategory $one) => $one->brand->value.':'.$one->parent_code)
            ->map(fn (Collection $children) => [
                // 不能用 load('parent')：parent 關聯帶品牌條件，eager load 只會拿
                // 第一筆的品牌套用全部，混品牌時整組查不到父分類。
                'category' => $children->first()->parent,
                'brand' => $children->first()->brand,
                'children' => $children->values(),
            ])
            ->filter(fn (array $group) => $group['category'] !== null)
            // 先 UNIQLO（站的主體）再 GU；品牌內大類多的群組排前面，男裝、女裝
            // 這類主要入口才不會被只有一兩個大類的小群組擠到後面
            ->sortBy([
                fn (array $a, array $b) => ($a['brand'] === Brand::Uniqlo ? 0 : 1)
                    <=> ($b['brand'] === Brand::Uniqlo ? 0 : 1),
                fn (array $a, array $b) => $b['children']->count() <=> $a['children']->count(),
                fn (array $a, array $b) => $a['category']->code <=> $b['category']->code,
            ])
            ->values();
    }

    /**
     * @return Collection<int, HmallCategory>
     */
    public function getBreadcrumb(HmallCategory $category): Collection
    {
        $trail = collect([$category]);
        $current = $category;

        for ($depth = 0; $depth < self::MAX_CATEGORY_DEPTH && $current->parent !== null; $depth++) {
            $current = $current->parent;
            $trail->prepend($current);
        }

        return $trail;
    }

    public function findPageable(Brand $brand, string $code): ?HmallCategory
    {
        return $this->repository->findPageable($brand, $code);
    }

    /**
     * 商品頁麵包屑要走的主分類。官方沒有給主分類，規則是自己定的：
     *
     * 一、取品項層（levelTwo），對「找同類商品」最有用。
     * 二、只認跟商品性別相符的樹，否則常會選到「熱門推薦」這類促銷樹
     *     （官方給它的 sort 常常最小）。
     * 三、同層多個取官方 sort 最小的。
     *
     * 找不到就依序放寬到大類、再放寬成不管性別。
     */
    public function getPrimaryCategory(HmallProduct $product): ?HmallCategory
    {
        $categories = $product->categories;
        // 商品身上掛的是完整的四層，父分類查得到，不必再回資料庫
        $byCode = $categories->keyBy('code');
        $topCodes = self::GENDER_TOP_CATEGORIES[$product->gender] ?? null;

        foreach ([true, false] as $matchGender) {
            foreach ([CategoryLevel::Two, CategoryLevel::One] as $level) {
                $found = $categories
                    ->filter(fn (HmallCategory $category) => $category->level === $level)
                    ->filter(fn (HmallCategory $category) => ! $matchGender
                        || $topCodes === null
                        || in_array($this->topCodeOf($category, $byCode), $topCodes, true))
                    // sort 是長度不一的數字字串（008004001008004009），照數值比會挑錯
                    ->sortBy(fn (HmallCategory $category) => (string) $category->pivot->sort, SORT_STRING)
                    ->first();

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * 男女適穿的商品在男裝、女裝樹下各掛一份同名分類，同名只留一個。
     *
     * @return Collection<int, HmallCategory>
     */
    public function getCategoryLinksForProductPage(HmallProduct $product): Collection
    {
        return $this->hmallProductRepository
            ->getCategoriesForProductPage($product)
            ->unique('name')
            ->take(self::CATEGORY_LINKS_ON_PRODUCT_PAGE);
    }

    /**
     * @param  Collection<string, HmallCategory>  $byCode
     */
    private function topCodeOf(HmallCategory $category, Collection $byCode): ?string
    {
        $current = $category;

        for ($depth = 0; $depth < self::MAX_CATEGORY_DEPTH; $depth++) {
            if ($current->parent_code === null) {
                return $current->code;
            }

            $current = $byCode->get($current->parent_code);

            if ($current === null) {
                return null;
            }
        }

        return null;
    }

    /**
     * 只有大類列子分類：levelThree 沒有頁面，列出來點下去必然 404。
     *
     * @return Collection<int, HmallCategory>
     */
    public function getChildren(HmallCategory $category): Collection
    {
        if ($category->level !== CategoryLevel::One) {
            return collect();
        }

        return $this->repository->getCategoriesWithProducts(
            [CategoryLevel::Two],
            $category->code,
            $category->brand
        );
    }

    public function hasAvailableProducts(HmallCategory $category): bool
    {
        return $this->repository->hasAvailableProducts($category->id);
    }

    /**
     * 不提供品牌篩選：一個分類只會有一家的商品。
     *
     * @param  array<int, ProductTag>  $tags
     */
    public function getProducts(HmallCategory $category, array $tags = [], ?string $q = null): LengthAwarePaginator
    {
        return $this->hmallProductRepository->getProductsByCategoryId(
            $category->id,
            $tags,
            HmallProductRepository::PRODUCTS_PER_PAGE,
            $q
        );
    }
}
