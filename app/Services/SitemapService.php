<?php

namespace App\Services;

use App\Enums\CategoryLevel;
use App\Repositories\HmallCategoryRepository;
use App\Repositories\HmallProductRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Route;
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

        // 清單頁直接從路由表長出來，新增清單頁不用回來改這裡
        $listRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name) => str_starts_with($name, 'lists.'));

        // products/ 底下的舊清單網址都是 301，不放進 sitemap
        foreach (['categories.index', ...$listRoutes, 'products.stockouts', 'pages.changelog', 'pages.privacy-policy'] as $name) {
            $sitemap->add(route($name));
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
