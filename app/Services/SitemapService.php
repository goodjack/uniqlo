<?php

namespace App\Services;

use App\Enums\CategoryLevel;
use App\Repositories\HmallCategoryRepository;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapService extends Service
{
    protected $productRepository;

    protected $hmallProductRepository;

    protected $hmallCategoryRepository;

    public function __construct(
        ProductRepository $productRepository,
        HmallProductRepository $hmallProductRepository,
        HmallCategoryRepository $hmallCategoryRepository
    ) {
        $this->productRepository = $productRepository;
        $this->hmallProductRepository = $hmallProductRepository;
        $this->hmallCategoryRepository = $hmallCategoryRepository;
    }

    public function make()
    {
        $sitemap = Sitemap::create();

        $pages = [
            'categories',
            'lists/limited-offers',
            'lists/sale',
            'lists/most-reviewed',
            'lists/japan-most-reviewed',
            'lists/top-wearing',
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

        // 分類頁從資料表長出來，新分類會自己進 sitemap，不用回頭改這份清單。
        // 只收還有商品的分類，避免把點進去是 404 的網址送給搜尋引擎。
        $categories = $this->hmallCategoryRepository->getCategoriesWithProducts([
            CategoryLevel::One,
            CategoryLevel::Two,
        ]);

        foreach ($categories as $category) {
            $sitemap->add(Url::create("categories/{$category->brand->slug()}/{$category->code}"));
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

        $sitemapFileName = 'sitemap'.config('app.sitemap_name').'.xml';
        $sitemap->writeToFile(public_path($sitemapFileName));
    }
}
