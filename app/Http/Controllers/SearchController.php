<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * 查詢字串長度上限。
     *
     * 兩個入口都要擋：搜尋表單走 index，但使用者可以直接開 /search/{query}，
     * 只在 index 驗證等於沒有驗證。
     */
    private const MAX_QUERY_LENGTH = 100;

    protected $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function index(Request $request)
    {
        $request->validate([
            'query' => ['required', 'string', 'max:'.self::MAX_QUERY_LENGTH],
        ]);

        $query = trim($request->query('query'));

        if (is_numeric($query)) {
            // 一頁常共用多個貨號，所以命中判準跟 showProductCodeResults() 是同一條查詢：
            // code 精準符合或 name 裡帶著這組號碼。舊軌 Product 維持精準比對不變。
            $results = $this->searchService->findHmallProductsByCodeOrSharedNumber($query)
                ->concat(Product::select('id')->where('id', $query)->get());

            if ($results->count() === 1) {
                return redirect($results->first()->route_url);
            }
        }

        return redirect()->route('search.show', ['query' => $query]);
    }

    public function show(string $query)
    {
        $query = trim($query);

        if ($query === '') {
            return redirect()->route('home');
        }

        abort_if(mb_strlen($query) > self::MAX_QUERY_LENGTH, 404);

        if (is_numeric($query)) {
            return $this->showProductCodeResults($query);
        }

        return $this->showKeywordResults($query);
    }

    public function searchByGoogleCse()
    {
        return view('search.google-cse');
    }

    /**
     * 維持原本行為，含已凍結的舊軌 Product——編號是跨兩套系統唯一通用的識別。
     *
     * hmallProducts 那條查詢同時含 code 精準符合與「name 裡帶這組共用號碼」的商品
     * （見 HmallProductRepository::findHmallProductsByCodeOrSharedNumber）。
     */
    private function showProductCodeResults(string $query)
    {
        return view('search.results', [
            'query' => $query,
            'isProductCodeSearch' => true,
            'hmallProducts' => $this->searchService->findHmallProductsByCodeOrSharedNumber($query),
            'products' => Product::where('id', 'like', "{$query}%")->get(),
        ]);
    }

    /**
     * 只查現行商品，不查已凍結的舊軌 Product。
     */
    private function showKeywordResults(string $query)
    {
        $keywords = $this->searchService->tokenize($query);

        return view('search.results', [
            'query' => $query,
            'isProductCodeSearch' => false,
            'keywords' => $keywords,
            // 超過上限的關鍵字會被丟掉，那要讓使用者看得到，不然結果會莫名其妙
            'ignoredKeywords' => $this->searchService->ignoredKeywords($query),
            // 分頁沿用 style-hints 頁那份共用 markup，onEachSide(1) 讓頁碼視窗跟它
            // 一致（Laravel 預設是 3，會多擠出好幾顆按鈕）。
            'hmallProducts' => $this->searchService->searchHmallProducts($query)->onEachSide(1),
        ]);
    }
}
