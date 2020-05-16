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
            config('uniqlo.api.category'),
            [
                'headers' => [
                    'User-Agent' => config('app.user_agent_mobile'),
                ],
            ]
        );

        $categories = json_decode($response->getBody());

        $this->categoryRepository->saveCategoriesFromUniqlo($categories);
    }
}
