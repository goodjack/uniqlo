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
        return redirect()->route('products.limited-offers');
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
    public function show($value)
    {
        $product = \App\Product::find($value);

        if (! $product) {
            // return redirect()->route('search', ['query' => $value])->send();
            return abort(404);
        }

        $styleDictionaries = $this->productService->getStyleDictionaries($product);
        $styles = $this->productService->getStyles($product);
        $suggestProducts = $this->productService->getSuggestProducts($product->id, $styles);

        $multiBuysRaw = $product->multiBuys()->select('multi_buy', 'created_at')->get();
        $multiBuys = $multiBuysRaw->map(function ($multiBuy) {
            $multiBuy->created_at = Carbon::parse($multiBuy->created_at)->format('m/d');

            return $multiBuy;
        });

        $productHistoriesRaw = $product->histories()->select('price', 'created_at')->get();
        $productHistories = $productHistoriesRaw->map(function ($productHistory) use ($multiBuys) {
            $productHistory->created_at = Carbon::parse($productHistory->created_at)->format('m/d');

            $productHistory->multi_buy = null;
            foreach ($multiBuys as $multiBuy) {
                if ($productHistory->created_at === $multiBuy->created_at) {
                    $productHistory->multi_buy = $multiBuy->multi_buy;
                    break;
                }
            }

            return $productHistory;
        });

        return view('products.show', [
            'product' => $product,
            'productHistories' => $productHistories,
            'suggestProducts' => $suggestProducts,
            'styles' => $styles,
            'styleDictionaries' => $styleDictionaries,
            'relatedProducts' => $this->productService->getRelatedProducts($product),
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
            'stockouts' => $stockouts,
        ]);
    }

    public function sales()
    {
        $sales = $this->productService->getSaleProducts();

        return view('products.sales', [
            'sales' => $sales,
        ]);
    }

    public function limitedOffers()
    {
        $limitedOffers = $this->productService->getLimitedOfferProducts();

        return view('products.limited-offers', [
            'limitedOffers' => $limitedOffers,
        ]);
    }

    public function multiBuys()
    {
        $multiBuys = $this->productService->getMultiBuyProducts();

        return view('products.multi-buys', [
            'multiBuys' => $multiBuys,
        ]);
    }

    public function news()
    {
        $news = $this->productService->getNewProducts();

        return view('products.news', [
            'news' => $news,
        ]);
    }

    public function mostReviewed()
    {
        $products = $this->productService->getMostReviewedProducts();

        return view('products.list', [
            'products' => $products,
            'typeName' => '熱門評論商品',
            'typeStyle' => 'most-reviewed',
            'typeIcon' => 'comments outline',
            'routeName' => 'products.most-reviewed',
        ]);
    }

    public function go(Request $request)
    {
        return redirect()->route('search.index', ['id' => $request->id]);
    }

    public function getProductForApi(Product $product)
    {
        return response()->json($product->only([
            'id',
            'name',
            'main_image_url',
            'price',
            'min_price',
            'max_price',
        ]));
    }
}
