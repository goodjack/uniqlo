<?php

namespace App\Http\Controllers;

use App\Enums\Brand;
use App\Services\FavoriteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
     *
     * 收藏存在使用者的瀏覽器，混進一筆壞資料很正常；壞的那筆當成查無此商品略過，
     * 不讓整批失敗、整份收藏載入不到。
     */
    public function cards(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'max:'.self::MAX_CODES],
        ]);

        $items = array_values(array_filter($validated['items'], fn ($item) => $this->isValidItem($item)));

        return view('favorites.cards', [
            'hmallProducts' => $this->service->getHmallProductsByBrandAndCodes($items),
        ]);
    }

    private function isValidItem(mixed $item): bool
    {
        return is_array($item) && Validator::make($item, [
            'brand' => ['required', 'string', Rule::enum(Brand::class)],
            'code' => ['required', 'string', 'max:191'],
        ])->passes();
    }
}
