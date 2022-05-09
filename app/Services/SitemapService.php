<?php

namespace App\Services;

use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Yish\Generators\Foundation\Service\Service;

class SitemapService extends Service
{
    protected $productRepository;
    protected $hmallProductRepository;

    public function __construct(ProductRepository $productRepository, HmallProductRepository $hmallProductRepository)
    {
        $this->productRepository = $productRepository;
        $this->hmallProductRepository = $hmallProductRepository;
    }

    public function make()
    {
        $sitemap = Sitemap::create();

        $pages = [
            'lists/limited-offers',
            'lists/sale',
            'lists/most-reviewed',
            'lists/new',
            'lists/coming-soon',
            'lists/multi-buy',
            'lists/online-special',
            'products/limited-offers',
            'products/sales',
            'products/multi-buys',
            'products/news',
            'products/stockouts',
            'products/most-reviewed',
            'pages/changelog',
            'pages/privacy',
        ];

        foreach ($pages as $page) {
            $sitemap->add($page);
        }

        $hmallProducts = $this->hmallProductRepository->getAllProductsForSitemap();

        foreach ($hmallProducts as $hmallProduct) {
            $prefix = $hmallProduct->brand === 'GU' ? 'gu-products' : 'hmall-products';

            $sitemap->add(
                Url::create("{$prefix}/{$hmallProduct->product_code}")
                    ->setLastModificationDate($hmallProduct->updated_at)
            );
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
