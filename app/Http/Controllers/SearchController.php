<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    /**
     * index 與 show 兩個入口都要擋：使用者可以直接開 /search/{query}。
     */
    private const MAX_QUERY_LENGTH = 100;

    /**
     * 貨號固定 6 碼。更短的數字（2、100、2WAY 的 2）不是貨號，照編號查會用
     * 數字邊界比對撈出一大串、而且沒有分頁，改走有分頁的關鍵字搜尋。
     */
    private const MIN_PRODUCT_CODE_LENGTH = 6;

    protected $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * 表單入口直接顯示結果，不轉址到 /search/{query}：關鍵字放進路徑時，Laravel
     * 產生網址不編碼 / ? #（會 404 或被截斷），還會撞到 /search/keywords，
     * 轉址也會讓一次搜尋扣兩次限流。
     */
    public function index(Request $request)
    {
        // 只看網址參數：validate() 會連 JSON 內文一起讀，跟實際取值的來源不同
        $query = Validator::make($request->query(), [
            'query' => ['required', 'string', 'max:'.self::MAX_QUERY_LENGTH],
        ])->validate()['query'];

        if (! $this->looksLikeProductCode($query)) {
            return $this->showKeywordResults($query);
        }

        $hmallProducts = $this->searchService->findHmallProductsByCodeOrSharedNumber($query);
        $hits = $hmallProducts->concat(Product::select('id')->where('id', $query)->get());

        if ($hits->count() === 1) {
            return redirect($hits->first()->route_url);
        }

        return $this->showProductCodeResults($query, $hmallProducts);
    }

    public function show(string $query)
    {
        $query = trim($query);

        if ($query === '') {
            return redirect()->route('home');
        }

        abort_if(mb_strlen($query) > self::MAX_QUERY_LENGTH, 404);

        if ($this->looksLikeProductCode($query)) {
            return $this->showProductCodeResults(
                $query,
                $this->searchService->findHmallProductsByCodeOrSharedNumber($query)
            );
        }

        return $this->showKeywordResults($query);
    }

    private function looksLikeProductCode(string $query): bool
    {
        return ctype_digit($query) && strlen($query) >= self::MIN_PRODUCT_CODE_LENGTH;
    }

    public function searchByGoogleCse()
    {
        return view('search.google-cse');
    }

    /**
     * 含已凍結的舊軌 Product：編號是兩套系統唯一通用的識別。
     */
    private function showProductCodeResults(string $query, Collection $hmallProducts)
    {
        $products = Product::where('id', 'like', "{$query}%")->get();

        // 查無貨號（例如帶前導零）時退回關鍵字搜尋，使用者才看得到空狀態與
        // 「改用 Google 搜尋」的出口
        if ($hmallProducts->isEmpty() && $products->isEmpty()) {
            return $this->showKeywordResults($query);
        }

        return view('search.results', [
            'query' => $query,
            'isProductCodeSearch' => true,
            'hmallProducts' => $hmallProducts,
            'products' => $products,
        ]);
    }

    /**
     * 只查現行商品。
     */
    private function showKeywordResults(string $query)
    {
        $keywords = $this->searchService->tokenize($query);

        return view('search.results', [
            'query' => $query,
            'isProductCodeSearch' => false,
            'keywords' => $keywords,
            'ignoredKeywords' => $this->searchService->ignoredKeywords($query),
            'hmallProducts' => $this->searchService->searchHmallProducts($query)->onEachSide(1),
        ]);
    }
}
