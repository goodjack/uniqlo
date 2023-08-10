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

    public function divideHmallProducts($hmallProducts)
    {
        $groupMapper = [
            ['group' => 'men', 'gender' => '男裝'],
            ['group' => 'men', 'gender' => '男女適用'],
            ['group' => 'women', 'gender' => '女裝'],
            ['group' => 'women', 'gender' => '男女適用'],
            ['group' => 'kids', 'gender' => '童裝'],
            ['group' => 'kids', 'gender' => '女童'],
            ['group' => 'baby', 'gender' => '新生兒/嬰幼兒'],
        ];

        $groupedHmallProducts = $hmallProducts->groupBy(function ($hmallProduct) {
            return $hmallProduct->gender;
        });

        $result = collect($groupMapper)->reduce(function ($carry, $item) use ($groupedHmallProducts) {
            if (! isset($carry[$item['group']])) {
                $carry[$item['group']] = collect([]);
            }

            $carry[$item['group']] = $carry[$item['group']]->merge($groupedHmallProducts->get($item['gender']));

            return $carry;
        }, []);

        return $result;
    }
}
