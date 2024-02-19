<?php

namespace App\Services;

use App\Repositories\JapanProductRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class JapanProductService
{
    public function __construct(protected JapanProductRepository $repository)
    {
    }

    public function fetchAllGuProducts(): void
    {
        $limit = 36;
        $offset = 0;
        $total = 0;
        $retry = 0;

        do {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                    'x-fr-clientid' => 'gu.jp.web-mem-cnc',
                ])
                    ->retry(5, 1000)
                    ->get(config('gu.api.product_list.jp'), [
                        'offset' => $offset,
                        'limit' => $limit,
                        'sort' => 1,
                        'httpFailure' => 'true',
                        'queryRelaxationFlag' => 'true',
                    ]);

                $responseBody = json_decode($response->body());
                $items = $responseBody->result->items;

                $this->repository->saveProducts($items, 'GU');

                $total = $responseBody->result->pagination->total;

                $offset += $limit;
                $retry = 0;

                usleep(500000);
            } catch (Throwable $e) {
                if ($retry >= 5) {
                    Log::error('fetchAllGuProducts error', [
                        'retry' => $retry,
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
}
