<?php

namespace App\Services;

use App\Http\Requests\ListRequest;
use App\Models\HmallProduct;
use App\Repositories\HmallProductRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ListService extends Service
{
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

        $tagMappings = [
            'limited-offer' => ['is_limited_offer', 'is_app_offer', 'is_ec_only'],
            'app-offer' => ['is_app_offer'],
            'ec-only' => ['is_ec_only'],
            'sale' => ['is_sale'],
            'new' => ['is_new'],
            'coming-soon' => ['is_coming_soon'],
            'multi-buy' => ['is_multi_buy'],
            'online-special' => ['is_online_special'],
            'stockout' => ['is_stockout'],
        ];

        $hmallProducts = $hmallProducts->filter(function ($hmallProduct) use ($tags, $tagMappings) {
            foreach ($tags as $tag) {
                if (isset($tagMappings[$tag])) {
                    $conditions = $tagMappings[$tag];
                    $match = true;

                    foreach ($conditions as $condition) {
                        if (! $hmallProduct->$condition) {
                            $match = false;
                            break;
                        }
                    }

                    if ($match) {
                        return true;
                    }
                }
            }

            return false;
        });

        return $hmallProducts;
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

    public function getMostVisitedHmallProducts(int $limit = null)
    {
        return $this->repository->getMostVisitedHmallProducts()->take($limit);
    }
}
