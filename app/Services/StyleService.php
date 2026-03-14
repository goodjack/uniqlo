<?php

namespace App\Services;

use App\Repositories\StyleRepository;
use App\Services\Traits\AntiBlockingCrawler;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class StyleService extends Service
{
    use AntiBlockingCrawler;

    /** @var StyleRepository */
    protected $repository;

    private const CACHE_KEY_STYLE_PAGE = 'styles:page:%s:%s'; // brand:gender

    private const CACHE_KEY_STYLE_LAST_GENDER = 'styles:last_gender:%s'; // brand

    public function __construct(StyleRepository $repository)
    {
        $this->repository = $repository;
    }

    public function fetchAllStyles($brand = 'UNIQLO', bool $fresh = false): void
    {
        $genderIds = collect([
            '1', // MEN
            '2', // WOMEN
            '3', // KIDS
            '4', // BABY
        ]);

        // Clear all checkpoints if fresh
        if ($fresh) {
            foreach ($genderIds as $genderId) {
                Cache::forget(sprintf(self::CACHE_KEY_STYLE_PAGE, $brand, $genderId));
            }
            Cache::forget(sprintf(self::CACHE_KEY_STYLE_LAST_GENDER, $brand));
        }

        // Resume from last gender if checkpoint exists
        $lastGender = Cache::get(sprintf(self::CACHE_KEY_STYLE_LAST_GENDER, $brand));
        $startIndex = $lastGender ? $genderIds->search($lastGender) : 0;

        $this->resetDetailCounter();

        logger()->info("Fetching styles for {$brand}, starting from gender index {$startIndex}");

        for ($i = $startIndex; $i < $genderIds->count(); $i++) {
            $genderId = $genderIds[$i];

            // Save current gender checkpoint
            Cache::put(sprintf(self::CACHE_KEY_STYLE_LAST_GENDER, $brand), $genderId, now()->addDays(7));

            try {
                $genderCompleted = $this->fetchStylesByGenderId($genderId, $brand);
            } catch (Throwable $e) {
                // 403 propagated from inner method - stop all genders
                if ($this->is403Error($e)) {
                    return;
                }
                // Other errors: skip gender, preserve page checkpoint for resumption
                $genderCompleted = false;
            }

            // Only clear page checkpoint if gender completed without errors
            if ($genderCompleted) {
                Cache::forget(sprintf(self::CACHE_KEY_STYLE_PAGE, $brand, $genderId));
            }
        }

        // Clear all checkpoints on complete success
        Cache::forget(sprintf(self::CACHE_KEY_STYLE_LAST_GENDER, $brand));
        logger()->info("Completed fetching styles for {$brand}");
    }

    /**
     * @return bool true if all pages completed without errors
     */
    private function fetchStylesByGenderId(string $genderId, string $brand = 'UNIQLO'): bool
    {
        $ugcOfficialStyleListApiUrl = $this->getUgcOfficialStyleListApiUrl($brand);

        $pageSize = (int) config('app.crawler.page_sizes.official_styles');
        if ($pageSize < 1) {
            throw new Exception('CRAWLER_OFFICIAL_STYLES_PAGE_SIZE is not configured.');
        }

        $cacheKey = sprintf(self::CACHE_KEY_STYLE_PAGE, $brand, $genderId);
        $page = Cache::get($cacheKey) ?? 1;
        $totalStyles = 0;
        $hasErrors = false;

        logger()->info("Fetching styles for {$brand} gender {$genderId}, starting from page {$page}");

        do {
            try {
                [$styles, $totalStyles] = retry(
                    config('app.crawler.retry.times'),
                    function ($attempts) use ($ugcOfficialStyleListApiUrl, $brand, $genderId, $page, $pageSize) {
                        $headers = $this->buildHeaders();
                        $headers['x-fr-clientid'] = $this->getClientId($brand);

                        $response = Http::withHeaders($headers)
                            ->throw()
                            ->get($ugcOfficialStyleListApiUrl, [
                                'gender_id' => $genderId,
                                'order' => 'display_start_at:desc',
                                'page_size' => $pageSize,
                                'page' => $page,
                                'brand' => ($brand === 'GU') ? 'gu' : 'uq',
                            ]);

                        $responseBody = json_decode($response->getBody());
                        $styles = data_get($responseBody, 'result.styles');

                        if (is_null($styles)) {
                            throw new Exception("Styles does not exist. {$response->body()}");
                        }

                        return [
                            $styles,
                            data_get($responseBody, 'result.total_styles'),
                        ];
                    },
                    fn ($attempts, $e) => $this->getRetrySleepMilliseconds($attempts, $e),
                    fn ($e) => $this->shouldRetry($e),
                );

                $this->fetchStyleDetails($styles, $brand);

                // Update checkpoint
                Cache::put($cacheKey, $page + 1, now()->addDays(7));

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 is a permanent block - propagate upward
                if ($this->is403Error($e)) {
                    logger()->error('fetchStylesByGenderId blocked (403)', [
                        'brand' => $brand,
                        'gender_id' => $genderId,
                        'page' => $page,
                    ]);
                    report($e);

                    throw $e;
                }

                // retry() exhausted - skip page and continue
                $hasErrors = true;
                logger()->error('fetchStylesByGenderId error - max retry exceeded', [
                    'gender_id' => $genderId,
                    'page' => $page,
                    'total_styles' => $totalStyles,
                    'page_size' => $pageSize,
                    'brand' => $brand,
                    'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }

            $page++;
        } while ($totalStyles >= ($page - 1) * $pageSize);

        return ! $hasErrors;
    }

    private function fetchStyleDetails($styles, string $brand = 'UNIQLO'): void
    {
        $styles = collect($styles);

        $styles->each(function ($style) use ($brand) {
            $ugcOfficialStyleListApiUrl = $this->getUgcOfficialStyleListApiUrl($brand);

            $styleId = $style->style_id;

            try {
                retry(
                    config('app.crawler.retry.times'),
                    function ($attempts) use ($ugcOfficialStyleListApiUrl, $brand, $styleId) {
                        $headers = $this->buildHeaders();
                        $headers['x-fr-clientid'] = $this->getClientId($brand);

                        $response = Http::withHeaders($headers)
                            ->throw()
                            ->get($ugcOfficialStyleListApiUrl . "/{$styleId}", [
                                'content_language' => 'zh-TW',
                                'brand' => ($brand === 'GU') ? 'gu' : 'uq',
                            ]);

                        $result = json_decode($response->getBody())->result;

                        $this->repository->saveStyleFromOfficialStyling($styleId, $result, $brand);
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
                    logger()->error('fetchStyleDetails blocked (403)', [
                        'brand' => $brand,
                        'styleId' => $styleId,
                    ]);
                    report($e);

                    throw $e;
                }

                // retry() exhausted - skip this style
                logger()->error('fetchStyleDetails error - max retry exceeded', [
                    'styleId' => $styleId,
                    'brand' => $brand,
                    'status_code' => $e instanceof RequestException ? $e->response?->status() : 'unknown',
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        });
    }

    private function getUgcOfficialStyleListApiUrl($brand = 'UNIQLO'): string
    {
        if ($brand === 'GU') {
            return config('gu.api.ugc_official_style_list.tw');
        }

        return config('uniqlo.api.ugc_official_style_list.tw');
    }

    private function getClientId($brand = 'UNIQLO'): string
    {
        if ($brand === 'GU') {
            return 'gutw-sth-sb-proxy';
        }

        return 'uqtw-sth-sb-proxy';
    }
}
