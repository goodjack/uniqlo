<?php

namespace App\Http\Controllers;

use App\Models\HmallProduct;
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
            $results = HmallProduct::select(['brand', 'product_code'])->where('code', $query)->get()
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
     */
    private function showProductCodeResults(string $query)
    {
        return view('search.results', [
            'query' => $query,
            'isProductCodeSearch' => true,
            'hmallProducts' => HmallProduct::where('code', $query)->orderBy('min_price')->get(),
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
            'hmallProducts' => $this->searchService->searchHmallProducts($query),
        ]);
    }
}
