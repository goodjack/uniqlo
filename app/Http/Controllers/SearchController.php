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

    public function search($query)
    {
        $searchResults = $this->searchService->getSearchResults($query);

        return view('search.results', [
            'query' => $query,
            'searchResults' => $searchResults
        ]);
    }
}
