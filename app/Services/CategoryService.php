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
    private const PRODUCTS_PER_PAGE = 24;

    /**
     * 商品的性別對應到哪些頂層分類。
     *
     * 兩家的頂層 code 各成一套（UNIQLO 是 all_men、GU 是 men_all），名稱也不同
     * （男裝／MEN），所以用 code 對照而不是比名稱。值是本機資料庫實際存在的
     * 頂層分類；對不到的性別（包含空字串）就不套性別條件。
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
     * 分類樹最深四層，往上回溯的次數上限。資料是爬蟲寫的，parent_code 萬一
     * 兜成環，沒有上限就會轉不出來。
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
            /*
             * 先 UNIQLO 再 GU，各自的群組照官方 code 排。
             *
             * 品牌不能照字串排：'GU' < 'UNIQLO'，GU 會排在前面，但這個站的主體是
             * UNIQLO（商品數是 GU 的好幾倍），使用者打開總覽第一眼該看到它。
             */
            ->sortBy(fn (array $group) => sprintf(
                '%d:%s',
                $group['brand'] === Brand::Uniqlo ? 0 : 1,
                $group['category']->code
            ))
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
     * 這件商品的主分類，也就是麵包屑要走的那一條路徑。
     *
     * 一件商品平均掛十幾個分類，官方沒有給主分類，所以規則是自己定的：
     *
     * 一、取品項層（levelTwo），它比大類具體、對「找同類商品」最有用。
     * 二、只認性別跟商品自己相符的那幾棵樹。少了這一條會選到「熱門推薦」——
     *     那是促銷用的樹，官方給它的 sort 又常常最小。實測 u0000000055090
     *     （女裝 HEATTECH）掛的四個品項裡 sort 最小的就是「熱門推薦 › 週週
     *     新品一覽 › 女裝 新品一覽」，那不是使用者想回去逛的地方。
     * 三、同一層有多個就取官方 sort 最小的。sort 是官方回傳的排序字串
     *     （008004001008004009 這種），照字串比，不要轉成數字。
     *
     * 找不到符合性別的就退一步：先放寬到大類（levelOne），再放寬成不管性別。
     * 掛得到分類就給得出麵包屑，比整條退成「首頁 › 商品」有用。
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
                    // SORT_STRING 不能省：PHP 的 <=> 對兩個數字字串是照數值比，
                    // 而 sort 是長度不一的官方排序字串（008004001008004009 對
                    // 014001999），照數值比會挑錯——實測就是這樣選到後面那個。
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
     * 從一個分類往上回溯到頂層，回傳頂層的 code。中途斷掉就回 null。
     *
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
    public function getProducts(HmallCategory $category, array $tags = [], ?string $q = null): LengthAwarePaginator
    {
        return $this->hmallProductRepository->getProductsByCategoryId(
            $category->id,
            $tags,
            self::PRODUCTS_PER_PAGE,
            $q
        );
    }
}
