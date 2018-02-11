<?php

namespace App\Services;

use App\Repositories\CategoryRepository;
use GuzzleHttp\Client;
use Yish\Generators\Foundation\Service\Service;

class CategoryService extends Service
{
    protected $categoryRepository;

    public function __construct(CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function fetchAllCategories()
    {
        $categoryApi = env('UQ_API_CATEGORY');

        $client = new Client();

        $response = $client->request(
            'GET',
            $categoryApi,
            [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_3 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) CriOS/56.0.2924.75 Mobile/14E5239e Safari/602.1'
                ]
            ]
        );

        $categories = json_decode($response->getBody());

        $this->categoryRepository->saveCategoriesFromUniqlo($categories);
    }
}
