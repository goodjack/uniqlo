/**
 * 收藏清單。
 *
 * 整份清單只存在使用者自己的瀏覽器，不會送到伺服器；伺服器只會收到一批品牌加
 * 商品編號用來換卡片。每一筆只存品牌、商品編號與加入時間——收藏頁刻意不顯示
 * 任何價格，所以這裡也沒有價格可以存。
 */
window.UqFavorites = (function () {
    const STORAGE_KEY = 'uq-favorites';

    /**
     * 後端一次最多收 100 件，超過整批 422。收藏數量由使用者決定，所以這裡
     * 分批送再把卡片接起來，不讓「收藏很多」變成整頁載入失敗。
     */
    const BATCH_SIZE = 100;

    const DEFAULT_SUMMARY = '只存在這個瀏覽器，換裝置看不到';

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

    /**
     * 明確地移除，不是切換。收藏頁的移除鈕不能用 toggle：使用者在另一個分頁
     * 已經移掉同一件商品時，toggle 會把它加回去。
     *
     * 回傳是否真的寫進去了，呼叫端才能決定要不要動畫面。
     */
    function remove(brand, code) {
        const favorites = read();

        delete favorites[keyOf(brand, code)];

        return write(favorites);
    }

    function items() {
        return Object.values(read()).map(function (item) {
            return { brand: item.brand, code: item.code };
        });
    }

    function paintButton(button, isFavorite) {
        button.classList.toggle('active', isFavorite);
        // Tocas 的 basic 加 active 會變成深底配深字（實測對比度 1.46，AA 門檻是 4.5）。
        // 拿掉 basic 之後的 active 是 5.64，可讀。
        button.classList.toggle('basic', !isFavorite);
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
     * 綁定每一列的移除按鈕。
     *
     * 移除後只把那一列從畫面拿掉，不重新跟伺服器要一次——清單本來就在瀏覽器裡，
     * 沒有需要重新對齊的狀態。onChange 收到的是畫面上還剩幾列。
     */
    function bindRemoveButtons(container, onChange) {
        container.querySelectorAll('[data-favorite-remove]').forEach(function (control) {
            const removeRow = function (event) {
                event.preventDefault();
                event.stopPropagation();

                // 寫不進去就不要動畫面：那一件其實還在收藏裡，重新整理會再出現
                if (!remove(control.dataset.brand, control.dataset.code)) {
                    return;
                }

                const row = control.closest('[data-favorite-key]');

                if (row) {
                    row.remove();
                }

                onChange(container.querySelectorAll('[data-favorite-key]').length);
            };

            // 真的 <button>，Enter 與空白鍵瀏覽器自己會轉成 click，不必另外聽 keydown
            control.addEventListener('click', removeRow);
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

    /**
     * 三種空的情況要分開講：完全沒收藏、收藏的商品都下架了、以及這次載入失敗。
     * 講成同一句會讓使用者以為自己的收藏不見了。
     */
    /**
     * 「全部清除」在工具列上，一件收藏都沒有的時候整條工具列不該佔版面。
     * 判準看 localStorage 還剩幾筆，不是畫面上還剩幾列——下架的商品換不到卡片，
     * 本來就不會出現在畫面上，但它們還在收藏裡、還清得掉。
     */
    function syncToolbar() {
        const toolbar = document.getElementById('favorites-toolbar');

        if (toolbar) {
            toolbar.hidden = items().length === 0;
        }
    }

    function showState(name, summaryText) {
        ['favorites-empty', 'favorites-gone', 'favorites-error'].forEach(function (id) {
            document.getElementById(id).hidden = id !== name;
        });

        if (summaryText !== undefined) {
            document.getElementById('favorites-summary').textContent = summaryText;
        }
    }

    function summaryFor(count) {
        return '共 ' + count + ' 件，只存在這個瀏覽器';
    }

    /**
     * 一批一批換卡片，任一批失敗就整個失敗——只渲染一半的清單比讀不到更難懂。
     */
    async function fetchCards(options, wanted) {
        let html = '';

        for (let start = 0; start < wanted.length; start += BATCH_SIZE) {
            const response = await fetch(options.cardsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': options.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ items: wanted.slice(start, start + BATCH_SIZE) }),
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            html += await response.text();
        }

        return html;
    }

    async function renderPage(options) {
        const container = document.getElementById('favorites-cards');
        const loading = document.getElementById('favorites-loading');
        const summary = document.getElementById('favorites-summary');
        const wanted = items();

        container.innerHTML = '';
        showState(null);

        document.getElementById('favorites-retry').onclick = function () {
            renderPage(options);
        };

        // 清空鈕在「都已下架」與「載入失敗」兩種狀態各有一顆，所以用屬性不用 id
        document.querySelectorAll('[data-favorites-clear]').forEach(function (button) {
            button.onclick = function () {
                write({});
                container.innerHTML = '';
                syncToolbar();
                showState('favorites-empty', DEFAULT_SUMMARY);
            };
        });

        syncToolbar();

        if (wanted.length === 0) {
            loading.hidden = true;
            showState('favorites-empty', DEFAULT_SUMMARY);

            return;
        }

        // 「載入中」要在等回應的時候看得到，所以顯示與收起都夾著 fetch
        loading.hidden = false;

        let html;

        try {
            html = await fetchCards(options, wanted);
        } catch (e) {
            showState('favorites-error', '收藏清單還在你的瀏覽器裡');

            return;
        } finally {
            loading.hidden = true;
        }

        container.innerHTML = html;
        revealLazyImages(container);

        // 商品可能已經下架，回來的卡片會比收藏的少
        const rendered = container.querySelectorAll('[data-favorite-key]');

        // 有收藏、但回來的卡片是空的，代表那些商品都下架了
        if (rendered.length === 0) {
            showState('favorites-gone');

            return;
        }

        bindRemoveButtons(container, function (remaining) {
            syncToolbar();

            if (remaining > 0) {
                summary.textContent = summaryFor(remaining);

                return;
            }

            /*
             * 畫面上沒有列了不代表收藏是空的：下架的商品換不到卡片，本來就
             * 不會出現在畫面上。判準要看 localStorage 還剩幾筆，否則 5 筆收藏
             * 有 3 筆下架時，移完 2 列會說「還沒有收藏任何商品」，重新整理
             * 又變成「都已下架」。
             */
            if (items().length === 0) {
                showState('favorites-empty', DEFAULT_SUMMARY);

                return;
            }

            showState('favorites-gone');
        });

        showState(null);

        summary.textContent = summaryFor(rendered.length);
    }

    function bindAll() {
        document.querySelectorAll('[data-favorite-button]').forEach(bindButton);
    }

    return {
        has: has,
        toggle: toggle,
        remove: remove,
        items: items,
        bindButton: bindButton,
        bindAll: bindAll,
        renderPage: renderPage,
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    window.UqFavorites.bindAll();
});
