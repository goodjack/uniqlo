<?php

namespace App\View\Components;

use Illuminate\View\Component;

/**
 * 區段元件
 * 用於包裝頁面中的主要內容區塊，支援標題、副標題、格線系統等功能
 */
class Section extends Component
{
    /**
     * Create a new component instance.
     *
     * @param  string|null  $title  區段標題
     * @param  string|null  $subTitle  副標題（可選）
     * @param  bool  $secondary  是否使用 secondary 樣式
     * @param  bool  $tertiary  是否使用 tertiary 樣式
     * @param  bool  $inverted  是否使用 inverted 樣式
     * @param  string  $padded  內距程度（預設為 very，可選：very、normal、none）
     * @param  string|null  $grid  grid 的類型（例如：stackable、relaxed、relaxed stackable）
     * @param  bool  $veryNarrow  是否使用 very narrow 樣式
     *
     * @return void
     */
    public function __construct(
        public ?string $title = '',
        public ?string $subTitle = null,
        public bool $secondary = false,
        public bool $tertiary = false,
        public bool $inverted = false,
        public string $padded = 'very',
        public ?string $grid = '',
        public bool $veryNarrow = false,
    ) {
        // 確保 secondary 和 tertiary 不會同時為 true
        if ($this->secondary && $this->tertiary) {
            $this->tertiary = false;
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('components.section');
    }
}
