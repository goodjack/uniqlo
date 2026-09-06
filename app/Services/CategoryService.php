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
        $ones = $this->repository->getCategoriesWithProducts([CategoryLevel::One]);

        return $ones
            ->filter(fn (HmallCategory $one) => $one->parent_code !== null)
            // 兩家的分類樹各自獨立，同名的 parent_code 要連品牌一起分組
            ->groupBy(fn (HmallCategory $one) => $one->brand->value.':'.$one->parent_code)
            ->map(fn (Collection $children) => [
                // 這裡刻意不用 load('parent')：parent 帶了品牌條件，eager load 只會
                // 拿集合裡第一筆的品牌去套全部，混品牌的集合會整組查不到父分類。
                // 分組後每一組本來就同品牌同 parent_code，取第一筆查一次就夠，
                // 查詢次數是頂層分類數而不是大類數。
                'category' => $children->first()->parent,
                // 兩家的分類名稱會撞（UNIQLO 的「男裝」與 GU 的「MEN」都是男裝），
                // 標上品牌使用者才知道自己在看誰的分類
                'brand' => $children->first()->brand,
                'children' => $children->values(),
            ])
            // 父分類不在主檔裡的（爬蟲只寫到子層）不做成群組，沒有標題可以掛
            ->filter(fn (array $group) => $group['category'] !== null)
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
     * 分類頁上讓人往下鑽的子分類。
     *
     * 只有大類會列出子分類，品項頁一律回空集合。原因是能開頁面的只有 levelOne
     * 與 levelTwo（findPageable 就是這麼定的），所以品項頁列出 levelThree 等於
     * 在畫面上排一整列點下去必然 404 的連結。
     *
     * blade 對空集合已經有 @if，不會留下空白區塊。
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
     * 分類頁不提供品牌篩選：兩家的分類 code 各成一套，一個分類只會有一家的商品，
     * 篩選另一家永遠是空的。品牌直接標在標題上。
     *
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
