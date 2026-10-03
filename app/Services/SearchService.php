<?php

namespace App\Services;

use App\Repositories\HmallProductRepository;
use App\Support\Keywords;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SearchService extends Service
{
    /**
     * 每多一個詞就多一輪全表掃描；超過的詞會告訴使用者沒用到。
     */
    private const MAX_KEYWORDS = 5;

    /** @var HmallProductRepository */
    protected $repository;

    public function __construct(HmallProductRepository $repository)
    {
        $this->repository = $repository;
    }

    public function searchHmallProducts(string $query): LengthAwarePaginator
    {
        return $this->repository->searchByKeywords(
            $this->tokenize($query),
            HmallProductRepository::PRODUCTS_PER_PAGE
        );
    }

    /**
     * code 精準符合，或 name 裡以獨立數字段落出現這組號碼（共用貨號）。
     *
     * @return Collection<int, \App\Models\HmallProduct>
     */
    public function findHmallProductsByCodeOrSharedNumber(string $query): Collection
    {
        return $this->repository->findHmallProductsByCodeOrSharedNumber($query);
    }

    /**
     * @return array<int, string>
     */
    public function ignoredKeywords(string $query): array
    {
        return array_slice(Keywords::split($query), self::MAX_KEYWORDS);
    }

    /**
     * @return array<int, string>
     */
    public function tokenize(string $query): array
    {
        return array_slice(Keywords::split($query), 0, self::MAX_KEYWORDS);
    }
}
