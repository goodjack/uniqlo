<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use Yish\Generators\Foundation\Service\Service;

class ProductService extends Service
{
    protected $repository;

    public function getProductInfo($productID)
    {
        $client = new Client();

        $response = $client->request(
            'GET',
            'http://www.uniqlo.com/tw/store/ApiGetProductInfo.do',
            [
                'query' => ['format' => 'json', 'product' => $productID]
            ]
        );

        return json_decode($response->getBody());
    }

    public function getStyleDictionary($productID)
    {
        $client = new Client();

        $response = $client->request(
            'GET',
            'http://www.uniqlo.com/tw/styledictionary/api/sdapi2.php',
            [
                'query' => ['t' => '3', 'pub' => $productID]
            ]
        );

        return json_decode($response->getBody());
    }
}
