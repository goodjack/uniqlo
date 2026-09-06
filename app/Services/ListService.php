<?php

namespace App\Services;

use App\Enums\ProductTag;
use App\Http\Requests\ListRequest;
use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ListService extends Service
{
    public const SORT_PRICE_ASC = 'price-asc';

    /** @var HmallProductRepository */
    protected $repository;

    public function __construct(HmallProductRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getLimitedOfferHmallProducts()
    {
        return $this->repository->getLimitedOfferHmallProducts();
    }

    public function getSaleHmallProducts()
    {
        return $this->repository->getSaleHmallProducts();
    }

    public function getMostReviewedHmallProducts()
    {
        return $this->repository->getMostReviewedHmallProducts();
    }

    public function getJapanMostReviewedHmallProducts()
    {
        return $this->repository->getJapanMostReviewedHmallProducts();
    }

    public function getTopWearingHmallProducts()
    {
        return $this->repository->getTopWearingHmallProducts();
    }

    public function getNewHmallProducts()
    {
        return $this->repository->getNewHmallProducts();
    }

    public function getComingSoonHmallProducts()
    {
        return $this->repository->getComingSoonHmallProducts();
    }

    public function getMultiBuyHmallProducts()
    {
        return $this->repository->getMultiBuyHmallProducts();
    }

    public function getOnlineSpecialHmallProducts()
    {
        return $this->repository->getOnlineSpecialHmallProducts();
    }

    public function getMostVisitedHmallProducts(?int $limit = null)
    {
        return $this->repository->getMostVisitedHmallProducts()->take($limit);
    }

    public function filterHmallProducts($hmallProducts, ListRequest $listRequest)
    {
        $brand = $listRequest->input('brand');
        $tags = $listRequest->input('tags') ?? [];
        $q = $listRequest->input('q');

        if ($brand === 'UNIQLO' || $brand === 'GU') {
            $hmallProducts = $hmallProducts->where('brand', $brand);
        }

        $selectedTags = empty($tags) ? [] : ProductTag::fromValues($tags);

        if (! empty($selectedTags)) {
            // 多選是「符合任一條件」：頁面本身已經是基礎集合，標籤只是再縮小範圍
            $hmallProducts = $hmallProducts->filter(
                fn (HmallProduct $hmallProduct) => collect($selectedTags)
                    ->contains(fn (ProductTag $tag) => $tag->matches($hmallProduct))
            );
        }

        if (filled($q)) {
            $hmallProducts = $this->filterHmallProductsByKeyword($hmallProducts, $q);
        }

        return $hmallProducts;
    }

    /**
     * 清單內搜尋：品名或編號含關鍵字，多個空白分開的詞要全部命中。
     *
     * 這裡篩的是清單頁預熱好的 Collection，跟分類頁走資料庫 LIKE 查詢
     * （HmallProductRepository::getProductsByCategoryId()）是兩條不同的路——
     * 清單本來就是每天算好放進快取的小集合，不值得為它另開一次資料庫查詢。
     */
    private function filterHmallProductsByKeyword(Collection $hmallProducts, string $q): Collection
    {
        $keywords = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($keywords as $keyword) {
            $hmallProducts = $hmallProducts->filter(
                fn (HmallProduct $hmallProduct) => $this->hmallProductMatchesKeyword($hmallProduct, $keyword)
            );
        }

        return $hmallProducts;
    }

    private function hmallProductMatchesKeyword(HmallProduct $hmallProduct, string $keyword): bool
    {
        foreach ([$hmallProduct->name, $hmallProduct->code, $hmallProduct->product_code] as $field) {
            if ($field !== null && mb_stripos((string) $field, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 依使用者選的軸重新排序，沒選就維持各清單原本的排序。
     *
     * 排序要在篩選之後、分組之前。分組用的是 groupBy，它照輸入順序把商品放進
     * 各性別群組，所以先整體排序、群組內仍然是遞增的。
     */
    public function sortHmallProducts(Collection $hmallProducts, ListRequest $listRequest): Collection
    {
        if ($listRequest->input('sort') !== self::SORT_PRICE_ASC) {
            return $hmallProducts;
        }

        // 用 price 這個 accessor 而不是 min_price 欄位：那欄是 decimal，
        // PDO 取回來是字串，直接排會變字典順序，「1000.00」會排在「299.00」前面
        return $hmallProducts->sortBy('price')->values();
    }

    public function groupHmallProducts(Collection $hmallProducts): Collection
    {
        $groupedProducts = $hmallProducts->groupBy(function (HmallProduct $product) {
            return $this->determineProductGroup($product);
        });

        $allGroups = ['men', 'women', 'kids', 'baby'];

        return collect($allGroups)->mapWithKeys(function ($group) use ($groupedProducts) {
            return [$group => $groupedProducts->get($group, collect())];
        });
    }

    private function determineProductGroup(HmallProduct $product): array
    {
        return $this->getSexGroups($product->sex) ?? $this->getGenderGroups($product->gender);
    }

    private function getSexGroups(string $sex): ?array
    {
        $sexMappings = [
            '童' => ['kids'],
            '嬰' => ['baby'],
            '新生兒' => ['baby'],
            '男女' => ['men', 'women'],
            '男' => ['men'],
            '女' => ['women'],
        ];

        foreach ($sexMappings as $keyword => $groups) {
            if (Str::contains($sex, $keyword)) {
                return $groups;
            }
        }

        return null;
    }

    private function getGenderGroups(string $gender): array
    {
        $genderMappings = [
            '男裝' => ['men'],
            '女裝' => ['women'],
            '男女適用' => ['men', 'women'],
            '童裝' => ['kids'],
            '女童' => ['kids'],
            '男童' => ['kids'],
            '新生兒/嬰幼兒' => ['baby'],
            '嬰幼兒' => ['baby'],
            '嬰兒' => ['baby'],
        ];

        return $genderMappings[$gender] ?? [];
    }
}
