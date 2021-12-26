<?php

namespace App\Http\Controllers;

use App\HmallProduct;
use App\Product;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function index(Request $request)
    {
        $request->validate([
            'id' => ['required', 'numeric'],
        ]);

        $hmallProducts = HmallProduct::where('code', $request->id)->get();
        $product = Product::where('id', $request->id)->get();

        $results = $hmallProducts->merge($product);

        if ($results->count() === 1) {
            return redirect($results->first()->route_url);
        }

        return redirect()->route('search.show', ['query' => $request->id]);
    }

    public function show($query)
    {
        Validator::make(['query' => $query], [
            'query' => ['required', 'numeric'],
        ]);

        $hmallProducts = HmallProduct::where('code', $query)->get()->sortBy('min_price');
        $products = Product::where('id', $query)->get();

        return view('search.results', [
            'query' => $query,
            'hmallProducts' => $hmallProducts,
            'products' => $products,
        ]);
    }

    public function search($query)
    {
        // FIXME: 已棄用
        $searchResults = $this->searchService->getSearchResults($query);
        $products = $this->searchService->getProducts($searchResults);

        return view('search.results', [
            'query' => $query,
            'products' => $products,
        ]);
    }
}
