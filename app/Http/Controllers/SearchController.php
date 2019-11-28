<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function index(Request $request)
    {
        return redirect()->route('products.limited-offers');
    }

    public function search($query)
    {
        $searchResults = $this->searchService->getSearchResults($query);
        $products = $this->searchService->getProducts($searchResults);

        return view('search.results', [
            'query' => $query,
            'products' => $products
        ]);
    }
}
