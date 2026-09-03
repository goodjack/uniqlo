{{--
    章節的捲動錨點，放在該段的 <h2> 前面。

    anchor  這一段的 id，選單項目的 href 指向它
    menu    章節選單的 id，捲動監聽靠 data-scrollspy 把兩邊對起來

    偏移量在 app.css 的 .uq-section-anchor：跳轉與 active 換手用同一個位置。
--}}
<span class="uq-section-anchor" id="{{ $anchor }}" data-scrollspy="{{ $menu }}"></span>
