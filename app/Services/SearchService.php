<?php

namespace App\Services;

use App\Repositories\HmallProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SearchService extends Service
{
    /**
     * 一次查詢最多吃幾個關鍵字。
     *
     * 每多一個詞就多一輪全表掃描，而超過五個詞的查詢幾乎不會有結果，
     * 擋在這裡也順便擋掉用長字串灌查詢的情況。
     */
    private const MAX_KEYWORDS = 5;

    private const RESULTS_PER_PAGE = 24;

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
            self::RESULTS_PER_PAGE
        );
    }

    /**
     * 用數字查詢找商品：code 精準符合，或者 name 裡以獨立數字段落出現這組號碼。
     *
     * 搜尋首頁（單一命中直接導向商品頁）與查詢字串結果頁（列出所有命中）
     * 共用同一條查詢，判準要一致，見 HmallProductRepository::findHmallProductsByCodeOrSharedNumber()。
     *
     * @return Collection<int, \App\Models\HmallProduct>
     */
    public function findHmallProductsByCodeOrSharedNumber(string $query): Collection
    {
        return $this->repository->findHmallProductsByCodeOrSharedNumber($query);
    }

    /**
     * 超過上限而沒有被用到的關鍵字。
     *
     * @return array<int, string>
     */
    public function ignoredKeywords(string $query): array
    {
        return array_slice($this->splitKeywords($query), self::MAX_KEYWORDS);
    }

    /**
     * 用空白切詞，每個詞都要命中才算符合。
     *
     * 全形空白也算：從手機輸入法打中文很容易帶到。
     *
     * @return array<int, string>
     */
    public function tokenize(string $query): array
    {
        return array_slice($this->splitKeywords($query), 0, self::MAX_KEYWORDS);
    }

    /**
     * @return array<int, string>
     */
    private function splitKeywords(string $query): array
    {
        return collect(preg_split('/[\s\x{3000}]+/u', trim($query)) ?: [])
            ->filter(fn ($keyword) => $keyword !== '')
            ->values()
            ->all();
    }
}
