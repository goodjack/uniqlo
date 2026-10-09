/**
 * 收藏清單。
 *
 * 整份清單只存在使用者自己的瀏覽器，伺服器只會收到一批品牌加商品編號用來換
 * 卡片。每一筆只存品牌、編號與加入時間；收藏頁刻意不顯示價格，所以也不存價格。
 */
window.UqFavorites = (function () {
    const STORAGE_KEY = 'uq-favorites';
    const BATCH_SIZE = 100; // 跟 FavoriteController::MAX_CODES 一致，超過整批 422
    // 跟 App\Enums\Brand 與 FavoriteController 的單筆驗證一致；不合的那筆伺服器會略過，
    // 留在瀏覽器裡就永遠換不到卡片、還被算成「已經找不到」
    const BRANDS = ['UNIQLO', 'GU'];
    const MAX_CODE_LENGTH = 191;
    const FETCH_TIMEOUT_MS = 15000;
    const UNDO_DURATION_MS = 8000;
    const DEFAULT_SUMMARY = '只存在這個瀏覽器，換裝置看不到';
    const BUTTON_SELECTOR = '[data-favorite-button], [data-favorite-card]';

    let currentOptions = null;

    // 每輪 renderList 一個編號：清空收藏或新的一輪開始時，晚到的舊回應不能把畫面蓋回去
    let renderToken = 0;
    let currentController = null;
    let staleWhileHidden = false;

    // Array.from 數的是字元，跟伺服器 max:191 的 mb_strlen 一致（.length 數的是 UTF-16 單位）
    function isValidEntry(key, item) {
        return !!item
            && typeof item === 'object'
            && BRANDS.indexOf(item.brand) !== -1
            && typeof item.code === 'string'
            && item.code.trim() !== ''
            && Array.from(item.code).length <= MAX_CODE_LENGTH
            && key === keyOf(item.brand, item.code);
    }

    /**
     * 隱私模式或關掉網站資料時，localStorage 的存取本身就會丟例外，一律退回
     * 「沒有收藏」。不合規則的項目（使用者手改、擴充套件寫壞）丟掉並寫回，
     * 不讓一筆壞資料卡住整頁。
     */
    function read() {
        let parsed;

        try {
            parsed = JSON.parse(window.localStorage.getItem(STORAGE_KEY)) || {};
        } catch (e) {
            return {};
        }

        const favorites = {};
        let hasInvalidEntry = false;

        Object.keys(parsed).forEach(function (key) {
            const item = parsed[key];

            if (isValidEntry(key, item)) {
                favorites[key] = item;
            } else {
                hasInvalidEntry = true;
            }
        });

        if (hasInvalidEntry) {
            write(favorites);
        }

        return favorites;
    }

    function write(favorites) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(favorites));
            return true;
        } catch (e) {
            return false;
        }
    }

    // 兩家共用同一組商品編號，key 一定要帶品牌
    function keyOf(brand, code) {
        return brand + ':' + code;
    }

    function has(favorites, brand, code) {
        return Object.prototype.hasOwnProperty.call(favorites, keyOf(brand, code));
    }

    function toggle(brand, code) {
        const favorites = read();
        const key = keyOf(brand, code);

        if (has(favorites, brand, code)) {
            delete favorites[key];
        } else {
            favorites[key] = { brand: brand, code: code, addedAt: new Date().toISOString() };
        }

        write(favorites);
    }

    // 收藏頁的移除不能用 toggle：別的分頁已經移掉同一件時，toggle 會把它加回去
    function remove(brand, code) {
        const favorites = read();

        delete favorites[keyOf(brand, code)];

        return write(favorites);
    }

    function isValidAddedAt(value) {
        return typeof value === 'string' && !isNaN(new Date(value).getTime());
    }

    /**
     * 最新收藏在前。addedAt 是後來才加的欄位，早期的收藏沒有，也不替它們補
     * （補的就是假時間）：沒有時間的排在最後，同類之間維持存放順序，重新整理
     * 不會跳動。
     */
    function sortedKeys(favorites) {
        return Object.keys(favorites)
            .map(function (key, index) {
                return { key: key, index: index };
            })
            .sort(function (a, b) {
                const addedA = favorites[a.key].addedAt;
                const addedB = favorites[b.key].addedAt;
                const validA = isValidAddedAt(addedA);
                const validB = isValidAddedAt(addedB);

                if (validA !== validB) {
                    return validA ? -1 : 1;
                }

                const diff = validA ? new Date(addedB).getTime() - new Date(addedA).getTime() : 0;

                return diff !== 0 ? diff : a.index - b.index;
            })
            .map(function (entry) {
                return entry.key;
            });
    }

    function items() {
        const favorites = read();

        return sortedKeys(favorites).map(function (key) {
            return { brand: favorites[key].brand, code: favorites[key].code };
        });
    }

    /**
     * 移除與清空共用的復原提示條。只借 Tocas .ts.snackbar 的外觀：它內建的
     * 計時固定約 3.5 秒、不能暫停，這裡要 8 秒，而且滑鼠移上去或焦點在裡面時
     * 暫停倒數。
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
                const viaKeyboard = isKeyboardFocused(actionEl);

                hide();
                onUndo(viaKeyboard);
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

            remaining = Math.max(0, remaining - (Date.now() - tickStartedAt));
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

        function hide() {
            clearScheduled();
            current = null;

            if (el) {
                el.classList.remove('active');
                document.documentElement.style.removeProperty('--uq-bottom-overlay');
            }
        }

        /**
         * 顯示或延續一條提示並重新計時。expirePrevious 為 true 代表這是一件
         * 新的事（例如清空收藏蓋掉還沒過期的單筆移除），舊那條要先當成過期
         * 處理；連續移除多件則是延續同一條，呼叫端傳進來的 callback 已經是
         * 整批的最新版本。
         */
        function show(content, onUndo, onExpire, expirePrevious) {
            if (!ensure()) {
                return;
            }

            if (expirePrevious && current && current.onExpire) {
                current.onExpire();
            }

            current = { onUndo: onUndo, onExpire: onExpire };
            hovering = false;
            focused = false;
            remaining = UNDO_DURATION_MS;
            scheduleTick();

            // 即時區域在 display:none 時內容變動不會被唸，先顯示、下一格再寫字
            el.classList.add('active');
            // 回到頁首鈕固定在右下角，會蓋住提示條，讓它照這個高度往上讓開
            document.documentElement.style.setProperty('--uq-bottom-overlay', el.offsetHeight + 'px');
            contentEl.textContent = '';
            requestAnimationFrame(function () {
                contentEl.textContent = content;
            });
        }

        function focusUndo() {
            if (current) {
                actionEl.focus();
            }
        }

        return { show: show, focusUndo: focusUndo };
    })();

    /**
     * 可及名稱固定不變，狀態只靠 aria-pressed 與實心／空心愛心表達（WAI-ARIA
     * 的 toggle button 模式）。商品頁那顆另外掛 Tocas 的 active。
     */
    function paint(button, isFavorite) {
        if (button.hasAttribute('data-favorite-button')) {
            button.classList.toggle('active', isFavorite);
        }

        button.querySelector('.icon').className = isFavorite ? 'heart icon' : 'heart outline icon';
        button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
    }

    /**
     * 一律整頁重畫：同一件商品在同一頁可能有好幾顆愛心（男女適穿的商品同時在
     * 男裝與女裝段），漏畫的那顆停在舊狀態，使用者再按一次就把剛收藏的刪掉了。
     */
    function paintAll(root) {
        const favorites = read();

        (root || document).querySelectorAll(BUTTON_SELECTOR).forEach(function (button) {
            paint(button, has(favorites, button.dataset.brand, button.dataset.productCode));
        });
    }

    function bindAll(root) {
        const scope = root || document;

        paintAll(scope);

        scope.querySelectorAll(BUTTON_SELECTOR).forEach(function (button) {
            button.addEventListener('click', function () {
                toggle(button.dataset.brand, button.dataset.productCode);
                paintAll();
            });
        });
    }

    // iOS 15.4 以前不認得 :focus-visible，matches 會直接丟例外
    function isKeyboardFocused(element) {
        try {
            return element.matches(':focus-visible');
        } catch (e) {
            return false;
        }
    }

    // 重畫後焦點的去處，依序找第一個看得到的
    const REFOCUS_ORDER = [
        '[data-favorite-remove]',
        '#favorites-offer-filter',
        '#favorites-filter-show-all',
        '#favorites-retry',
        '[data-favorites-clear]',
    ];

    function focusFirstVisible(selectors) {
        for (let i = 0; i < selectors.length; i++) {
            const target = Array.prototype.find.call(document.querySelectorAll(selectors[i]), function (candidate) {
                return candidate.offsetParent !== null;
            });

            if (target) {
                target.focus();

                return;
            }
        }
    }

    function focusIsInList() {
        const list = document.getElementById('favorites-cards');

        return !!list && list.parentElement.contains(document.activeElement);
    }

    function rowsIn(container) {
        return container.querySelectorAll('[data-favorite-key]');
    }

    /**
     * 收藏頁每一列的移除鈕：直接移除，再給 8 秒復原，不先跳確認。連續移除會
     * 併成同一條提示、一次復原全部；復原保留原本的 addedAt，商品回到原位。
     * 復原把移除時拿下來的那一列塞回去，不重抓伺服器，反覆移除與復原才不會
     * 撞到限流。
     */
    function bindRemoveButtons(container, onChange) {
        let pending = null; // { key: { snapshot, row } }

        function restore(entries, viaKeyboard) {
            const favorites = read();

            Object.keys(entries).forEach(function (key) {
                favorites[key] = entries[key].snapshot;
            });

            if (!write(favorites)) {
                showState('favorites-error', '復原失敗，收藏沒有存回去');

                if (viaKeyboard) {
                    focusFirstVisible(REFOCUS_ORDER);
                }

                return;
            }

            showState(null);

            const order = sortedKeys(favorites);
            const restoredRows = [];

            order.forEach(function (key, position) {
                const entry = entries[key];

                // 別的分頁觸發過整份重畫時，這筆已經有新的一列，舊的不能再塞回去
                if (!entry || !entry.row || container.querySelector('[data-favorite-key="' + key + '"]')) {
                    return;
                }

                const insertBefore = order.slice(position + 1).reduce(function (found, laterKey) {
                    return found || container.querySelector('[data-favorite-key="' + laterKey + '"]');
                }, null);

                container.insertBefore(entry.row, insertBefore);
                restoredRows.push(entry.row);
            });

            onChange(rowsIn(container).length);

            // 「只看優惠中」開著時，復原的那件可能被藏起來，焦點不能給看不到的按鈕
            const visibleRow = restoredRows.find(function (row) {
                return !row.hidden;
            });

            if (visibleRow) {
                visibleRow.querySelector('[data-favorite-remove]').focus();
            } else if (viaKeyboard) {
                focusFirstVisible(['#favorites-offer-filter'].concat(REFOCUS_ORDER));
            }
        }

        function nextVisibleRemoveButton(row) {
            for (let next = row.nextElementSibling; next; next = next.nextElementSibling) {
                const button = next.hidden ? null : next.querySelector('[data-favorite-remove]');

                if (button) {
                    return button;
                }
            }

            return null;
        }

        container.querySelectorAll('[data-favorite-remove]').forEach(function (control) {
            control.addEventListener('click', function () {
                const brand = control.dataset.brand;
                const code = control.dataset.productCode;
                const key = keyOf(brand, code);
                const snapshot = read()[key];

                if (!snapshot || !remove(brand, code)) {
                    return;
                }

                const row = control.closest('[data-favorite-key]');
                const usingKeyboard = isKeyboardFocused(control);
                const nextRemove = nextVisibleRemoveButton(row);

                row.remove();
                onChange(rowsIn(container).length);

                pending = pending || {};
                pending[key] = { snapshot: snapshot, row: row };

                const batch = pending;
                const count = Object.keys(batch).length;

                Snackbar.show(count === 1 ? '已移除收藏' : ('已移除 ' + count + ' 件收藏'), function (viaKeyboard) {
                    pending = null;
                    restore(batch, viaKeyboard);
                }, function () {
                    pending = null;
                });

                // 按鈕跟著整列消失，焦點會掉回頁首。焦點停在提示條裡會暫停倒數，
                // 所以只在鍵盤操作時才移進去
                if (nextRemove) {
                    nextRemove.focus();
                } else if (usingKeyboard) {
                    Snackbar.focusUndo();
                }
            });
        });
    }

    /**
     * 清空收藏：先確認，確認後一樣給 8 秒復原。工具列、都已下架、載入失敗三個
     * 入口共用同一顆對話視窗。
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

        // Tocas 的對話視窗只是加上 open 屬性，不是 showModal()，背景仍可聚焦，
        // Tab 要自己困在視窗裡
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

            const focusables = Array.prototype.filter.call(
                dialog.querySelectorAll('button, [href], [tabindex]'),
                function (candidate) {
                    return candidate.offsetParent !== null;
                },
            );

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

        // 關閉鈕維持 Tocas 原生的 <i class="close icon">（排版依賴它），自己補鍵盤操作
        if (closeIcon) {
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
            // 確認鈕是 negative 外觀，Tocas 預設把 .negative 當拒絕，所以明確指定
            approve: '.js-favorites-clear-confirm',
            deny: '.js-favorites-clear-cancel',
            onDeny: function () {
                restoreFocus();

                return true;
            },
            onApprove: function () {
                const viaKeyboard = isKeyboardFocused(document.activeElement);
                const snapshot = read();

                if (!write({})) {
                    showState('favorites-error', '清空失敗，請再試一次');
                    restoreFocus();

                    return true;
                }

                invalidatePendingRender();

                const container = document.getElementById('favorites-cards');

                container.innerHTML = '';
                syncToolbar();
                applyOfferFilter(container);
                showState('favorites-empty', DEFAULT_SUMMARY);

                Snackbar.show('已清空收藏', function (undoViaKeyboard) {
                    const favorites = read();

                    Object.keys(snapshot).forEach(function (key) {
                        favorites[key] = snapshot[key];
                    });

                    if (!write(favorites)) {
                        showState('favorites-error', '復原失敗，收藏沒有存回去');

                        if (undoViaKeyboard) {
                            focusFirstVisible(REFOCUS_ORDER);
                        }

                        return;
                    }

                    renderPage(currentOptions, undoViaKeyboard);
                }, null, true);

                // 三個清空入口清空後都藏起來了，鍵盤使用者的焦點只能交給「復原」；
                // 滑鼠操作不移，焦點停在提示條裡會暫停倒數
                if (viaKeyboard) {
                    Snackbar.focusUndo();
                }

                return true;
            },
        });

        document.querySelectorAll('[data-favorites-clear]').forEach(function (button) {
            button.addEventListener('click', function () {
                lastTrigger = button;
                // 數的是瀏覽器裡存的筆數，包含已下架、畫面上沒有列的那些
                countEl.textContent = items().length;
                ts(dialog).modal('show');
                cancelBtn.focus();
            });
        });
    }

    // master 版型的 lazy-load 只處理頁面載入當下就在的圖片
    function revealLazyImages(container) {
        container.querySelectorAll('img[data-src]').forEach(function (img) {
            img.src = img.dataset.src;
        });
    }

    // 看的是存了幾筆、不是畫面上幾列：已下架的商品沒有列，但還在收藏裡、還清得掉
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

    function throttledMessage(retryAfterHeader) {
        const seconds = parseInt(retryAfterHeader, 10);

        return seconds > 0
            ? '操作太頻繁，請等 ' + seconds + ' 秒後再試'
            : '操作太頻繁，請稍後再試一次';
    }

    // 件數跟清空確認視窗一致，是存了幾筆；換不到卡片的（已下架）另外註明，
    // 不然使用者會以為收藏憑空少了
    function summaryFor(totalStored, notFoundCount) {
        if (notFoundCount > 0) {
            return '共 ' + totalStored + ' 件，其中 ' + notFoundCount + ' 件已經找不到，只存在這個瀏覽器';
        }

        return '共 ' + totalStored + ' 件，只存在這個瀏覽器';
    }

    /**
     * 「只看優惠中」：純前端篩選、不記住狀態。是否優惠中由伺服器算好寫在
     * data-on-offer。每次卡片變動後都要重算。
     */
    function applyOfferFilter(container) {
        const checkbox = document.getElementById('favorites-offer-filter');
        const on = !!checkbox && checkbox.checked;
        const rows = rowsIn(container);
        let matched = 0;

        rows.forEach(function (row) {
            const onOffer = row.dataset.onOffer === '1';

            if (onOffer) {
                matched += 1;
            }

            row.hidden = on && !onOffer;
        });

        const control = document.getElementById('favorites-filter-control');
        const summaryEl = document.getElementById('favorites-filter-summary');
        const filteredEmpty = document.getElementById('favorites-filter-empty');
        // 有收藏、只是篩選後沒有結果，要用專屬的空狀態，不是「還沒有收藏」
        const noMatches = rows.length > 0 && on && matched === 0;

        if (control) {
            control.hidden = rows.length === 0;
        }

        if (summaryEl) {
            summaryEl.textContent = on
                ? ('顯示 ' + matched + '／共 ' + rows.length + ' 件')
                : ('優惠中 ' + matched);
        }

        if (filteredEmpty) {
            filteredEmpty.hidden = !noMatches;
        }

        container.hidden = noMatches;
    }

    function bindOfferFilter() {
        const checkbox = document.getElementById('favorites-offer-filter');
        const showAllButton = document.getElementById('favorites-filter-show-all');
        const container = document.getElementById('favorites-cards');

        if (!checkbox) {
            return;
        }

        checkbox.addEventListener('change', function () {
            applyOfferFilter(container);
        });

        if (showAllButton) {
            showAllButton.addEventListener('click', function () {
                checkbox.checked = false;
                applyOfferFilter(container);
                checkbox.focus();
            });
        }
    }

    /**
     * 逾時每一批各自計算，前面幾批慢不連坐後面；讀回應本體也算在內。外面傳進來
     * 的 signal 代表這一輪已被取代，跟逾時共用同一顆 controller。
     */
    async function requestCardsBatch(cardsUrl, batchItems, signal) {
        const controller = new AbortController();
        const abort = function () {
            controller.abort();
        };
        const timeoutId = setTimeout(abort, FETCH_TIMEOUT_MS);

        signal.addEventListener('abort', abort);

        if (signal.aborted) {
            abort();
        }

        try {
            const response = await fetch(cardsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ items: batchItems }),
                signal: controller.signal,
            });

            if (!response.ok) {
                const error = new Error('HTTP ' + response.status);

                error.status = response.status;
                error.retryAfter = response.headers.get('Retry-After');

                throw error;
            }

            return await response.text();
        } finally {
            clearTimeout(timeoutId);
            signal.removeEventListener('abort', abort);
        }
    }

    // 任一批失敗就整份失敗：只列出一半的清單比讀不到更難懂
    async function fetchCards(options, wanted, signal) {
        let html = '';

        for (let start = 0; start < wanted.length; start += BATCH_SIZE) {
            html += await requestCardsBatch(options.cardsUrl, wanted.slice(start, start + BATCH_SIZE), signal);
        }

        return html;
    }

    function invalidatePendingRender() {
        renderToken += 1;

        if (currentController) {
            currentController.abort();
            currentController = null;
        }

        document.getElementById('favorites-loading').hidden = true;
    }

    /**
     * refocus：整份重畫會把焦點所在的列或按鈕一起換掉（重新載入、清空後復原、
     * 別的分頁改了收藏），焦點原本在清單裡的話要放回看得到的第一個控制項。
     */
    async function renderPage(options, refocus) {
        const pending = renderList(options);
        const token = renderToken;

        await pending;

        if (refocus && token === renderToken) {
            focusFirstVisible(REFOCUS_ORDER);
        }
    }

    async function renderList(options) {
        currentOptions = options;
        invalidatePendingRender();

        const token = renderToken;
        const controller = new AbortController();

        currentController = controller;

        const container = document.getElementById('favorites-cards');
        const loading = document.getElementById('favorites-loading');
        const summary = document.getElementById('favorites-summary');
        const wanted = items();

        container.innerHTML = '';
        showState(null);
        applyOfferFilter(container);
        syncToolbar();

        document.getElementById('favorites-retry').onclick = function (event) {
            renderPage(options, isKeyboardFocused(event.currentTarget));
        };

        if (wanted.length === 0) {
            showState('favorites-empty', DEFAULT_SUMMARY);

            return;
        }

        loading.hidden = false;

        let html;

        try {
            html = await fetchCards(options, wanted, controller.signal);
        } catch (e) {
            if (token === renderToken) {
                loading.hidden = true;
                showState('favorites-error', e.status === 429 ? throttledMessage(e.retryAfter) : '收藏清單還在你的瀏覽器裡');
            }

            return;
        }

        if (token !== renderToken) {
            return;
        }

        loading.hidden = true;
        container.innerHTML = html;
        revealLazyImages(container);
        bindAll(container);
        applyOfferFilter(container);

        const rendered = rowsIn(container).length;

        // 有收藏卻一張卡都換不到，代表全部下架了
        if (rendered === 0) {
            showState('favorites-gone');

            return;
        }

        summary.textContent = summaryFor(wanted.length, wanted.length - rendered);

        bindRemoveButtons(container, function (remaining) {
            const totalStored = items().length;

            syncToolbar();
            applyOfferFilter(container);

            if (remaining > 0) {
                summary.textContent = summaryFor(totalStored, totalStored - remaining);
            } else if (totalStored === 0) {
                showState('favorites-empty', DEFAULT_SUMMARY);
            } else {
                // 畫面上沒有列了，但收藏裡還有換不到卡片的已下架商品
                showState('favorites-gone');
            }
        });
    }

    /**
     * 別的分頁改了收藏（同一個分頁自己寫不會觸發 storage 事件）。收藏頁整份
     * 重畫；收藏頁在背景時先記著，回到前景才重抓，不然使用者在別的分頁每按
     * 一次愛心就整份重抓一次，很快撞到限流。
     */
    function bindStorageSync() {
        window.addEventListener('storage', function (event) {
            // key 是 null 代表整個 localStorage 被 clear()
            if (event.key !== null && event.key !== STORAGE_KEY) {
                return;
            }

            if (!currentOptions) {
                paintAll();
            } else if (document.hidden) {
                staleWhileHidden = true;
            } else {
                renderPage(currentOptions, focusIsInList());
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && staleWhileHidden) {
                staleWhileHidden = false;
                renderPage(currentOptions, focusIsInList());
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindAll();
        bindClearAll();
        bindOfferFilter();
        bindStorageSync();
    });

    return { renderPage: renderPage };
})();
