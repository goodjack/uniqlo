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
            "http://www.uniqlo.com/tw/spa-catalog/products/{$productID}",
            [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_3 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) CriOS/56.0.2924.75 Mobile/14E5239e Safari/602.1'
                ]
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
