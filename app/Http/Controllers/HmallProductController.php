<?php

namespace App\Http\Controllers;

use App\Models\HmallProduct;
use App\Services\HmallProductService;
use Illuminate\Http\Request;

class HmallProductController extends Controller
{
    protected $service;

    public function __construct(HmallProductService $service)
    {
        $this->service = $service;
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
        $relatedHmallProducts = $this->service->getRelatedHmallProducts($hmallProduct);
        $relatedProducts = $this->service->getRelatedProducts($hmallProduct);
        $styles = $this->service->getStyles($hmallProduct);
        $styleHints = $this->service->getStyleHints($hmallProduct, 12);
        $styleHintCount = $this->service->getStyleHintCount($hmallProduct);
        $hmallPriceHistories = $hmallProduct->hmallPriceHistories()->get();

        return view('hmall-products.show', [
            'hmallProduct' => $hmallProduct,
            'relatedHmallProducts' => $relatedHmallProducts,
            'relatedProducts' => $relatedProducts,
            'styles' => $styles,
            'styleHints' => $styleHints,
            'styleHintCount' => $styleHintCount,
            'hmallPriceHistories' => $hmallPriceHistories,
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
