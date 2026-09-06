<?php

namespace App\Http\Controllers;

use App\Models\HmallProduct;
use App\Services\CategoryService;
use App\Services\HmallProductService;
use Illuminate\Http\Request;

class HmallProductController extends Controller
{
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
        // 規則在 CategoryService::getCategoryLinksForProductPage()
        $categories = $this->categoryService->getCategoryLinksForProductPage($hmallProduct);

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
