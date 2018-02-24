<?php

namespace App\Http\Controllers;

use App\Product;
use App\Services\ProductHistoryService;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected $productService;
    
    public function __construct(ProductHistoryService $productHistoryService, ProductService $productService)
    {
        $this->productService = $productService;
        $this->productHistoryService = $productHistoryService;
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Product $product)
    {
        $styleDictionary = $this->productService->getStyleDictionary($product->id);

        $productHistories = $product->histories;
        $highestPrice = $this->productHistoryService->getHighestPrice($productHistories);
        $lowestPrice = $this->productHistoryService->getLowestPrice($productHistories);

        return view('products.show', [
            'product' => $product,
            'productHistories' => $productHistories,
            'highestPrice' => $highestPrice,
            'lowestPrice' => $lowestPrice,
            'styleDictionary' => $styleDictionary
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
