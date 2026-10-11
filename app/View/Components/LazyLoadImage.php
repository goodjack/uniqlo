<?php

namespace App\View\Components;

use Illuminate\View\Component;

class LazyLoadImage extends Component
{
    /**
     * The image src.
     *
     * @var string
     */
    public $src;

    /**
     * The image alt.
     *
     * @var string
     */
    public $alt;

    /**
     * The image width.
     *
     * @var string
     */
    public $width;

    /**
     * The image height.
     *
     * @var string
     */
    public $height;

    /**
     * 首屏主圖不延後：直接給 src 並提高下載優先順序，LCP 才不用等頁尾的腳本補 src。
     *
     * @var bool
     */
    public $priority;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($src, $alt, $width = 3, $height = 4, $priority = false)
    {
        $this->priority = (bool) $priority;
        $this->src = $src;
        $this->alt = $alt;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.lazy-load-image');
    }
}
