<?php

namespace App\Services;

use App\Http\Requests\ListRequest;
use App\Repositories\HmallProductRepository;

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

    public function divideHmallProducts($hmallProducts)
    {
        $groupMapper = [
            ['group' => 'men', 'genders' => ['男裝', '男女適用']],
            ['group' => 'women', 'genders' => ['女裝', '男女適用']],
            ['group' => 'kids', 'genders' => ['童裝', '女童']],
            ['group' => 'baby', 'genders' => ['新生兒/嬰幼兒']],
        ];

        $groupedHmallProducts = $hmallProducts->groupBy('gender');

        $result = [];

        foreach ($groupMapper as $item) {
            $group = $item['group'];
            $genders = $item['genders'];

            $filteredProducts = $groupedHmallProducts->filter(function ($value, $key) use ($genders) {
                return in_array($key, $genders);
            })->flatten();

            $result[$group] = $filteredProducts;
        }

        return $result;
    }
}
