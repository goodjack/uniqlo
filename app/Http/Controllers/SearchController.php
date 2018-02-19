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
        return redirect()->route('search', ['query' => $request->q]);
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
