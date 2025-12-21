<?php

namespace App\Services;

use App\Repositories\StyleHintRepository;
use App\Services\Traits\AntiBlockingCrawler;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class StyleHintService extends Service
{
    use AntiBlockingCrawler;

    /** @var StyleHintRepository */
    protected $repository;

    private const CACHE_UGC_SCHEDULING = 'style_hint_ugc:scheduling';

    private const CACHE_UGC_MANUAL_LAST_GENDER = 'style_hint_ugc:manual:%s:last_gender';

    private const CACHE_UGC_MANUAL_LAST_PAGE = 'style_hint_ugc:manual:%s:last_page';

    public function __construct(StyleHintRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Build complete browser headers for the request (customized for StyleHint).
     */
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
        $cacheKey = "style_hint:offset:{$country}";
        $offset = $fresh ? 0 : (Cache::get($cacheKey) ?? 0);
        $total = 0;
        $retry = 0;
        $maxRetry = config('app.crawler.retry.laravel');

        logger()->info("Fetching style hints for {$country}, starting from offset {$offset}");

        do {
            try {
                $response = Http::withHeaders($this->buildHeaders())
                    ->retry($maxRetry, 1000)
                    ->get(config("uniqlo.api.style_hint_list.{$country}"), [
                        'offset' => $offset,
                        'limit' => $limit,
                        'userType' => '0,1,2,3',
                        'order' => 'published_at:desc',
                    ]);

                $responseBody = json_decode($response->body());
                $styleHintSummaries = $responseBody->result->images;
                $this->fetchStyleHintsDetails($country, $styleHintSummaries);

                $total = $responseBody->result->pagination->total;

                // Update checkpoint
                Cache::set($cacheKey, $offset + $limit, now()->addDays(7));

                $offset += $limit;
                $retry = 0;

                // Check if offset batch rest is needed
                if ($this->shouldOffsetBatchRest($offset, $limit)) {
                    $this->doOffsetBatchRest();
                }

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 is a permanent block - stop immediately
                if ($this->is403Error($e)) {
                    Log::error('fetchAllStyleHints blocked (403)', [
                        'country' => $country,
                        'offset' => $offset,
                        'limit' => $limit,
                    ]);
                    report($e);

                    return;
                }

                if ($retry >= $maxRetry) {
                    Log::error('fetchAllStyleHints error - max retry exceeded', [
                        'retry' => $retry,
                        'country' => $country,
                        'limit' => $limit,
                        'offset' => $offset,
                        'status_code' => $e instanceof \Illuminate\Http\Client\RequestException ? $e->response?->status() : 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    report($e);

                    $offset += $limit;
                    $retry = 0;

                    continue;
                }

                $retry++;
                $this->randomSleep();
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
            $this->resetDetailCounter();
            $this->fetchStyleHintsFromUgcByGender($gender, $brand, $onlyRecent, $isManual);
        }

        if (! $isManual) {
            Cache::forever(self::CACHE_UGC_SCHEDULING, false);
        }
    }

    private function fetchStyleHintsDetails($country, $styleHintSummaries)
    {
        $this->resetDetailCounter();
        $styleHintSummaries = collect($styleHintSummaries);

        $existOutfitIds = $this->repository->getExistStyleHintOutfitIds(
            $country,
            $styleHintSummaries->pluck('outfitId')
        );

        $styleHintSummaries = $styleHintSummaries->reject(function ($styleHintSummary) use ($existOutfitIds) {
            $outfitId = $styleHintSummary->outfitId;

            return in_array($outfitId, $existOutfitIds);
        });

        $maxRetry = config('app.crawler.retry.manual');

        $styleHintSummaries->each(function ($styleHintSummary) use ($country, $maxRetry) {
            $retry = 0;

            $outfitId = $styleHintSummary->outfitId;
            $url = config("uniqlo.api.style_hint_detail.{$country}") . "{$outfitId}/details";

            do {
                try {
                    $response = Http::withHeaders($this->buildHeaders())
                        ->retry($maxRetry, 1000)
                        ->get($url, [
                            'type' => 'sh',
                        ]);

                    $responseBody = json_decode($response->body());
                    $result = optional($responseBody)->result;

                    if (is_null($result)) {
                        sleep(1);
                        throw new Exception("Result does not exist. {$response->body()}");
                    }

                    $this->repository->saveStyleHints(
                        $country,
                        $styleHintSummary,
                        $result
                    );

                    $this->detailCounter++;

                    // Check if detail batch rest is needed
                    if ($this->shouldDetailBatchRest()) {
                        $this->doDetailBatchRest();
                    }

                    $retry = 0;

                    $this->randomDelay();
                } catch (Throwable $e) {
                    // 403 is a permanent block - stop immediately
                    if ($this->is403Error($e)) {
                        Log::error('fetchStyleHintsDetails blocked (403)', [
                            'country' => $country,
                            'outfitId' => $outfitId,
                        ]);
                        report($e);

                        throw $e;
                    }

                    if ($retry >= $maxRetry) {
                        Log::error('fetchStyleHintsDetails error - max retry exceeded', [
                            'retry' => $retry,
                            'country' => $country,
                            'outfitId' => $outfitId,
                            'status_code' => $e instanceof \Illuminate\Http\Client\RequestException ? $e->response?->status() : 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                        report($e);

                        return; // Skip this item
                    }

                    $retry++;
                    $this->randomSleep();
                }
            } while ($retry > 0 && $retry <= $maxRetry);
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
        $retry = 0;
        $maxRetry = config('app.crawler.retry.manual');

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
                $headers = $this->buildHeaders();
                $headers['x-fr-clientid'] = $this->getClientId($brand);

                $response = Http::withHeaders($headers)
                    ->retry($maxRetry, 1000)
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
                    sleep(1);
                    throw new Exception("Content list does not exist. {$response->body()}");
                }

                $this->repository->saveStyleHintsFromUgc($contentList, $brand);
                $this->detailCounter += count($contentList);

                // Check if detail batch rest is needed
                if ($this->shouldDetailBatchRest()) {
                    $this->doDetailBatchRest();
                }

                $totalResultCount = $responseBody->total_result_count;

                if ($onlyRecent && $totalResultCount > 10000) {
                    $totalResultCount = 10000;
                }

                $retry = 0;

                $this->randomDelay();

                if ($isManual) {
                    $this->forgetLastManualFetchPage($brand);
                }
            } catch (Throwable $e) {
                // 403 is a permanent block - stop immediately
                if ($this->is403Error($e)) {
                    Log::error('fetchStyleHintsFromUgcByGender blocked (403)', [
                        'brand' => $brand,
                        'style_gender' => $gender,
                        'page' => $page,
                    ]);
                    report($e);

                    return;
                }

                if ($retry >= $maxRetry) {
                    Log::error('fetchStyleHintsFromUgcByGender error - max retry exceeded', [
                        'retry' => $retry,
                        'brand' => $brand,
                        'style_gender' => $gender,
                        'page' => $page,
                        'totalResultCount' => $totalResultCount,
                        'resultLimit' => $resultLimit,
                        'status_code' => $e instanceof \Illuminate\Http\Client\RequestException ? $e->response?->status() : 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    report($e);

                    $retry = 0;

                    continue;
                }

                $retry++;
                $page--;

                $this->randomSleep();
            }
        } while ($totalResultCount >= $page++ * $resultLimit);

        if ($isManual) {
            $this->forgetLastManualFetchGender($brand);
        }
    }

    private function shouldManualFetchContinue(string $gender, $brand = 'UNIQLO'): bool
    {
        if (Cache::get(self::CACHE_UGC_SCHEDULING, true)) {
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
