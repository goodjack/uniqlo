{{-- 照片放大圖：GLightbox 與共用設定（product-photos.js）。有 x-image-card 或 data-lightbox 連結的頁面放在 javascript 區段 --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/glightbox/3.3.0/css/glightbox.min.css"
    integrity="sha512-T+KoG3fbDoSnlgEXFQqwcTC9AdkFIxhBlmoaFqYaIjq2ShhNwNao9AKaLUPMfwiBPL0ScxAtc+UYbHAgvd+sjQ=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/glightbox/3.3.0/js/glightbox.min.js"
    integrity="sha512-RBWI5Qf647bcVhqbEnRoL4KuUT+Liz+oG5jtF+HP05Oa5088M9G0GxG0uoHR9cyq35VbjahcI+Hd1xwY8E1/Kg=="
    crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
<script src="{{ asset('js/product-photos.js') }}?v={{ filemtime(public_path('js/product-photos.js')) }}" defer></script>
