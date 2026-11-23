{{-- 燈箱裡播影片用的播放器（GLightbox 的影片類型要用 Plyr）。只在有影片的頁面載；
     GLightbox 開影片時看到頁面已經有同一個網址或播放器已載入，就不會再插一次，網址由 product-photos.js 照這兩個 id 讀。
     data-icon-url 是播放器的圖示檔：不指定的話 Plyr 會到它自己的網站抓 --}}
<link rel="stylesheet" id="uq-plyr-css" href="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.6.12/plyr.css"
    integrity="sha512-DiRh+DVNccYmgFhKZTU84F7KVYu5baUooO4zEY5rD2CLDp2hRZ/OvAFKbtpgHEgE99bPh6p17j0I/AN0qXDSCA=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<script id="uq-plyr-js" src="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.6.12/plyr.min.js"
    integrity="sha512-KD7SjO7VUcKW975+6TGB/h/E//W8Pei+W9806myhzEwekQ9W82Ne5jUMa2JMVn+pqSICZDVnvckAhTUwfON+pA=="
    data-icon-url="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.6.12/plyr.svg"
    crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
