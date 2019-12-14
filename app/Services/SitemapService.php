<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Yish\Generators\Foundation\Service\Service;

class SitemapService extends Service
{
    protected $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function make()
    {
        $sitemap = Sitemap::create();

        $pages = [
            'products/limited-offers',
            'products/sales',
            'products/multi-buys',
            'products/news',
            'products/stockouts',
        ];

        foreach ($pages as $page) {
            $sitemap->add($page);
        }

        $products = $this->productRepository->getAllProductsForSitemap();

        foreach ($products as $product) {
            $sitemap->add(
                Url::create("products/{$product->id}")
                    ->setLastModificationDate($product->updated_at)
            );
        }

        $sitemapFileName = 'sitemap' . config('app.sitemap_name') . '.xml';
        $sitemap->writeToFile(public_path($sitemapFileName));
    }
}
