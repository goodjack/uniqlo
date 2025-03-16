<?php

namespace App\View\Components;

use Illuminate\View\Component;

class SectionContainer extends Component
{
    /**
     * Create a new component instance.
     *
     * @param  bool  $secondary  是否使用 secondary 樣式
     * @param  bool  $tertiary  是否使用 tertiary 樣式
     * @param  string  $grid  grid 的類型（例如：stackable、relaxed、relaxed stackable）
     * @param  bool  $veryNarrow  是否使用 very narrow 樣式
     *
     * @return void
     */
    public function __construct(
        public bool $secondary = false,
        public bool $tertiary = false,
        public string $grid = '',
        public bool $veryNarrow = false,
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('components.section-container');
    }
}
