<?php

namespace App\Http\Requests;

use App\Enums\Brand;
use App\Enums\ProductTag;
use App\Services\ListService;
use Illuminate\Foundation\Http\FormRequest;
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
     */
    protected function prepareForValidation()
    {
        $normalized = [
            'brand' => $this->normalizeBrand($this->query('brand')),
            'sort' => $this->query('sort') === ListService::SORT_PRICE_ASC ? ListService::SORT_PRICE_ASC : null,
            'tags' => $this->normalizeTags($this->query('tags')),
            'q' => $this->normalizeQuery($this->query('q')),
        ];

        foreach ($normalized as $key => $value) {
            foreach ([$this, request()] as $request) {
                if ($value === null) {
                    $request->offsetUnset($key);
                } else {
                    $request->merge([$key => $value]);
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

    private function normalizeBrand(mixed $brand): ?string
    {
        return is_string($brand) ? Brand::tryFrom($brand)?->value : null;
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

    /**
     * 超長的關鍵字截斷而不是拒絕，使用者只會看到搜尋框內容被裁掉。
     */
    private function normalizeQuery(mixed $q): ?string
    {
        if (! is_string($q)) {
            return null;
        }

        $q = mb_substr(trim($q), 0, self::MAX_QUERY_LENGTH);

        return $q === '' ? null : $q;
    }
}
