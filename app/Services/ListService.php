<?php

namespace App\Services;

use App\Enums\ProductTag;
use App\Http\Requests\ListRequest;
use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use App\Support\Keywords;
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

        if ($brand !== null) {
            $hmallProducts = $hmallProducts->where('brand', $brand);
        }

        $selectedTags = ProductTag::fromValues($tags);

        if (! empty($selectedTags)) {
            // 多選是聯集：符合任一標籤即顯示
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
     * 清單內搜尋。比對欄位要跟 public/js/list-search.js 的即時篩選一致。
     */
    private function filterHmallProductsByKeyword(Collection $hmallProducts, string $q): Collection
    {
        foreach (Keywords::split($q) as $keyword) {
            $hmallProducts = $hmallProducts->filter(
                fn (HmallProduct $hmallProduct) => $this->hmallProductMatchesKeyword($hmallProduct, $keyword)
            );
        }

        return $hmallProducts;
    }

    private function hmallProductMatchesKeyword(HmallProduct $hmallProduct, string $keyword): bool
    {
        // short_product_code 是卡片上顯示的編號，使用者照畫面抄的多半是它
        $fields = [
            $hmallProduct->name,
            $hmallProduct->code,
            $hmallProduct->product_code,
            $hmallProduct->short_product_code,
        ];

        foreach ($fields as $field) {
            if ($field !== null && mb_stripos((string) $field, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 要在分組之前排：groupBy 保留輸入順序，群組內才會跟著有序。
     */
    public function sortHmallProducts(Collection $hmallProducts, ListRequest $listRequest): Collection
    {
        if ($listRequest->input('sort') !== self::SORT_PRICE_ASC) {
            return $hmallProducts;
        }

        // min_price 是 decimal，PDO 取回字串，直接排會變字典順序
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
