<?php

namespace App\Services;

use App\Enums\Brand;
use App\Enums\CategoryLevel;
use App\Enums\ProductTag;
use App\Models\HmallCategory;
use App\Repositories\HmallCategoryRepository;
use App\Repositories\HmallProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CategoryService extends Service
{
    private const PRODUCTS_PER_PAGE = 24;

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
     * 分類總覽的內容：頂層當標題，底下掛它的大類。
     *
     * 只到大類為止。品項那層有三百多個，全部攤在同一頁反而找不到東西，
     * 它們列在各自大類的頁面裡。
     *
     * @return Collection<int, array{category: HmallCategory, children: Collection<int, HmallCategory>}>
     */
    public function getOverview(): Collection
    {
        // 頂層自己有沒有掛到商品不重要，有還在售的大類就該出現在總覽上
        $ones = $this->repository
            ->getCategoriesWithProducts([CategoryLevel::One])
            ->load('parent');

        return $ones
            ->filter(fn (HmallCategory $one) => $one->parent !== null)
            // 兩家的分類樹各自獨立，同名的 parent_code 要連品牌一起分組
            ->groupBy(fn (HmallCategory $one) => $one->brand->value.':'.$one->parent_code)
            ->map(fn (Collection $children) => [
                'category' => $children->first()->parent,
                // 兩家的分類名稱會撞（UNIQLO 的「男裝」與 GU 的「MEN」都是男裝），
                // 標上品牌使用者才知道自己在看誰的分類
                'brand' => $children->first()->brand,
                'children' => $children->values(),
            ])
            ->sortBy(fn (array $group) => $group['brand']->value.':'.$group['category']->code)
            ->values();
    }

    /**
     * 從自己往上回溯到根，做成麵包屑。
     *
     * 分類樹每一層只有一個父分類，所以這條路徑是唯一的。深度上限是防呆——
     * 資料是爬蟲寫的，萬一 parent_code 兜成環就會轉不出來。
     *
     * @return Collection<int, HmallCategory>
     */
    public function getBreadcrumb(HmallCategory $category): Collection
    {
        $trail = collect([$category]);
        $current = $category;

        for ($depth = 0; $depth < 5 && $current->parent !== null; $depth++) {
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
     * @return Collection<int, HmallCategory>
     */
    public function getChildren(HmallCategory $category): Collection
    {
        return $this->repository->getCategoriesWithProducts(
            [CategoryLevel::Two, CategoryLevel::Three],
            $category->code
        );
    }

    public function hasAvailableProducts(HmallCategory $category): bool
    {
        return $this->repository->hasAvailableProducts($category->id);
    }

    /**
     * 分類頁不提供品牌篩選：兩家的分類 code 各成一套，一個分類只會有一家的商品，
     * 篩選另一家永遠是空的。品牌直接標在標題上。
     */
    /**
     * @param  array<int, ProductTag>  $tags
     */
    public function getProducts(HmallCategory $category, array $tags = []): LengthAwarePaginator
    {
        return $this->hmallProductRepository->getProductsByCategoryId(
            $category->id,
            $tags,
            self::PRODUCTS_PER_PAGE
        );
    }
}
