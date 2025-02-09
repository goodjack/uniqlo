<?php

namespace App\Http\Controllers;

use App\Services\ListService;

class HomeController extends Controller
{
    protected $listService;

    public function __construct(ListService $listService)
    {
        $this->listService = $listService;
    }

    public function index()
    {
        $mostVisitedProducts = $this->listService->getMostVisitedHmallProducts(limit: 12);

        return view('home', [
            'mostVisitedProducts' => $mostVisitedProducts,
        ]);
    }
}
