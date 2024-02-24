<?php

namespace App\Services;

use App\Repositories\JapanProductRepository;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class JapanProductService
{
    public function __construct(protected JapanProductRepository $repository)
    {
    }

    public function fetchAllProducts($brand = 'UNIQLO'): void
    {
        $japanProductListApiUrl = $this->getJapanProductListApiUrl($brand);

        $limit = 36;
        $offset = 0;
        $total = 0;
        $retry = 0;

        do {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent_mobile'),
                    'x-fr-clientid' => $this->getClientId($brand),
                ])
                    ->retry(5, 1000)
                    ->get($japanProductListApiUrl, [
                        'offset' => $offset,
                        'limit' => $limit,
                        'sort' => 1,
                        'httpFailure' => 'true',
                        'queryRelaxationFlag' => 'true',
                    ]);

                $responseBody = json_decode($response->body());
                $items = $responseBody->result->items;

                $this->repository->saveProducts($items, $brand);

                $total = $responseBody->result->pagination->total;

                if ($total === 0) {
                    throw new Exception('No products found');
                }

                $offset += $limit;
                $retry = 0;

                usleep(500000);
            } catch (Throwable $e) {
                if ($retry >= 5) {
                    Log::error('JapanProductService fetchAllProducts error', [
                        'brand' => $brand,
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

    private function getJapanProductListApiUrl($brand = 'UNIQLO')
    {
        if ($brand === 'GU') {
            return config('gu.api.product_list.jp');
        }

        return config('uniqlo.api.product_list.jp');
    }

    private function getClientId($brand = 'UNIQLO')
    {
        if ($brand === 'GU') {
            return 'gu.jp.web-mem-cnc';
        }

        return 'uq.jp.web-spa';
    }
}
