<?php

namespace App\Http\Controllers;

use App\Product;
use App\Services\ProductService;
use Carbon\Carbon;
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

        $multiBuys = $product->multiBuys;
        $multiBuys = $multiBuys->map(function ($multiBuy) {
            unset($multiBuy['id']);
            unset($multiBuy['product_id']);
            $multiBuy->created_at = Carbon::parse($multiBuy->created_at)->format('m/d');

            return $multiBuy;
        });

        $productHistories = $product->histories;
        $productHistories = $productHistories->map(function ($productHistorie) use ($multiBuys) {
            unset($productHistorie['id']);
            unset($productHistorie['product_id']);

            $productHistorie->created_at = Carbon::parse($productHistorie->created_at)->format('m/d');

            $productHistorie->multi_buy = null;
            foreach ($multiBuys as $multiBuy) {
                if ($productHistorie->created_at == $multiBuy->created_at) {
                    $productHistorie->multi_buy = $multiBuy->multi_buy;
                    break;
                }
            }

            return $productHistorie;
        });

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

    public function multiBuys()
    {
        $multiBuys = $this->productService->getMultiBuyProducts();

        return view('products.multi-buys', [
            'multiBuys' => $multiBuys
        ]);
    }
}
