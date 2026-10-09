<?php

namespace App\Http\Requests;

use App\Enums\Brand;
use App\Enums\ProductTag;
use App\Services\ListService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    private const MAX_QUERY_LENGTH = 50;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * 篩選參數來自網址，舊連結、爬蟲或手改網址都會帶進不合法的值。
     * 一律丟掉不合法的部分、照常顯示頁面，不讓驗證失敗把人轉走。
     *
     * 同時改寫全域的 request()：Blade 讀的是那一份，FormRequest 只是它的副本。
     *
     * 只認網址參數，而 input() 與驗證讀的是「網址參數＋內文」：帶 JSON 內文的
     * GET 會讓內文蓋過整理好的值，所以內文裡的同名欄位一併拿掉。
     */
    protected function prepareForValidation()
    {
        $normalized = [
            'brand' => self::brandFrom($this),
            'sort' => $this->query('sort') === ListService::SORT_PRICE_ASC ? ListService::SORT_PRICE_ASC : null,
            'tags' => $this->normalizeTags($this->query('tags')),
            'q' => self::keywordFrom($this),
        ];

        foreach ($normalized as $key => $value) {
            foreach ([$this, request()] as $request) {
                $request->request->remove($key);
                $request->json()->remove($key);

                if ($value === null) {
                    $request->query->remove($key);
                } else {
                    $request->query->set($key, $value);
                }
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'brand' => ['nullable', Rule::enum(Brand::class)],
            'sort' => ['nullable', Rule::in([ListService::SORT_PRICE_ASC])],
            'tags' => 'nullable|array',
            'tags.*' => [Rule::enum(ProductTag::class)],
            'q' => ['nullable', 'string', 'max:'.self::MAX_QUERY_LENGTH],
        ];
    }

    /**
     * 網址上的品牌篩選。不經過 ListRequest 的頁面（導覽列、頁尾）帶品牌連結時
     * 也讀這裡，才不會把內文或不合法的值帶進連結。
     */
    public static function brandFrom(Request $request): ?string
    {
        $brand = $request->query('brand');

        return is_string($brand) ? Brand::tryFrom($brand)?->value : null;
    }

    /**
     * 網址上的清單內關鍵字。限流器在 ListRequest 之前就要判斷「有沒有關鍵字」，
     * 兩邊必須讀同一份、用同一套清理，否則內文帶 q 就能繞過限流。
     *
     * 超長的關鍵字截斷而不是拒絕，使用者只會看到搜尋框內容被裁掉。
     */
    public static function keywordFrom(Request $request): ?string
    {
        $q = $request->query('q');

        if (! is_string($q)) {
            return null;
        }

        $q = mb_substr(trim($q), 0, self::MAX_QUERY_LENGTH);

        return $q === '' ? null : $q;
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeTags(mixed $tags): ?array
    {
        if (! is_array($tags)) {
            return null;
        }

        $values = array_map(fn (ProductTag $tag) => $tag->value, ProductTag::fromValues($tags));

        return $values === [] ? null : $values;
    }
}
