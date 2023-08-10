<?php

namespace App\Services;

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

    public function divideHmallProducts($hmallProducts, $brand = null)
    {
        $groupMapper = [
            ['group' => 'men', 'genders' => ['男裝', '男女適用']],
            ['group' => 'women', 'genders' => ['女裝', '男女適用']],
            ['group' => 'kids', 'genders' => ['童裝', '女童']],
            ['group' => 'baby', 'genders' => ['新生兒/嬰幼兒']],
        ];

        if ($brand === 'UNIQLO' || $brand === 'GU') {
            $hmallProducts = $hmallProducts->where('brand', $brand);
        }

        $groupedHmallProducts = $hmallProducts->groupBy('gender');

        $result = collect($groupMapper)->reduce(function ($carry, $item) use ($groupedHmallProducts) {
            $group = $item['group'];
            $genders = $item['genders'];

            $filteredProducts = $groupedHmallProducts->filter(function ($value, $key) use ($genders) {
                return in_array($key, $genders);
            })->flatten();

            $carry[$group] = $filteredProducts;

            return $carry;
        }, []);

        return $result;
    }
}
