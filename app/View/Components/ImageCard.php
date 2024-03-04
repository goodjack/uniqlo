<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ImageCard extends Component
{
    /**
     * The image link.
     *
     * @var string
     */
    public $link;

    /**
     * The image Url.
     *
     * @var string
     */
    public $imageUrl;

    /**
     * The large image Url.
     *
     * @var string
     */
    public $largeImageUrl;

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
     * The image color header.
     *
     * @var string
     */
    public $colorHeader;

    /**
     * The country from which the image was fetched.
     *
     * @var string
     */
    public $country;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        $link,
        $imageUrl,
        $largeImageUrl,
        $alt,
        $width = 1,
        $height = 1,
        $colorHeader = null,
        $country = null
    ) {
        $this->link = $link;
        $this->imageUrl = $imageUrl;
        $this->largeImageUrl = $largeImageUrl;
        $this->alt = $alt;
        $this->width = $width;
        $this->height = $height;
        $this->colorHeader = $colorHeader;
        $this->country = $country;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.image-card');
    }
}
