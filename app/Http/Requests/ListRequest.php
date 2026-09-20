<?php

namespace App\Http\Requests;

use App\Enums\Brand;
use App\Enums\ProductTag;
use App\Services\ListService;
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
     * 清單／分類內搜尋的關鍵字先修剪再截斷，不讓超長輸入變成 422。
     *
     * 一般網頁導覽（Accept: text/html）驗證失敗時 Laravel 是導回上一頁而不是
     * 回 422，這裡截斷成不會觸發規則失敗的長度，使用者只會看到搜尋框內容
     * 被裁掉，而不是整頁失敗。
     *
     * $this->merge() 只改到這個 FormRequest 自己那份副本，Controller 跟
     * Service 拿到的是這一份沒錯；但 FormRequestServiceProvider 沒有把容器
     * 裡的 'request' 換成這份副本（只是從舊的複製資料過來建新的），Blade 裡
     * 到處在用的全域 request() helper 解析到的還是原本沒截斷過的那個實例。
     * 這裡額外對 request() 也 merge 一次，兩邊才會看到同一個修剪過的值。
     */
    protected function prepareForValidation()
    {
        if (! $this->has('q')) {
            return;
        }

        // ?q[]=x 這種陣列輸入不能直接 (string) 轉型：PHP 會發 Array to string
        // conversion warning，Laravel 的 error handler 把它轉成 ErrorException，
        // 頁面還沒走到 rules() 的 'string' 規則就先 500 了。不是字串就當作沒填，
        // 讓使用者看到的是搜尋框是空的，而不是整頁壞掉。
        $q = $this->input('q');
        $normalizedQ = is_string($q) ? mb_substr(trim($q), 0, 50) : '';

        $this->merge(['q' => $normalizedQ]);
        request()->merge(['q' => $normalizedQ]);
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
            'sort' => ['nullable', Rule::in([ListService::SORT_PRICE_ASC])],
            'tags' => 'nullable|array|max:'.count(ProductTag::cases()),
            // 可選的標籤就是 enum 定義的那些，不另外維護一份清單。
            // 已售罄不在其中：清單與分類本來就只收還買得到的商品。
            'tags.*' => [Rule::enum(ProductTag::class)],
            // 清單／分類內搜尋，品名或編號含關鍵字。prepareForValidation() 已經
            // 修剪並截斷成 50 字以內，這條規則是防呆，不是真正擋住超長輸入的關卡
            'q' => ['nullable', 'string', 'max:50'],
        ];
    }
}
