<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'brand' => 'nullable|in:UNIQLO,GU',
            'sort' => 'nullable|in:price-asc',
            'tags' => 'nullable|array|max:6',
            // 不收 stockout：清單與分類本來就只收還買得到的商品，篩它永遠沒有結果。
            // app-offer 與 ec-only 併在 limited-offer 底下，不單獨開。
            'tags.*' => 'in:lowest-price,limited-offer,sale,new,coming-soon,multi-buy,online-special',
        ];
    }
}
