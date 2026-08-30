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

        if ($brand === 'UNIQLO' || $brand === 'GU') {
            $hmallProducts = $hmallProducts->where('brand', $brand);
        }

        if (empty($tags)) {
            return $hmallProducts;
        }

        $selectedTags = ProductTag::fromValues($tags);

        if (empty($selectedTags)) {
            return $hmallProducts;
        }

        // 多選是「符合任一條件」：頁面本身已經是基礎集合，標籤只是再縮小範圍
        $hmallProducts = $hmallProducts->filter(
            fn (HmallProduct $hmallProduct) => collect($selectedTags)
                ->contains(fn (ProductTag $tag) => $tag->matches($hmallProduct))
        );

        return $hmallProducts;
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
