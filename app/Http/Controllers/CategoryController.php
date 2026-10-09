<?php

namespace App\Http\Controllers;

use App\Enums\Brand;
use App\Enums\ProductTag;
use App\Http\Requests\ListRequest;
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
     * 分類的身分是品牌加官方 code：兩家的 code 各成一套、看不出品牌，日後也可能撞名。
     */
    public function show(ListRequest $listRequest, string $brand, string $code)
    {
        $resolvedBrand = Brand::fromSlug($brand);

        abort_if($resolvedBrand === null, 404);

        $category = $this->service->findPageable($resolvedBrand, $code);

        abort_if($category === null, 404);

        // 分類主檔只增不減，商品全下架的分類要 404 而不是空頁
        abort_if(! $this->service->hasAvailableProducts($category), 404);

        return view('categories.show', [
            'brand' => $category->brand,
            'category' => $category,
            'breadcrumb' => $this->service->getBreadcrumb($category),
            'children' => $this->service->getChildren($category),
            'hmallProducts' => $this->service->getProducts(
                $category,
                ProductTag::fromValues($listRequest->input('tags') ?? []),
                $listRequest->input('q')
            )->onEachSide(1),
        ]);
    }
}
