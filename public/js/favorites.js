/**
 * 收藏清單。
 *
 * 整份清單只存在使用者自己的瀏覽器，不會送到伺服器；伺服器只會收到一串商品編號
 * 用來換卡片。收藏時的價格也存在這裡，收藏頁靠它算出「跟你收藏時比降了多少」。
 */
window.UqFavorites = (function () {
    const STORAGE_KEY = 'uq-favorites';

    /**
     * 隱私模式或使用者關掉網站資料時，localStorage 的存取本身就會丟例外，
     * 所以每一次讀寫都要能安靜地退回「沒有收藏」的狀態，不能讓整頁掛掉。
     */
    function read() {
        try {
            return JSON.parse(window.localStorage.getItem(STORAGE_KEY)) || {};
        } catch (e) {
            return {};
        }
    }

    function write(favorites) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(favorites));
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * 兩家共用同一組商品編號，所以 key 一定要帶品牌，
     * 不然 UNIQLO 的褲子跟 GU 的家居服會互相蓋掉。
     */
    function keyOf(brand, code) {
        return brand + ':' + code;
    }

    function has(brand, code) {
        return Object.prototype.hasOwnProperty.call(read(), keyOf(brand, code));
    }

    function toggle(brand, code) {
        const favorites = read();
        const key = keyOf(brand, code);

        if (has(brand, code)) {
            delete favorites[key];
        } else {
            favorites[key] = { brand: brand, code: code, addedAt: new Date().toISOString() };
        }

        write(favorites);

        return has(brand, code);
    }

    function items() {
        return Object.values(read()).map(function (item) {
            return { brand: item.brand, code: item.code };
        });
    }

    function paintButton(button, isFavorite) {
        button.classList.toggle('active', isFavorite);
        button.querySelector('.label').textContent = isFavorite ? '已收藏' : '收藏';
        button.querySelector('.icon').className = isFavorite ? 'heart icon' : 'heart outline icon';
        button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
    }

    function bindButton(button) {
        const brand = button.dataset.brand;
        const code = button.dataset.productCode;

        paintButton(button, has(brand, code));

        button.addEventListener('click', function () {
            paintButton(button, toggle(brand, code));
        });
    }

    /**
     * master 版型的 lazy-load 腳本只在 DOMContentLoaded 跑一次，
     * 之後動態插進來的圖片不會被處理，data-src 永遠不會搬到 src。
     */
    function revealLazyImages(container) {
        container.querySelectorAll('img[data-src]').forEach(function (img) {
            img.src = img.dataset.src;
        });
    }

    async function renderPage(options) {
        const container = document.getElementById('favorites-cards');
        const loading = document.getElementById('favorites-loading');
        const empty = document.getElementById('favorites-empty');
        const summary = document.getElementById('favorites-summary');
        const wanted = items();

        loading.hidden = true;

        if (wanted.length === 0) {
            empty.hidden = false;
            return;
        }

        let html = '';

        try {
            const response = await fetch(options.cardsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': options.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ items: wanted }),
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            html = await response.text();
        } catch (e) {
            summary.textContent = '載入收藏時發生問題，請稍後再試一次';
            return;
        }

        container.innerHTML = html;
        revealLazyImages(container);

        // 商品可能已經下架，回來的卡片會比收藏的少
        const rendered = container.querySelectorAll('[data-favorite-key]');

        if (rendered.length === 0) {
            empty.hidden = false;
            return;
        }

        summary.textContent = '共 ' + rendered.length + ' 件，只存在這個瀏覽器';
    }

    function bindAll() {
        document.querySelectorAll('[data-favorite-button]').forEach(bindButton);
    }

    return { has: has, toggle: toggle, items: items, bindButton: bindButton, bindAll: bindAll, renderPage: renderPage };
})();

document.addEventListener('DOMContentLoaded', function () {
    window.UqFavorites.bindAll();
});
