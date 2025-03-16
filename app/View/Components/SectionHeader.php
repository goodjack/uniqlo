<?php

namespace App\View\Components;

use Illuminate\View\Component;

class SectionHeader extends Component
{
    /**
     * Create a new component instance.
     *
     * @param  string  $title  區段標題
     * @param  string|null  $subTitle  副標題（可選）
     * @param  string|null  $rightAction  右側動作（可選）
     *
     * @return void
     */
    public function __construct(
        public string $title,
        public ?string $subTitle = null,
        public ?string $rightAction = null,
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.section-header');
    }
}
