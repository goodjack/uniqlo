<?php

namespace App\Services;

use App\Foundations\DivideProducts;
use App\Models\Product;
use GuzzleHttp\Client;

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
                    'User-Agent' => config('app.user_agent_mobile'),
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
