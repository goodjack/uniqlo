<?php

namespace App\Services;

use App\Repositories\StyleRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use Yish\Generators\Foundation\Service\Service;

class StyleService extends Service
{
    protected $repository;

    public function __construct(StyleRepository $repository)
    {
        $this->repository = $repository;
    }

    public function fetchAllStyles()
    {
        $dptIds = collect([
            'men',
            'women',
            'kids',
            'baby',
        ]);

        $dptIds->each(function ($dptId) {
            return $this->fetchStylesByDptId($dptId);
        });
    }

    private function fetchStylesByDptId(string $dptId)
    {
        $limit = 50;
        $offset = 0;
        $styleCount = 0;
        $retry = 0;

        do {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                ])->get(config('uniqlo.api.style_book_list'), [
                    'dptId' => $dptId,
                    'lang' => 'zh',
                    'limit' => $limit,
                    'locale' => 'tw',
                    'offset' => $offset,
                ]);

                $responseBody = json_decode($response->getBody());

                $styles = $responseBody->result->styles;
                $this->fetchStyleDetails($styles);

                $styleCount = $responseBody->result->styleCount;

                $offset += $limit;

                $retry = 0;

                sleep(1);
            } catch (Throwable $e) {
                Log::error('fetchStylesByDptId error', [
                    'retry' => $retry,
                    'dptId' => $dptId,
                    'limit' => $limit,
                    'offset' => $offset,
                ]);
                report($e);

                if ($retry >= 3) {
                    $retry = 0;
                    continue;
                }

                $retry++;

                sleep(1);
            }
        } while ($styleCount >= $offset);
    }

    private function fetchStyleDetails($styles)
    {
        $styles = collect($styles);
        $styles->each(function ($style) {
            $retry = 0;

            $styleId = $style->styleId;

            do {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => config('app.user_agent_mobile'),
                    ])->get(config('uniqlo.api.style_book_detail'), [
                        'lang' => 'zh',
                        'limit' => 4,
                        'locale' => 'tw',
                        'styleId' => $styleId,
                    ]);

                    $result = json_decode($response->getBody())->result;
                    $this->repository->saveStyleFromUniqloStyleBook($styleId, $result);

                    $retry = 0;

                    sleep(1);
                } catch (Throwable $e) {
                    Log::error('fetchStyleDetails error', [
                        'retry' => $retry,
                        'styleId' => $styleId,
                    ]);
                    report($e);

                    $retry++;

                    sleep(1);
                }
            } while ($retry > 0 && $retry <= 3);
        });
    }
}
