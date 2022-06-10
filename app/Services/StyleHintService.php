<?php

namespace App\Services;

use App\Repositories\StyleHintRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use Yish\Generators\Foundation\Service\Service;

class StyleHintService extends Service
{
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

                $responseBody = json_decode($response->getBody());
                $styleHintSummaries = $responseBody->result->images;
                $this->fetchStyleHintsDetails($country, $styleHintSummaries);

                $total = $responseBody->result->pagination->total;

                $offset += $limit;

                $retry = 0;

                sleep(1);
            } catch (Throwable $e) {
                Log::error('fetchAllStyleHints error', [
                    'retry' => $retry,
                    'country' => $country,
                    'limit' => $limit,
                    'offset' => $offset,
                ]);
                report($e);

                if ($retry >= 5) {
                    $retry = 0;
                    continue;
                }

                $retry++;

                sleep(1);
            }
        } while ($total >= $offset);
    }

    private function fetchStyleHintsDetails($country, $styleHintSummaries)
    {
        $styleHintSummaries = collect($styleHintSummaries);
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
                            'type' => 'sh'
                        ]);

                    $result = json_decode($response->getBody())->result;
                    $this->repository->saveStyleHints(
                        $country,
                        $styleHintSummary,
                        $result
                    );

                    $retry = 0;

                    sleep(1);
                } catch (Throwable $e) {
                    Log::error('fetchStyleHintsDetails error', [
                        'retry' => $retry,
                        'country' => $country,
                        'styleHintSummary' => $styleHintSummary,
                    ]);
                    report($e);

                    $retry++;

                    sleep(1);
                }
            } while ($retry > 0 && $retry <= 5);
        });
    }
}