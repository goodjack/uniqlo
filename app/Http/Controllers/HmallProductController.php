<?php

namespace App\Http\Controllers;

use App\Enums\CategoryLevel;
use App\Models\HmallProduct;
use App\Services\CategoryService;
use App\Services\HmallProductService;
use Illuminate\Http\Request;

class HmallProductController extends Controller
{
    /**
     * 商品頁列出幾個分類連結。再多就從導覽變成雜訊。
     */
    private const CATEGORY_LINKS_ON_PRODUCT_PAGE = 5;

    protected $service;

    protected $categoryService;

    public function __construct(HmallProductService $service, CategoryService $categoryService)
    {
        $this->service = $service;
        $this->categoryService = $categoryService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(HmallProduct $hmallProduct)
    {
        $commonlyStyledHmallProducts = $this->service->getCommonlyStyledHmallProducts($hmallProduct);
        $relatedHmallProducts = $this->service->getRelatedHmallProducts($hmallProduct);
        $relatedProducts = $this->service->getRelatedProducts($hmallProduct);
        $styles = $this->service->getStyles($hmallProduct);
        $styleHints = $this->service->getStyleHints($hmallProduct, 12);
        $styleHintCount = $this->service->getStyleHintCount($hmallProduct);
        $hmallPriceHistories = $hmallProduct->hmallPriceHistories()->get();
        // 一件商品平均掛十幾個分類，沒有唯一路徑可以做成麵包屑，所以列出分類連結。
        //
        // 兩件事要收斂：男女適穿的商品在男裝與女裝樹下各掛一份，名稱會重複
        // （兩個「下身類」）；而且十幾個標籤對使用者是雜訊。品項層（levelTwo）
        // 比大類具體、對找同類商品也最有用，所以優先顯示它，同名的只留一個。
        $categories = $hmallProduct->categories()
            ->whereIn('level', [CategoryLevel::One->value, CategoryLevel::Two->value])
            ->orderBy('level', 'desc')
            ->orderBy('code')
            ->get()
            ->unique('name')
            ->take(self::CATEGORY_LINKS_ON_PRODUCT_PAGE);

        // 麵包屑只走一條路徑，規則在 CategoryService::getPrimaryCategory()
        $primaryCategory = $this->categoryService->getPrimaryCategory($hmallProduct);

        return view('hmall-products.show', [
            'hmallProduct' => $hmallProduct,
            'commonlyStyledHmallProducts' => $commonlyStyledHmallProducts,
            'relatedHmallProducts' => $relatedHmallProducts,
            'relatedProducts' => $relatedProducts,
            'styles' => $styles,
            'styleHints' => $styleHints,
            'styleHintCount' => $styleHintCount,
            'hmallPriceHistories' => $hmallPriceHistories,
            'japanProduct' => $hmallProduct->japanProduct,
            'categories' => $categories,
            'categoryTrail' => $primaryCategory === null
                ? collect()
                : $this->categoryService->getBreadcrumb($primaryCategory),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(HmallProduct $hmallProduct)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, HmallProduct $hmallProduct)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(HmallProduct $hmallProduct)
    {
        //
    }
}
