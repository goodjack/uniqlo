<?php

namespace App\Http\Requests;

use App\Enums\Brand;
use App\Enums\ProductTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
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
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // 可選的品牌就是 Brand enum 的那兩個，不另外維護一份字串清單
            'brand' => ['nullable', Rule::enum(Brand::class)],
            'sort' => 'nullable|in:price-asc',
            'tags' => 'nullable|array|max:'.count(ProductTag::cases()),
            // 可選的標籤就是 enum 定義的那些，不另外維護一份清單。
            // 已售罄不在其中：清單與分類本來就只收還買得到的商品。
            'tags.*' => [Rule::enum(ProductTag::class)],
        ];
    }
}
