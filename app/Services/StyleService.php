<?php

namespace App\Services;

use App\Repositories\StyleRepository;
use App\Services\Traits\AntiBlockingCrawler;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        Log::info("Fetching styles for {$brand}, starting from gender index {$startIndex}");

        for ($i = $startIndex; $i < $genderIds->count(); $i++) {
            $genderId = $genderIds[$i];

            // Save current gender checkpoint
            Cache::set(sprintf(self::CACHE_KEY_STYLE_LAST_GENDER, $brand), $genderId, now()->addDays(7));

            $this->fetchStylesByGenderId($genderId, $brand);

            // Clear gender-specific checkpoint after completion
            Cache::forget(sprintf(self::CACHE_KEY_STYLE_PAGE, $brand, $genderId));
        }

        // Clear all checkpoints on complete success
        Cache::forget(sprintf(self::CACHE_KEY_STYLE_LAST_GENDER, $brand));
        Log::info("Completed fetching styles for {$brand}");
    }

    private function fetchStylesByGenderId(string $genderId, string $brand = 'UNIQLO'): void
    {
        $ugcOfficialStyleListApiUrl = $this->getUgcOfficialStyleListApiUrl($brand);

        $pageSize = 50;
        $cacheKey = sprintf(self::CACHE_KEY_STYLE_PAGE, $brand, $genderId);
        $page = Cache::get($cacheKey) ?? 1;
        $totalStyles = 0;
        $retry = 0;
        $maxRetry = config('app.crawler.retry.manual');

        Log::info("Fetching styles for {$brand} gender {$genderId}, starting from page {$page}");

        do {
            try {
                $headers = $this->buildHeaders();
                $headers['x-fr-clientid'] = $this->getClientId($brand);

                $response = Http::withHeaders($headers)
                    ->retry($maxRetry, 1000)
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
                    $this->randomSleep();
                    throw new Exception("Styles does not exist. {$response->body()}");
                }

                $this->fetchStyleDetails($styles, $brand);

                $totalStyles = data_get($responseBody, 'result.total_styles');

                // Update checkpoint
                Cache::set($cacheKey, $page + 1, now()->addDays(7));

                $retry = 0;

                $this->randomDelay();
            } catch (Throwable $e) {
                // 403 is a permanent block - stop immediately
                if ($this->is403Error($e)) {
                    Log::error('fetchStylesByGenderId blocked (403)', [
                        'brand' => $brand,
                        'gender_id' => $genderId,
                        'page' => $page,
                    ]);
                    report($e);

                    return;
                }

                if ($retry >= $maxRetry) {
                    Log::error('fetchStylesByGenderId error - max retry exceeded', [
                        'retry' => $retry,
                        'gender_id' => $genderId,
                        'page' => $page,
                        'total_styles' => $totalStyles,
                        'page_size' => $pageSize,
                        'brand' => $brand,
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
        } while ($totalStyles >= $page++ * $pageSize);
    }

    private function fetchStyleDetails($styles, string $brand = 'UNIQLO'): void
    {
        $styles = collect($styles);
        $maxRetry = config('app.crawler.retry.manual');

        $styles->each(function ($style) use ($brand, $maxRetry) {
            $ugcOfficialStyleListApiUrl = $this->getUgcOfficialStyleListApiUrl($brand);

            $retry = 0;

            $styleId = $style->style_id;

            do {
                try {
                    $headers = $this->buildHeaders();
                    $headers['x-fr-clientid'] = $this->getClientId($brand);

                    $response = Http::withHeaders($headers)
                        ->retry($maxRetry, 1000)
                        ->get($ugcOfficialStyleListApiUrl . "/{$styleId}", [
                            'content_language' => 'zh-TW',
                            'brand' => ($brand === 'GU') ? 'gu' : 'uq',
                        ]);

                    $result = json_decode($response->getBody())->result;

                    $this->repository->saveStyleFromOfficialStyling($styleId, $result, $brand);

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
                        Log::error('fetchStyleDetails blocked (403)', [
                            'brand' => $brand,
                            'styleId' => $styleId,
                        ]);
                        report($e);

                        return;
                    }

                    if ($retry >= $maxRetry) {
                        Log::error('fetchStyleDetails error - max retry exceeded', [
                            'retry' => $retry,
                            'styleId' => $styleId,
                            'brand' => $brand,
                            'status_code' => $e instanceof \Illuminate\Http\Client\RequestException ? $e->response?->status() : 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                        report($e);

                        $retry = 0;

                        return;
                    }

                    $retry++;

                    $this->randomSleep();
                }
            } while ($retry > 0 && $retry <= $maxRetry);
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
