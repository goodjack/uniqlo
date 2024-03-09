<?php

namespace App\Services;

use App\Repositories\StyleRepository;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class StyleService extends Service
{
    /** @var StyleRepository */
    protected $repository;

    public function __construct(StyleRepository $repository)
    {
        $this->repository = $repository;
    }

    public function fetchAllStyles($brand = 'UNIQLO'): void
    {
        $genderIds = collect([
            '1', // MEN
            '2', // WOMEN
            '3', // KIDS
            '4', // BABY
        ]);

        $genderIds->each(function ($genderId) use ($brand) {
            return $this->fetchStylesByGenderId($genderId, $brand);
        });
    }

    private function fetchStylesByGenderId(string $genderId, string $brand = 'UNIQLO'): void
    {
        $ugcOfficialStyleListApiUrl = $this->getUgcOfficialStyleListApiUrl($brand);

        $pageSize = 50;
        $page = 1;
        $totalStyles = 0;
        $retry = 0;

        do {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                    'x-fr-clientid' => $this->getClientId($brand),
                ])
                    ->retry(5, 1000)
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
                    sleep(1);
                    throw new Exception("Styles does not exist. {$response->body()}");
                }

                $this->fetchStyleDetails($styles, $brand);

                $totalStyles = data_get($responseBody, 'result.total_styles');

                $retry = 0;

                usleep(500000);
            } catch (Throwable $e) {
                if ($retry >= 5) {
                    Log::error('fetchStyleHintsFromUgcByGender error', [
                        'retry' => $retry,
                        'gender_id' => $genderId,
                        'page' => $page,
                        'total_styles' => $totalStyles,
                        'page_size' => $pageSize,
                        'brand' => $brand,
                    ]);
                    report($e);

                    $retry = 0;

                    continue;
                }

                $retry++;
                $page--;

                sleep(1);
            }
        } while ($totalStyles >= $page++ * $pageSize);
    }

    private function fetchStyleDetails($styles, string $brand = 'UNIQLO'): void
    {
        $styles = collect($styles);

        $styles->each(function ($style) use ($brand) {
            $ugcOfficialStyleListApiUrl = $this->getUgcOfficialStyleListApiUrl($brand);

            $retry = 0;

            $styleId = $style->style_id;

            do {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => config('app.user_agent_mobile'),
                        'x-fr-clientid' => $this->getClientId($brand),
                    ])
                        ->retry(5, 1000)
                        ->get($ugcOfficialStyleListApiUrl . "/{$styleId}", [
                            'content_language' => 'zh-TW',
                            'brand' => ($brand === 'GU') ? 'gu' : 'uq',
                        ]);

                    $result = json_decode($response->getBody())->result;

                    $this->repository->saveStyleFromOfficialStyling($styleId, $result, $brand);

                    $retry = 0;

                    usleep(500000);
                } catch (Throwable $e) {
                    if ($retry >= 5) {
                        Log::error('fetchStyleDetails error', [
                            'retry' => $retry,
                            'styleId' => $styleId,
                            'brand' => $brand,
                        ]);
                        report($e);
                    }

                    $retry++;

                    sleep(1);
                }
            } while ($retry > 0 && $retry <= 5);
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
