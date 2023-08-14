<?php

namespace App\Http\Controllers;

use App\Models\HmallProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'query' => ['required'],
        ]);

        $query = $request->query('query');

        if (! is_numeric($query)) {
            return redirect()->route('search.google-cse', ['query' => $query]);
        }

        $hmallProducts = HmallProduct::select(['brand', 'product_code'])->where('code', $query)->get();
        $product = Product::select('id')->where('id', $query)->get();

        $results = $hmallProducts->concat($product);

        if ($results->count() === 1) {
            return redirect($results->first()->route_url);
        }

        return redirect()->route('search.show', ['query' => $query]);
    }

    public function show($query)
    {
        Validator::make(['query' => $query], [
            'query' => ['required', 'numeric'],
        ]);

        $hmallProducts = HmallProduct::where('code', $query)->orderBy('min_price')->get();
        $products = Product::where('id', 'like', "{$query}%")->get();

        return view('search.results', [
            'query' => $query,
            'hmallProducts' => $hmallProducts,
            'products' => $products,
        ]);
    }

    public function searchByGoogleCse()
    {
        return view('search.google-cse');
    }
}
