<?php

namespace App\Http\Controllers;

use App\Enums\Brand;
use App\Services\FavoriteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FavoriteController extends Controller
{
    /**
     * 一次最多查幾件，擋住把它當成批次查詢介面的用法。favorites.js 的 BATCH_SIZE 要跟著改。
     */
    private const MAX_CODES = 100;

    protected $service;

    public function __construct(FavoriteService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return view('favorites.index');
    }

    /**
     * 用一批品牌加編號換回渲染好的卡片。
     *
     * 不吐任何價格，連價差也不算：呼叫端可以宣稱收藏時是 0 元，差額就等於現價，
     * 這支會變成批次查價介面。要表達的「現在特價」由卡片上的狀態標籤負責。
     */
    public function cards(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'max:'.self::MAX_CODES],
            'items.*.brand' => ['required', Rule::enum(Brand::class)],
            'items.*.code' => ['required', 'string', 'max:191'],
        ]);

        return view('favorites.cards', [
            'hmallProducts' => $this->service->getHmallProductsByBrandAndCodes($validated['items']),
        ]);
    }
}
