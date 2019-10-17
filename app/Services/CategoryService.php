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
        $client = new Client();

        $response = $client->request(
            'GET',
            env('UQ_API_CATEGORY'),
            [
                'headers' => [
                    'User-Agent' => env('USER_AGENT_MOBILE')
                ],
            ]
        );

        $categories = json_decode($response->getBody());

        $this->categoryRepository->saveCategoriesFromUniqlo($categories);
    }
}
