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
     * 移除、清空都是「先動手、再給復原機會」，不是先跳確認。8 秒是跟清空收藏
     * 的確認視窗一起定案的數字：夠讓使用者反應過來，又不會長到擋在畫面上。
     */
    const UNDO_DURATION_MS = 8000;

    /**
     * 記住最近一次 renderPage 用的參數，清空收藏的復原不是在 renderPage 的
     * closure 裡觸發（它綁在工具列的固定按鈕上，只在 DOMContentLoaded 綁一次），
     * 所以需要另外留一份可以呼叫 renderPage 的地方。
     */
    let currentOptions = null;

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

    /**
     * addedAt 是後來才加的欄位，舊使用者瀏覽器裡那批收藏沒有這個值，重新整理
     * 後也不會替它們補一個——補了就是造假的時間，會讓「最新收藏在前」失真。
     */
    function isValidAddedAt(value) {
        return typeof value === 'string' && !isNaN(new Date(value).getTime());
    }

    /**
     * 「最新收藏在前」的排序鍵。
     *
     * 規則：
     * 1. addedAt 有效的依時間由新到舊。
     * 2. 缺少時間或時間無效的舊收藏全部排在後面。
     * 3. 同一類（都有效或都無效）時間相同（或都沒有時間）時，維持目前存在
     *    localStorage 裡的先後順序——不補造假的時間，也不讓每次重新整理都跳動。
     */
    function sortedKeys(favorites) {
        return Object.keys(favorites)
            .map(function (key, index) {
                return { key: key, index: index };
            })
            .sort(function (a, b) {
                const itemA = favorites[a.key];
                const itemB = favorites[b.key];
                const validA = isValidAddedAt(itemA.addedAt);
                const validB = isValidAddedAt(itemB.addedAt);

                if (validA && validB) {
                    const diff = new Date(itemB.addedAt).getTime() - new Date(itemA.addedAt).getTime();

                    return diff !== 0 ? diff : a.index - b.index;
                }

                if (validA !== validB) {
                    return validA ? -1 : 1;
                }

                return a.index - b.index;
            })
            .map(function (entry) {
                return entry.key;
            });
    }

    function items() {
        const favorites = read();

        return sortedKeys(favorites).map(function (key) {
            const item = favorites[key];

            return { brand: item.brand, code: item.code };
        });
    }

    /**
     * 移除與清空共用的復原提示條。外觀是 Tocas 的 .ts.snackbar，但 Tocas 的
     * ts().snackbar() 計時是寫死的 3.5 秒、沒有暫停機制，8 秒與滑鼠／鍵盤焦點
     * 暫停倒數這裡自己控制，只借它的 class 跟 CSS。
     */
    const Snackbar = (function () {
        let el = null;
        let contentEl = null;
        let actionEl = null;
        let current = null; // { onUndo, onExpire }
        let hovering = false;
        let focused = false;
        let remaining = 0;
        let tickStartedAt = 0;
        let timerId = null;

        function ensure() {
            if (el) {
                return true;
            }

            el = document.getElementById('favorites-snackbar');

            if (!el) {
                return false;
            }

            contentEl = el.querySelector('.content');
            actionEl = el.querySelector('.action');

            el.addEventListener('mouseenter', function () {
                hovering = true;
                pause();
            });

            el.addEventListener('mouseleave', function () {
                hovering = false;
                resume();
            });

            // focusin／focusout 會冒泡，掛在容器上就能知道焦點有沒有落在
            // 提示條（或它裡面唯一能拿到焦點的「復原」按鈕）身上。
            el.addEventListener('focusin', function () {
                focused = true;
                pause();
            });

            el.addEventListener('focusout', function () {
                focused = false;
                resume();
            });

            actionEl.addEventListener('click', function () {
                if (!current) {
                    return;
                }

                const onUndo = current.onUndo;

                hide();
                onUndo();
            });

            return true;
        }

        function clearScheduled() {
            if (timerId !== null) {
                clearTimeout(timerId);
                timerId = null;
            }
        }

        function scheduleTick() {
            clearScheduled();
            tickStartedAt = Date.now();
            timerId = setTimeout(onTimerFire, remaining);
        }

        function pause() {
            if (timerId === null) {
                return;
            }

            remaining -= (Date.now() - tickStartedAt);

            if (remaining < 0) {
                remaining = 0;
            }

            clearScheduled();
        }

        function resume() {
            if (hovering || focused || !current || timerId !== null) {
                return;
            }

            scheduleTick();
        }

        function onTimerFire() {
            timerId = null;

            const finished = current;

            hide();

            if (finished && finished.onExpire) {
                finished.onExpire();
            }
        }

        function activate(content, onUndo, onExpire) {
            if (!ensure()) {
                return;
            }

            current = { onUndo: onUndo, onExpire: onExpire };
            contentEl.textContent = content;
            el.classList.add('active');
            hovering = false;
            focused = false;
            remaining = UNDO_DURATION_MS;
            scheduleTick();
        }

        function hide() {
            clearScheduled();
            current = null;

            if (el) {
                el.classList.remove('active');
            }
        }

        /**
         * 開一條全新、跟現有這條無關的復原（例如清空收藏蓋掉還沒過期的單筆
         * 移除）：先讓舊的那條直接失效（呼叫它的 onExpire），再開新的一條。
         */
        function show(content, onUndo, onExpire) {
            if (current) {
                const stale = current;

                clearScheduled();
                current = null;

                if (stale.onExpire) {
                    stale.onExpire();
                }
            }

            activate(content, onUndo, onExpire);
        }

        /**
         * 延續現有這條（移除多件時合併成同一條、重新計時）。沒有現有的就
         * 等同 show——這裡不呼叫舊的 onExpire，因為呼叫端傳進來的 onUndo／
         * onExpire 本來就是同一批待復原項目的最新版本。
         */
        function update(content, onUndo, onExpire) {
            activate(content, onUndo, onExpire);
        }

        return { show: show, update: update };
    })();

    /**
     * 文字固定顯示「收藏」，不隨狀態改變——切換按鈕（toggle button）的可及名稱
     * 不該隨狀態變（WAI-ARIA 的 button 模式），狀態交給 aria-pressed 與品牌紅
     * 實心愛心。之前這裡連文字帶 aria-pressed 一起換，兩個一起用等於自己打架。
     */
    function paintButton(button, isFavorite) {
        button.classList.toggle('active', isFavorite);
        // 底色與文字顏色由 app.css 的收藏鈕專屬規則負責（背景透明、已收藏時愛心紅色）。
        // basic 類別兩種狀態都保留，讓邊框與未收藏、與分享鈕保持一致的視覺感受。
        button.querySelector('.icon').className = isFavorite ? 'heart icon' : 'heart outline icon';
        button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
    }

    /**
     * 卡片上的收藏鈕只有一顆愛心，沒有文字可以換，所以上色跟商品頁那顆不一樣：
     * 只換 icon 的實心與否，狀態交給 aria-pressed（CSS 也是讀它上色）。
     * 可及名稱是固定的「收藏 商品名」，不隨狀態改。
     */
    function paintCardButton(button, isFavorite) {
        button.querySelector('.icon').className = isFavorite ? 'heart icon' : 'heart outline icon';
        button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
    }

    function bindCardButton(button) {
        const brand = button.dataset.brand;
        const code = button.dataset.productCode;

        paintCardButton(button, has(brand, code));

        button.addEventListener('click', function () {
            paintCardButton(button, toggle(brand, code));
        });
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
     * 按下就整列立刻消失、資料也立刻寫掉，不是「先確認」——復原走的是提示條
     * 的 8 秒，不是攔住這次點擊。連續按好幾顆會合併成同一條提示、復原一次
     * 全部拿回來；復原用的是原本的 addedAt，不是重新收藏的新時間，所以商品
     * 會回到原來的排序位置。
     *
     * onChange 收到的是畫面上還剩幾列，跟移除之前一樣。
     */
    function bindRemoveButtons(container, options, onChange) {
        let pending = null; // { key: 該筆原始資料, ... }，只在還沒過期／還沒復原前存在

        function undoLabel(count) {
            return count === 1 ? '已移除收藏' : ('已移除 ' + count + ' 件收藏');
        }

        function restore(entries) {
            const favorites = read();

            Object.keys(entries).forEach(function (key) {
                favorites[key] = entries[key];
            });

            write(favorites);
            renderPage(options);
        }

        container.querySelectorAll('[data-favorite-remove]').forEach(function (control) {
            control.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const brand = control.dataset.brand;
                const code = control.dataset.code;
                const key = keyOf(brand, code);
                const snapshot = read()[key];

                // 寫不進去（或這筆其實已經不在收藏裡）就不要動畫面
                if (!snapshot || !remove(brand, code)) {
                    return;
                }

                const row = control.closest('[data-favorite-key]');

                if (row) {
                    row.remove();
                }

                onChange(container.querySelectorAll('[data-favorite-key]').length);

                if (!pending) {
                    pending = {};
                }

                pending[key] = snapshot;

                const batch = pending;

                Snackbar.update(undoLabel(Object.keys(batch).length), function () {
                    pending = null;
                    restore(batch);
                }, function () {
                    pending = null;
                });
            });
        });
    }

    /**
     * 清空收藏：按鈕不直接清空，先開確認視窗；確認後才真的清空，並且一樣給
     * 8 秒的復原提示條。三個「清空收藏」入口（工具列、都已下架、載入失敗）
     * 共用同一顆對話視窗，只在 DOMContentLoaded 綁一次——不能放進 bindAll，
     * 那個會在每次 renderPage 重新渲染卡片時被呼叫，重複綁定會讓對話視窗的
     * 按鈕跟背景關閉都疊加好幾份監聽。
     */
    function bindClearAll() {
        const dialog = document.getElementById('favorites-clear-modal');

        if (!dialog) {
            return;
        }

        const countEl = document.getElementById('favorites-clear-count');
        const closeIcon = dialog.querySelector('.close.icon');
        const cancelBtn = dialog.querySelector('.js-favorites-clear-cancel');
        const dimmer = dialog.closest('.ts.modals.dimmer');
        let lastTrigger = null;

        function restoreFocus() {
            if (lastTrigger && lastTrigger.offsetParent !== null) {
                lastTrigger.focus();
            }
        }

        // Tocas 的對話視窗只是加上 open 屬性，不是原生 <dialog>.showModal()，
        // 不會自動把背景變成不可聚焦，Tab 陷阱要自己顧：只在「已經在第一個／
        // 最後一個可聚焦元素」時攔截，其餘照瀏覽器原生 tab 順序走。
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.stopPropagation();
                restoreFocus();
                ts(dialog).modal('hide');

                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusables = Array.prototype.slice
                .call(dialog.querySelectorAll('button, [href], [tabindex]'))
                .filter(function (candidate) {
                    return candidate.offsetParent !== null;
                });

            if (focusables.length === 0) {
                return;
            }

            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        if (closeIcon) {
            // 原生的 <i> 不會自己把 Enter／空白鍵轉成 click，用真的 button
            // 會動到 Tocas 對「關閉鈕在對話視窗左上角」的排版假設，這裡補
            // 鍵盤事件、外觀跟位置維持 Tocas 原生的 close icon。
            closeIcon.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    closeIcon.click();
                }
            });

            closeIcon.addEventListener('click', restoreFocus);
        }

        if (dimmer) {
            dimmer.addEventListener('click', function (event) {
                if (event.target === dimmer && dialog.classList.contains('closable')) {
                    restoreFocus();
                }
            });
        }

        ts(dialog).modal({
            approve: '.js-favorites-clear-confirm',
            deny: '.js-favorites-clear-cancel',
            onDeny: function () {
                restoreFocus();

                return true;
            },
            onApprove: function () {
                const snapshot = read();

                // 寫不進去就不要動畫面：收藏其實還在，硬清空畫面會讓使用者
                // 以為真的清掉了
                if (!write({})) {
                    showState('favorites-error', '清空失敗，請再試一次');

                    return true;
                }

                const container = document.getElementById('favorites-cards');

                container.innerHTML = '';
                syncToolbar();
                showState('favorites-empty', DEFAULT_SUMMARY);

                Snackbar.show('已清空收藏', function () {
                    const favorites = read();

                    Object.keys(snapshot).forEach(function (key) {
                        favorites[key] = snapshot[key];
                    });

                    write(favorites);
                    renderPage(currentOptions);
                }, function () {});

                restoreFocus();

                return true;
            },
        });

        document.querySelectorAll('[data-favorites-clear]').forEach(function (button) {
            button.addEventListener('click', function () {
                lastTrigger = button;
                // N 要看瀏覽器裡實際存的總數，包含下架、伺服器查不到而沒有
                // 顯示成商品列的那些——items() 讀的是 localStorage，不是畫面。
                countEl.textContent = items().length;
                ts(dialog).modal('show');
                cancelBtn.focus();
            });
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
     * 「清空收藏」在工具列上，一件收藏都沒有的時候整條工具列不該佔版面。
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
        currentOptions = options;

        const container = document.getElementById('favorites-cards');
        const loading = document.getElementById('favorites-loading');
        const summary = document.getElementById('favorites-summary');
        const wanted = items();

        container.innerHTML = '';
        showState(null);

        document.getElementById('favorites-retry').onclick = function () {
            renderPage(options);
        };

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
        // 動態插進來的內容不在 DOMContentLoaded 那一輪裡，要自己綁一次
        bindAll(container);

        // 商品可能已經下架，回來的卡片會比收藏的少
        const rendered = container.querySelectorAll('[data-favorite-key]');

        // 有收藏、但回來的卡片是空的，代表那些商品都下架了
        if (rendered.length === 0) {
            showState('favorites-gone');

            return;
        }

        bindRemoveButtons(container, options, function (remaining) {
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

    /**
     * 綁定範圍內所有的收藏鈕。不給 root 就是整份文件。
     */
    function bindAll(root) {
        const scope = root || document;

        scope.querySelectorAll('[data-favorite-button]').forEach(bindButton);
        scope.querySelectorAll('[data-favorite-card]').forEach(bindCardButton);
    }

    return {
        has: has,
        toggle: toggle,
        remove: remove,
        items: items,
        bindButton: bindButton,
        bindCardButton: bindCardButton,
        bindAll: bindAll,
        bindClearAll: bindClearAll,
        renderPage: renderPage,
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    window.UqFavorites.bindAll();
    window.UqFavorites.bindClearAll();
});
