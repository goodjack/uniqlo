<?php

namespace App\Services;

use App\Product;
use GuzzleHttp\Client;
use App\Foundations\DivideProducts;
use Yish\Generators\Foundation\Service\Service;

class SearchService extends Service
{
    use divideProducts;

    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getSearchResults($query)
    {
        $response = $this->client->request(
            'GET',
            config('uniqlo.api.search'),
            [
                'headers' => [
                    'User-Agent' => env('USER_AGENT_MOBILE')
                ],
                'query' => [
                    'limit' => '10',
                    'order' => 'asc',
                    'page' => '1',
                    'q' => $query,
                    'sort' => 'flagSortWeightage',
                ],
            ]
        );

        return json_decode($response->getBody());
    }

    public function getProducts($searchResults)
    {
        $records = collect($searchResults->records);

        $productIds = $records->map(function ($record) {
            return $record->id;
        });

        $products = Product::find($productIds);

        return $this->divideProducts($products);
    }
}
