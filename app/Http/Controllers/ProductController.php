<?php

namespace App\Http\Controllers;

use App\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
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
        $styleDictionaries = $this->productService->getStyleDictionaries($product);

        $productHistories = $product->histories;

        return view('products.show', [
            'product' => $product,
            'productHistories' => $productHistories,
            'styleDictionaries' => $styleDictionaries
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

    public function stockouts()
    {
        $stockouts = $this->productService->getStockoutProducts();

        return view('products.stockouts', [
            'stockouts' => $stockouts
        ]);
    }

    public function sales()
    {
        $sales = $this->productService->getSaleProducts();

        return view('products.sales', [
            'sales' => $sales
        ]);
    }

    public function limitedOffers()
    {
        $limitedOffers = $this->productService->getLimitedOfferProducts();

        return view('products.limited-offers', [
            'limitedOffers' => $limitedOffers
        ]);
    }
}
