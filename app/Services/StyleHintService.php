<?php

namespace App\Services;

use App\Repositories\StyleHintRepository;
use App\Services\Traits\AntiBlockingCrawler;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class StyleHintService extends Service
{
    use AntiBlockingCrawler;

    /** @var StyleHintRepository */
    protected $repository;

    private const CACHE_KEY_STYLE_HINT_OFFSET = 'style_hint:offset:%s';

    private const CACHE_UGC_SCHEDULING = 'style_hint_ugc:scheduling';

    private const CACHE_UGC_MANUAL_LAST_GENDER = 'style_hint_ugc:manual:%s:last_gender';

    private const CACHE_UGC_MANUAL_LAST_PAGE = 'style_hint_ugc:manual:%s:last_page';

    public function __construct(StyleHintRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Build complete browser headers for the request.
     *
     * Overrides trait default because StyleHint API requires:
     * - Mobile origin (m.uniqlo.com) instead of www.uniqlo.com
     * - appCheck header for app-review bypass
     * - langCode header for Traditional Chinese content
     */
    // TODO: Headers are hardcoded for Taiwan site (zh_TW, m.uniqlo.com).
    // If fetchAllStyleHints() is used for non-TW countries, these should be parameterized.
    private function buildHeaders(): array
    {
        return [
            'User-Agent' => $this->getRandomUserAgent(),
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'appCheck' => 'for-app-review',
            'langCode' => 'zh_TW',
            'Origin' => 'https://m.uniqlo.com',
            'Referer' => 'https://m.uniqlo.com/',
        ];
    }

    public function fetchAllStyleHints(string $country, bool $fresh = false)
    {
        $limit = 50;
        $cacheKey = sprintf(self::CACHE_KEY_STYLE_HINT_OFFSET, $country);

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $offset = $fresh ? 0 : (Cache::get($cacheKey) ?? 0);
        $total = 0;

        logger()->info("Fetching style hints for {$country}, starting from offset {$offset}");

        $this->resetDetailCounter();

        do {
            try {
                [$styleHintSummaries, $total] = retry(
                    config('app.crawler.retry.times'),
                    function ($attempts) use ($country, $offset, $limit) {
                        $response = Http::withHeaders($this->buildHeaders())
                            ->throw()
                            ->get(config("uniqlo.api.style_hint_list.{$country}"), [
                                'offset' => $offset,
                                'limit' => $limit,
                                'userType' => '0,1,2,3',
                                'order' => 'published_at:desc',
                            ]);

                        $responseBody = json_decode($response->body());

                        $images = data_get($responseBody, 'result.images');
                        $total = data_get($responseBody, 'result.pagination.total');

                        if (is_null($images) || is_null($total)) {
                            throw new Exception("Missing required fields in response. {$response->body()}");
                        }

                        return [$images, $total];
                    },
                    fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                    fn ($e) => $this->shouldRetry($e),
                );

                $this->fetchStyleHintsDetails($country, $styleHintSummaries);

                // Update checkpoint
                Cache::put($cacheKey, $offset + $limit, now()->addDays(7));

                $offset += $limit;

                // Check if offset batch rest is needed
                if ($this->shouldOffsetBatchRest($offset, $limit)) {
                    $this->doOffsetBatchRest();
                }

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 is a permanent block - stop immediately
                if ($this->is403Error($e)) {
                    logger()->error('fetchAllStyleHints blocked (403)', [
                        'country' => $country,
                        'offset' => $offset,
                        'limit' => $limit,
                    ]);
                    report($e);

                    return;
                }

                // retry() exhausted - skip batch and continue
                logger()->error('fetchAllStyleHints error - max retry exceeded', [
                    'country' => $country,
                    'limit' => $limit,
                    'offset' => $offset,
                    'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                    'error' => $e->getMessage(),
                ]);
                report($e);

                $offset += $limit;
            }
        } while ($total >= $offset);

        // Clear checkpoint on successful completion
        Cache::forget($cacheKey);
        logger()->info("Completed fetching style hints for {$country}");
    }

    public function fetchAllStyleHintsFromUgc(
        string $brand = 'UNIQLO',
        bool $onlyRecent = false,
        bool $isManual = true,
        bool $fresh = false,
    ): void {
        $genders = [
            '1', // MEN
            '2', // WOMEN
            '3', // KIDS
            '4', // BABY
            '5',
        ];

        // Clear checkpoints if fresh start
        if ($fresh) {
            Cache::forget(sprintf(self::CACHE_UGC_MANUAL_LAST_PAGE, $brand));
            Cache::forget(sprintf(self::CACHE_UGC_MANUAL_LAST_GENDER, $brand));
        }

        if (! $isManual) {
            Cache::forever(self::CACHE_UGC_SCHEDULING, true);
        }

        foreach ($genders as $gender) {
            $this->resetDetailCounter(); // Reset per-gender — each gender has independent batch rest quota

            try {
                $this->fetchStyleHintsFromUgcByGender($gender, $brand, $onlyRecent, $isManual);
            } catch (Throwable $e) {
                // 403 propagated from inner method - clean up and stop
                if ($this->is403Error($e)) {
                    if (! $isManual) {
                        Cache::forever(self::CACHE_UGC_SCHEDULING, false);
                    }

                    return;
                }
                // Other errors: skip gender (already logged)
            }
        }

        if (! $isManual) {
            Cache::forever(self::CACHE_UGC_SCHEDULING, false);
        }
    }

    private function fetchStyleHintsDetails($country, $styleHintSummaries)
    {
        $styleHintSummaries = collect($styleHintSummaries);

        $existOutfitIds = $this->repository->getExistStyleHintOutfitIds(
            $country,
            $styleHintSummaries->pluck('outfitId')
        );

        $styleHintSummaries = $styleHintSummaries->reject(function ($styleHintSummary) use ($existOutfitIds) {
            $outfitId = $styleHintSummary->outfitId;

            return in_array($outfitId, $existOutfitIds);
        });

        $styleHintSummaries->each(function ($styleHintSummary) use ($country) {
            $outfitId = $styleHintSummary->outfitId;
            $url = config("uniqlo.api.style_hint_detail.{$country}") . "{$outfitId}/details";

            try {
                retry(
                    config('app.crawler.retry.times'),
                    function ($attempts) use ($url, $country, $styleHintSummary, $outfitId) {
                        $response = Http::withHeaders($this->buildHeaders())
                            ->throw()
                            ->get($url, [
                                'type' => 'sh',
                            ]);

                        $responseBody = json_decode($response->body());
                        $result = optional($responseBody)->result;

                        if (is_null($result)) {
                            throw new Exception("Result does not exist. {$response->body()}");
                        }

                        $this->repository->saveStyleHints(
                            $country,
                            $styleHintSummary,
                            $result
                        );
                    },
                    fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                    fn ($e) => $this->shouldRetry($e),
                );

                $this->detailCounter++;

                // Check if detail batch rest is needed
                if ($this->shouldDetailBatchRest()) {
                    $this->doDetailBatchRest();
                }

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 is a permanent block - propagate to stop Collection::each
                if ($this->is403Error($e)) {
                    logger()->error('fetchStyleHintsDetails blocked (403)', [
                        'country' => $country,
                        'outfitId' => $outfitId,
                    ]);
                    report($e);

                    throw $e;
                }

                // retry() exhausted - skip this item
                logger()->error('fetchStyleHintsDetails error - max retry exceeded', [
                    'country' => $country,
                    'outfitId' => $outfitId,
                    'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        });
    }

    private function fetchStyleHintsFromUgcByGender(
        string $gender,
        string $brand = 'UNIQLO',
        bool $onlyRecent = false,
        bool $isManual = true,
    ): void {
        $ugcStyleHintListApiUrl = $this->getUgcStyleHintListApiUrl($brand);

        $resultLimit = 50;
        $page = 1;
        $totalResultCount = 0;

        do {
            if ($isManual) {
                if (! $this->shouldManualFetchContinue($gender, $brand)) {
                    return;
                }
                $page = $this->getLastManualFetchPage($page, $brand);

                $totalPage = ceil($totalResultCount / $resultLimit);
                logger()->info("Fetching UGC for {$brand} gender {$gender}: page {$page}/{$totalPage}");
            }

            try {
                [$totalResultCount, $contentCount] = retry(
                    config('app.crawler.retry.times'),
                    function ($attempts) use ($ugcStyleHintListApiUrl, $brand, $gender, $page, $resultLimit, $onlyRecent) {
                        $headers = $this->buildHeaders();
                        $headers['x-fr-clientid'] = $this->getClientId($brand);

                        $response = Http::withHeaders($headers)
                            ->throw()
                            ->get($ugcStyleHintListApiUrl, [
                                'style_gender' => [$gender],
                                'order' => 'published_at:desc',
                                'result_limit' => $resultLimit,
                                'page' => $page,
                                'priority_flag' => 'true',
                                'brand' => $brand === 'GU' ? 'gu' : 'uq',
                            ]);

                        $responseBody = json_decode($response->getBody());
                        $contentList = optional($responseBody)->content_list;

                        if (is_null($contentList)) {
                            throw new Exception("Content list does not exist. {$response->body()}");
                        }

                        $this->repository->saveStyleHintsFromUgc($contentList, $brand);

                        $total = $responseBody->total_result_count;

                        if ($onlyRecent && $total > 10000) {
                            $total = 10000;
                        }

                        return [$total, count($contentList)];
                    },
                    fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                    fn ($e) => $this->shouldRetry($e),
                );

                $this->detailCounter += $contentCount;

                // Check if detail batch rest is needed
                if ($this->shouldDetailBatchRest()) {
                    $this->doDetailBatchRest();
                }

                $this->randomDelay();

                if ($isManual) {
                    $this->forgetLastManualFetchPage($brand);
                }
            } catch (Throwable $e) {
                // 403 is a permanent block - propagate upward
                if ($this->is403Error($e)) {
                    logger()->error('fetchStyleHintsFromUgcByGender blocked (403)', [
                        'brand' => $brand,
                        'style_gender' => $gender,
                        'page' => $page,
                    ]);
                    report($e);

                    throw $e;
                }

                // retry() exhausted - skip page and continue
                logger()->error('fetchStyleHintsFromUgcByGender error - max retry exceeded', [
                    'brand' => $brand,
                    'style_gender' => $gender,
                    'page' => $page,
                    'totalResultCount' => $totalResultCount,
                    'resultLimit' => $resultLimit,
                    'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }

            $page++;
        } while ($totalResultCount >= ($page - 1) * $resultLimit);

        if ($isManual) {
            $this->forgetLastManualFetchGender($brand);
        }
    }

    private function shouldManualFetchContinue(string $gender, $brand = 'UNIQLO'): bool
    {
        if (Cache::get(self::CACHE_UGC_SCHEDULING, false)) {
            logger()->info('Manual fetch is stopped because of scheduling.');

            return false;
        }

        return $gender === Cache::rememberForever(
            sprintf(self::CACHE_UGC_MANUAL_LAST_GENDER, $brand),
            fn () => $gender,
        );
    }

    private function getLastManualFetchPage(int $page, $brand = 'UNIQLO'): int
    {
        return Cache::rememberForever(
            sprintf(self::CACHE_UGC_MANUAL_LAST_PAGE, $brand),
            fn () => $page,
        );
    }

    private function forgetLastManualFetchPage($brand = 'UNIQLO'): void
    {
        Cache::forget(sprintf(self::CACHE_UGC_MANUAL_LAST_PAGE, $brand));
    }

    private function forgetLastManualFetchGender($brand = 'UNIQLO'): void
    {
        Cache::forget(sprintf(self::CACHE_UGC_MANUAL_LAST_GENDER, $brand));
    }

    private function getUgcStyleHintListApiUrl(string $brand = 'UNIQLO')
    {
        if ($brand === 'GU') {
            return config('gu.api.ugc_style_hint_list.tw');
        }

        return config('uniqlo.api.ugc_style_hint_list.tw');
    }

    private function getClientId($brand = 'UNIQLO')
    {
        if ($brand === 'GU') {
            return 'gutw-sth-sb-proxy';
        }

        return 'uqtw-sth-sb-proxy';
    }
}
