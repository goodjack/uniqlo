<?php

namespace App\Http\Controllers;

use App\Enums\Brand;
use App\Services\CategoryService;

class CategoryController extends Controller
{
    protected $service;

    public function __construct(CategoryService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return view('categories.index', [
            'groups' => $this->service->getOverview(),
        ]);
    }

    /**
     * 網址是 /categories/{brand}/{code}，品牌在路徑裡。
     *
     * 兩家的分類 code 目前各成一套、不會撞，但 code 看不出品牌；把品牌放進路徑，
     * 使用者與搜尋引擎一眼就知道自己在看誰的分類，日後兩家真的出現同名 code 也不會衝突。
     *
     * 分類 code 用官方的原值，不另外維護 slug 對照表：它穩定又自帶層級語意
     * （all_men-tops-polo），標題與 h1 用中文名，搜尋引擎看的是那些。
     */
    public function show(string $brand, string $code)
    {
        $resolvedBrand = Brand::fromSlug($brand);

        abort_if($resolvedBrand === null, 404);

        // 分類的身分是品牌加 code，兩者都要對上才是同一個資源
        $category = $this->service->findPageable($resolvedBrand, $code);

        abort_if($category === null, 404);

        // 主檔只增不減，商品全部下架的分類仍在表裡，讓它出 404 而不是空頁
        abort_if(! $this->service->hasAvailableProducts($category), 404);

        return view('categories.show', [
            'brand' => $category->brand,
            'category' => $category,
            'children' => $this->service->getChildren($category),
            'hmallProducts' => $this->service->getProducts($category),
        ]);
    }
}
