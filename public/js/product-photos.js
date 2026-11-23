'use strict';

// 照片用 GLightbox 放大，手機可以左右滑；同一個 data-lightbox 值算一本相簿。
// 說明讀卡片裡轉義過的 template（data-description），不讀會被當成 HTML 的 data-title
(function () {
    if (typeof GLightbox !== 'function') {
        return;
    }

    const links = [...document.querySelectorAll('a[data-lightbox]')];
    const plyrCss = document.getElementById('uq-plyr-css');
    const plyrJs = document.getElementById('uq-plyr-js');
    // GLightbox 在 768px 以下換成手機版排法；它只在建立時讀連結上的設定，所以跨過這條線就拆掉重建
    const desktopLayout = window.matchMedia('(min-width: 769px)');
    let lightboxes = [];

    function build() {
        lightboxes.forEach(lightbox => lightbox.destroy());

        links.forEach(link => {
            // 每張的高度上限（官方的 data-height）：預設是整個畫面高，照片加說明會貼滿上下；改小才留得出上下空白、整組置中。
            // 手機版 GLightbox 會把它直接當成照片高度，照片會被撐出畫面，所以只給電腦版
            if (desktopLayout.matches) {
                link.dataset.height = '86vh';
            } else {
                delete link.dataset.height;
            }
        });

        lightboxes = [...new Set(links.map(link => link.dataset.lightbox))].map(name => GLightbox({
            selector: `a[data-lightbox="${CSS.escape(name)}"]`,
            // 預設開關是從畫面中央放大縮小，跟點的那張縮圖沒有關係；淡入淡出比較安靜。
            // 減少動態效果交給 app.css 統一處理，社群熱推的放大圖也一起生效
            openEffect: 'fade',
            closeEffect: 'fade',
            moreText: '顯示更多',
            touchNavigation: true,
            loop: false,
            // 商品實照裡的影片也在同一組，GLightbox 用 Plyr 播。播放器由頁面先載（partials/video-player），
            // 網址照抄頁面上的那兩個標籤，GLightbox 才認得出已經載過、不會再插一次。
            // 比例照官網影片的 3:4（預設 16:9 會在直式影片左右補黑邊）；靜音才能在手機上自動播，跟頁面上的預覽一樣；
            // Plyr 的方向鍵是快轉，會搶走燈箱左右換張，關掉。
            // 寬度給 64vh：3:4 的影片高約 85vh，跟照片的 86vh 一樣留出上下空白（手機上 GLightbox 會自己縮到畫面寬）
            videosWidth: '64vh',
            plyr: {
                ...(plyrCss && plyrJs ? { css: plyrCss.getAttribute('href'), js: plyrJs.getAttribute('src') } : {}),
                config: {
                    ...(plyrJs ? { iconUrl: plyrJs.dataset.iconUrl } : {}),
                    ratio: '3:4',
                    muted: true,
                    keyboard: { focused: false, global: false },
                    fullscreen: { enabled: true, iosNative: true },
                },
            },
        }));
    }

    build();
    desktopLayout.addEventListener('change', build);
})();
