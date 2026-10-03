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

        // 手寫清單：新增清單頁要回來補一行（順序同 routes/web.php）
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
            'lists/most-visited',
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

        // 只收還有商品的分類，沒商品的分類頁是 404
        $categories = $this->hmallCategoryRepository->getCategoriesWithProducts([
            CategoryLevel::One,
            CategoryLevel::Two,
        ]);

        foreach ($categories as $category) {
            // 用 route() 才會跟站內連結同一套網址編碼（有 code 帶零寬空白）
            $sitemap->add(Url::create(route('categories.show', [
                'brand' => $category->brand->slug(),
                'code' => $category->code,
            ])));
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
