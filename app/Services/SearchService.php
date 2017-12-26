<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use Yish\Generators\Foundation\Service\Service;

class SearchService extends Service
{
    public function getSearchResults($query)
    {
        $client = new Client();
        $searchApi = env('UQ_API_SEARCH');

        $response = $client->request(
            'GET',
            $searchApi,
            [
                'query' => [
                    'limit' => '10',
                    'order' => 'asc',
                    'page' => '1',
                    'q' => $query,
                    'sort' => 'flagSortWeightage'
                ]
            ]
        );

        return json_decode($response->getBody());
    }
}
