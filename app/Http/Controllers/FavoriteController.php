<?php

namespace App\Http\Controllers;

use App\Repositories\HmallProductRepository;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * 一次最多查幾件。收藏清單存在瀏覽器，數量由使用者決定，
     * 這裡擋住把它當成免費批次查價介面的用法。
     */
    private const MAX_CODES = 100;

    protected $repository;

    public function __construct(HmallProductRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        return view('favorites.index');
    }

    /**
     * 用一批商品換回渲染好的卡片。
     *
     * 卡片只顯示商品標籤，不吐任何價格——絕對價格不放、價差也不算。價差看似只是
     * 相對值，但呼叫端可以宣稱自己收藏時是 0 元，差額就等於現價；那等於一邊把價格
     * 從畫面拿掉、一邊開一個算得出價格的介面。使用者要知道的是「這件現在特價」，
     * 那件事卡片上的既有標籤就在講了。
     *
     * 品牌是必要的，不是多餘：兩家共用同一組商品編號，少了品牌會撈到另一家的商品。
     */
    public function cards(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'max:'.self::MAX_CODES],
            'items.*.brand' => ['required', 'string', 'in:UNIQLO,GU'],
            'items.*.code' => ['required', 'string', 'max:191'],
        ]);

        return view('favorites.cards', [
            'hmallProducts' => $this->repository->getByBrandAndProductCodes($validated['items']),
        ]);
    }
}
