<?php

namespace App\Services;

use App\Repositories\StyleHintRepository;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class StyleHintService extends Service
{
    /** @var StyleHintRepository */
    protected $repository;

    public function __construct(StyleHintRepository $repository)
    {
        $this->repository = $repository;
    }

    public function fetchAllStyleHints(string $country)
    {
        $limit = 50;
        $offset = 0;
        $total = 0;
        $retry = 0;

        do {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                ])
                    ->retry(5, 1000)
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

                $offset += $limit;
                $retry = 0;

                usleep(500000);
            } catch (Throwable $e) {
                if ($retry >= 5) {
                    Log::error('fetchAllStyleHints error', [
                        'retry' => $retry,
                        'country' => $country,
                        'limit' => $limit,
                        'offset' => $offset,
                        'response_body' => $response->body() ?? null,
                    ]);
                    report($e);

                    $offset += $limit;
                    $retry = 0;

                    continue;
                }

                $retry++;

                sleep(1);
            }
        } while ($total >= $offset);
    }

    public function fetchAllStyleHintsFromUgc(string $brand = 'UNIQLO', bool $onlyRecent = true): void
    {
        $genders = collect([
            '1', // MEN
            '2', // WOMEN
            '3', // KIDS
            '4', // BABY
            '5',
        ]);

        $genders->each(function ($gender) use ($brand, $onlyRecent) {
            return $this->fetchStyleHintsFromUgcByGender($gender, $brand, $onlyRecent);
        });
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
            $retry = 0;

            $outfitId = $styleHintSummary->outfitId;
            $url = config("uniqlo.api.style_hint_detail.{$country}") . "{$outfitId}/details";

            do {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => config('app.user_agent_mobile'),
                    ])
                        ->retry(5, 1000)
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

                    $retry = 0;

                    usleep(500000);
                } catch (Throwable $e) {
                    if ($retry >= 5) {
                        Log::error('fetchStyleHintsDetails error', [
                            'retry' => $retry,
                            'country' => $country,
                            'outfitId' => $outfitId,
                        ]);
                        report($e);
                    }

                    $retry++;

                    sleep(1);
                }
            } while ($retry > 0 && $retry <= 5);
        });
    }

    private function fetchStyleHintsFromUgcByGender(
        string $gender,
        string $brand = 'UNIQLO',
        bool $onlyRecent = true,
    ): void {
        $ugcStyleHintListApiUrl = $this->getUgcStyleHintListApiUrl($brand);

        $resultLimit = 50;
        $page = 1;
        $totalResultCount = 0;
        $retry = 0;

        do {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                    'x-fr-clientid' => $this->getClientId($brand),
                ])
                    ->retry(5, 1000)
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

                collect($contentList)->each(function ($content) use ($country) {
                    $this->repository->saveStyleHintsFromUgc(
                        $country,
                        $content
                    );
                });

                $totalResultCount = $responseBody->total_result_count;

                if ($onlyRecent && $totalResultCount > 10000) {
                    $totalResultCount = 10000;
                }

                $retry = 0;

                usleep(100000);
            } catch (Throwable $e) {
                if ($retry >= 5) {
                    Log::error('fetchStyleHintsFromUgcByGender error', [
                        'retry' => $retry,
                        'country' => $country,
                        'style_gender' => $gender,
                        'page' => $page,
                        'totalResultCount' => $totalResultCount,
                        'resultLimit' => $resultLimit,
                    ]);
                    report($e);

                    $retry = 0;

                    continue;
                }

                $retry++;
                $page--;

                sleep(1);
            }
        } while ($totalResultCount >= $page++ * $resultLimit);
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
