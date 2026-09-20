<?php

namespace App\Services;

use App\Repositories\HmallProductRepository;
use Illuminate\Support\Collection;

class FavoriteService extends Service
{
    /** @var HmallProductRepository */
    protected $repository;

    public function __construct(HmallProductRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * 用一批商品編號換回對應的商品，順序跟著收藏清單走。
     *
     * @param  array<int, array{brand: string, code: string}>  $items
     * @return Collection<int, \App\Models\HmallProduct>
     */
    public function getHmallProductsByBrandAndCodes(array $items): Collection
    {
        return $this->repository->getByBrandAndProductCodes($items);
    }
}
