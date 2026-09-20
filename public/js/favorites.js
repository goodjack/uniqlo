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
     * 每次 renderPage 遞增一次，讓「這次渲染是不是已經過期」有東西可以比對。
     * 清空收藏、換一次新的 renderPage 都會讓舊的那輪變成過期——回應晚到時
     * 舊那輪要安靜地不動畫面，不能把資料已經清空的畫面蓋回去。
     *
     * currentController 是跟目前這個 token 綁在一起的 fetch 控制器：新的一輪
     * 開始、或清空收藏時，都要先 abort 掉上一輪還沒回來的請求，讓它們不要
     * 繼續佔連線、也不要在 abort 之後又跑出一次不必要的錯誤畫面。
     */
    let renderToken = 0;
    let currentController = null;

    /** 單一批次的逾時，不是整個 renderPage 的逾時——分批送的清單不該因為
     * 前面幾批比較慢就連坐失敗。 */
    const FETCH_TIMEOUT_MS = 15000;

    /**
     * 隱私模式或使用者關掉網站資料時，localStorage 的存取本身就會丟例外，
     * 所以每一次讀寫都要能安靜地退回「沒有收藏」的狀態，不能讓整頁掛掉。
     */
    /**
     * 只有 JSON.parse 失敗會被擋，解出來的形狀完全沒被檢查——使用者自己改過、
     * 瀏覽器擴充套件寫壞、或以後換格式沒處理到，都可能讓某一筆變成 null 或
     * 整包變成字串。每一筆都驗過形狀，壞的丟掉並寫回，其餘正常使用，不讓一筆
     * 壞資料卡住整頁。
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

            if (item && typeof item === 'object' && typeof item.brand === 'string' && typeof item.code === 'string') {
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
        let pending = null; // { key: { snapshot, row } }，只在還沒過期／還沒復原前存在

        function undoLabel(count) {
            return count === 1 ? '已移除收藏' : ('已移除 ' + count + ' 件收藏');
        }

        /**
         * 復原不重新整份重抓——收藏很多的使用者反覆「移除→復原」時，每次
         * 都整份重抓會在一分鐘內就把 throttle:60,1 的額度用完（PR 審查留言
         * 4057184148）。移除的當下那個列元素只是從畫面上 detach，資料跟
         * 綁定的事件都還在，復原就是照 sortedKeys 的順序把它們塞回去，
         * 完全不用打伺服器。
         */
        function restore(entries) {
            const favorites = read();

            Object.keys(entries).forEach(function (key) {
                favorites[key] = entries[key].snapshot;
            });

            // 跟清空那條一樣：寫不進去就不要假裝復原成功。
            if (!write(favorites)) {
                showState('favorites-error', '復原失敗，收藏沒有存回去');

                return;
            }

            // 現在確定會有東西要顯示了，先把「都已下架／還沒有收藏」這類
            // 空狀態收起來，等下面的 onChange 用真正的筆數重新判斷一次。
            showState(null);

            const order = sortedKeys(favorites);

            order.forEach(function (key) {
                const entry = entries[key];

                if (!entry || !entry.row) {
                    return;
                }

                // 這筆在畫面上已經有列了（例如另一個分頁的異動觸發了
                // storage 事件、整份重新渲染過），不要把 detach 的舊節點
                // 又塞進去，會變成同一筆兩列。
                if (container.querySelector('[data-favorite-key="' + key + '"]')) {
                    return;
                }

                let insertBefore = null;

                for (let i = order.indexOf(key) + 1; i < order.length; i += 1) {
                    const candidate = container.querySelector('[data-favorite-key="' + order[i] + '"]');

                    if (candidate) {
                        insertBefore = candidate;
                        break;
                    }
                }

                if (insertBefore) {
                    container.insertBefore(entry.row, insertBefore);
                } else {
                    container.appendChild(entry.row);
                }
            });

            onChange(container.querySelectorAll('[data-favorite-key]').length);
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

                pending[key] = { snapshot: snapshot, row: row };

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

                // 清空成功了，不管畫面上是不是還在等一輪 renderPage 的回應，
                // 都要讓那一輪過期——回應晚到時才不會把清空後的畫面蓋回去。
                invalidatePendingRender();

                const container = document.getElementById('favorites-cards');

                container.innerHTML = '';
                syncToolbar();
                // 清空後重算一次：篩選鈕與「找不到符合的商品」空狀態都要
                // 跟著收起來，不然開著篩選時清空會同時看到兩種空狀態
                applyOfferFilter(container);
                showState('favorites-empty', DEFAULT_SUMMARY);

                Snackbar.show('已清空收藏', function () {
                    const favorites = read();

                    Object.keys(snapshot).forEach(function (key) {
                        favorites[key] = snapshot[key];
                    });

                    // 跟清空那條一樣：寫不進去（storage 剛好滿了）就不要假裝復原
                    // 成功——不重新載入，直接說清楚，不然畫面會安靜地維持在
                    // 「還沒有收藏任何商品」，使用者以為那是正常的空清單。
                    if (!write(favorites)) {
                        showState('favorites-error', '復原失敗，收藏沒有存回去');

                        return;
                    }

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

    /**
     * 429 逾時倒數用的計時器。放在模組層是因為它要在下一次 showState 呼叫、
     * 或清空收藏時被清掉——不然畫面已經換到別的狀態，倒數卻還在背景改
     * #favorites-summary 的文字，把使用者不相關的畫面蓋回去。
     */
    let retryCountdownTimer = null;

    function clearRetryCountdown() {
        if (retryCountdownTimer !== null) {
            clearInterval(retryCountdownTimer);
            retryCountdownTimer = null;
        }

        const retryButton = document.getElementById('favorites-retry');

        if (retryButton) {
            retryButton.disabled = false;
        }
    }

    function showState(name, summaryText) {
        // 任何狀態切換都代表倒數已經不適用了——不管是使用者自己重新載入，
        // 還是清空收藏蓋掉了正在等待的錯誤畫面。
        clearRetryCountdown();

        ['favorites-empty', 'favorites-gone', 'favorites-error'].forEach(function (id) {
            document.getElementById(id).hidden = id !== name;
        });

        if (summaryText !== undefined) {
            document.getElementById('favorites-summary').textContent = summaryText;
        }
    }

    /**
     * 429 時後端會回 Retry-After（秒數），比起固定的「請稍後再試」，讓使用者
     * 知道具體要等多久、按「重新載入」前先擋住，不然使用者只會一直重試、
     * 在同一個限流視窗裡永遠救不回來。
     */
    function showRetryAfter(retryAfterHeader) {
        const seconds = parseInt(retryAfterHeader, 10);

        if (!Number.isFinite(seconds) || seconds <= 0) {
            showState('favorites-error', '操作太頻繁，請稍後再試一次');

            return;
        }

        showState('favorites-error', '操作太頻繁，請等 ' + seconds + ' 秒後再試');

        const summary = document.getElementById('favorites-summary');
        const retryButton = document.getElementById('favorites-retry');
        let remaining = seconds;

        if (retryButton) {
            retryButton.disabled = true;
        }

        retryCountdownTimer = setInterval(function () {
            remaining -= 1;

            if (remaining <= 0) {
                clearRetryCountdown();
                summary.textContent = '可以再試一次了，請按重新載入';

                return;
            }

            summary.textContent = '操作太頻繁，請等 ' + remaining + ' 秒後再試';
        }, 1000);
    }

    /**
     * 頁首件數要跟清空收藏確認視窗數的是同一批（瀏覽器裡真正存起來的筆數），
     * 不是畫面上列出來的卡片數——商品下架時，那筆還在收藏裡、只是換不到卡片，
     * 兩個數字不一樣使用者會以為收藏憑空消失了，所以要附註找不到幾件。
     */
    function summaryFor(totalStored, notFoundCount) {
        if (notFoundCount > 0) {
            return '共 ' + totalStored + ' 件，其中 ' + notFoundCount + ' 件已經找不到，只存在這個瀏覽器';
        }

        return '共 ' + totalStored + ' 件，只存在這個瀏覽器';
    }

    /**
     * 「只看優惠中」篩選：純前端即時篩選，不記住狀態（勾選框本身就是 DOM
     * 狀態，重新整理頁面自然回到預設關閉，不用額外程式碼清掉）。
     *
     * data-on-offer 是後端算好寫進每一列的明確狀態（見
     * favorites/cards.blade.php、HmallProductPresenter::isOnOffer()），這裡
     * 只讀這個屬性，不解析文字或顏色。
     */
    function isOfferFilterOn() {
        const checkbox = document.getElementById('favorites-offer-filter');

        return !!checkbox && checkbox.checked;
    }

    /**
     * 每次卡片重新渲染（載入、移除、復原、清空後復原）都要重跑一次：篩選
     * 開關本身活在工具列裡不會被清掉，但卡片是新的，符合資格的筆數、要不要
     * 顯示「找不到符合的商品」都要重算。
     */
    function applyOfferFilter(container) {
        const on = isOfferFilterOn();
        const rows = Array.prototype.slice.call(container.querySelectorAll('[data-favorite-key]'));
        const total = rows.length;
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
        // 有卡片、篩選開著、但一件都不符合：要顯示專用的空結果，不是
        // 「還沒有收藏任何商品」——使用者其實有收藏，只是這次篩選沒有結果
        const noMatches = total > 0 && on && matched === 0;

        if (control) {
            control.hidden = total === 0;
        }

        if (summaryEl) {
            summaryEl.textContent = on
                ? ('顯示 ' + matched + '／共 ' + total + ' 件')
                : ('優惠中 ' + matched);
        }

        if (filteredEmpty) {
            filteredEmpty.hidden = !noMatches;
        }

        container.hidden = noMatches;
    }

    /**
     * 篩選勾選框跟「顯示全部」只需要在 DOMContentLoaded 綁一次：勾選框本身
     * 住在工具列，不在 #favorites-cards 裡面，renderPage 重新渲染卡片時
     * 不會把它們洗掉。
     */
    function bindOfferFilter() {
        const checkbox = document.getElementById('favorites-offer-filter');
        const showAllButton = document.getElementById('favorites-filter-show-all');
        const container = document.getElementById('favorites-cards');

        if (checkbox) {
            checkbox.addEventListener('change', function () {
                applyOfferFilter(container);
            });
        }

        if (showAllButton) {
            showAllButton.addEventListener('click', function () {
                if (checkbox) {
                    checkbox.checked = false;
                }

                applyOfferFilter(container);

                if (checkbox) {
                    checkbox.focus();
                }
            });
        }
    }

    /**
     * 單一批次的請求。逾時跟「這一輪被更新的一輪取消」共用同一顆
     * AbortController：外面傳進來的 signal abort 時，這裡的逾時計時器也要
     * 跟著清掉（避免兩顆各自 abort、造成重複的錯誤處理）；反過來，逾時
     * 也直接 abort 掉這次請求，效果跟被取消一樣，呼叫端不用分開處理。
     */
    async function requestCardsBatch(cardsUrl, headers, batchItems, signal) {
        if (signal.aborted) {
            throw new DOMException('Aborted', 'AbortError');
        }

        const timeoutController = new AbortController();
        const onOuterAbort = function () {
            timeoutController.abort();
        };
        const timeoutId = setTimeout(onOuterAbort, FETCH_TIMEOUT_MS);

        signal.addEventListener('abort', onOuterAbort);

        try {
            return await fetch(cardsUrl, {
                method: 'POST',
                headers: Object.assign(
                    {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    headers,
                ),
                body: JSON.stringify({ items: batchItems }),
                signal: timeoutController.signal,
            });
        } finally {
            clearTimeout(timeoutId);
            signal.removeEventListener('abort', onOuterAbort);
        }
    }

    /**
     * session 過期後，頁面渲染當下寫進 options.csrfToken 的那個 token 永遠
     * 是舊的，重新整理以外沒有別的辦法拿到新的。GET 自己這一頁不用帶 token
     * （VerifyCsrfToken 只驗會改資料的動詞），但 Laravel 的 CSRF 中介層會在
     * 回應裡重新核發一份 XSRF-TOKEN cookie；讀那顆 cookie（要 decodeURIComponent，
     * 瀏覽器存的是編碼過的字串）就能換到一個新鮮、跟目前 session 對得起來的
     * token。cookie 本身是加密過的，所以要送 X-XSRF-TOKEN 這個表頭，讓
     * Laravel 走它自己會解密的那條路徑——跟頁面渲染當下用的 X-CSRF-TOKEN
     * 不是同一個表頭。
     */
    async function refreshXsrfToken() {
        try {
            await fetch(location.href, { credentials: 'same-origin' });
        } catch (e) {
            return null;
        }

        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

        return match ? decodeURIComponent(match[1]) : null;
    }

    /**
     * 一批一批換卡片，任一批失敗就整個失敗——只渲染一半的清單比讀不到更難懂。
     *
     * 逾時是每一批各自 15 秒，不是整支函式共用一個計時器：分 3 批送的清單，
     * 前面幾批正常、只有某一批卡住時，不該連坐判定成整體逾時。
     */
    async function fetchCards(options, wanted, signal) {
        let html = '';
        let authHeaders = { 'X-CSRF-TOKEN': options.csrfToken };
        let csrfRetried = false;

        for (let start = 0; start < wanted.length; start += BATCH_SIZE) {
            const batch = wanted.slice(start, start + BATCH_SIZE);
            let response = await requestCardsBatch(options.cardsUrl, authHeaders, batch, signal);

            // 419 只重試一次：換一次新 token 就能解決的是「session 剛好在這
            // 之間過期」，換過還是 419 代表另有問題（例如 session 真的被登出），
            // 重試下去只會一直卡在這裡。
            if (response.status === 419 && !csrfRetried) {
                csrfRetried = true;

                const freshToken = await refreshXsrfToken();

                if (freshToken) {
                    authHeaders = { 'X-XSRF-TOKEN': freshToken };
                    response = await requestCardsBatch(options.cardsUrl, authHeaders, batch, signal);
                }
            }

            if (!response.ok) {
                const error = new Error('HTTP ' + response.status);

                error.status = response.status;
                error.retryAfter = response.headers.get('Retry-After');

                throw error;
            }

            html += await response.text();
        }

        return html;
    }

    /**
     * 清空收藏不是走 renderPage，是直接操作畫面（見 bindClearAll 的
     * onApprove），所以載入途中按清空時，要自己讓正在等待的那一輪 renderPage
     * 過期並 abort 掉它的請求——不然那些回應晚到時，還是會把已經清空的畫面
     * 蓋回 300 張卡片（PR 審查留言 4057184136 講的就是這個）。
     */
    function invalidatePendingRender() {
        renderToken += 1;

        if (currentController) {
            currentController.abort();
            currentController = null;
        }

        const loading = document.getElementById('favorites-loading');

        if (loading) {
            loading.hidden = true;
        }

        clearRetryCountdown();
    }

    async function renderPage(options) {
        currentOptions = options;

        // 新的一輪一律讓上一輪過期，並且真的把它的請求 abort 掉——不然清空
        // 收藏、或連按兩次重新載入時，晚到的回應還是會把畫面蓋回去（見
        // fetchCards 的逾時／取消共用同一顆 controller 的說明）。
        renderToken += 1;
        const token = renderToken;

        if (currentController) {
            currentController.abort();
        }

        const controller = new AbortController();

        currentController = controller;

        const container = document.getElementById('favorites-cards');
        const loading = document.getElementById('favorites-loading');
        const summary = document.getElementById('favorites-summary');
        const wanted = items();

        container.innerHTML = '';
        showState(null);
        // 重新載入前先歸零：0 張卡片時篩選鈕本來就該藏起來，等新內容渲染完
        // 再重算一次真正的筆數
        applyOfferFilter(container);

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
            html = await fetchCards(options, wanted, controller.signal);
        } catch (e) {
            // 這一輪已經被更新的一輪（或清空收藏）取代了，不管是逾時、取消
            // 還是真的失敗，都不該再動畫面——畫面現在該長什麼樣是新的那輪
            // 的事。
            if (token !== renderToken) {
                return;
            }

            if (e && e.status === 429) {
                showRetryAfter(e.retryAfter);
            } else if (e && e.status === 419) {
                // fetchCards 已經試過換一次新 token 重送，還是 419 才會到這裡
                // ——不是單純的「載入失敗」，按「重新載入」也沒用，要請使用者
                // 真的重新整理頁面。
                showState('favorites-error', '頁面已經過期，請重新整理頁面');
            } else {
                showState('favorites-error', '收藏清單還在你的瀏覽器裡');
            }

            return;
        } finally {
            if (token === renderToken) {
                loading.hidden = true;
            }
        }

        if (token !== renderToken) {
            return;
        }

        container.innerHTML = html;
        revealLazyImages(container);
        // 動態插進來的內容不在 DOMContentLoaded 那一輪裡，要自己綁一次
        bindAll(container);

        // 商品可能已經下架，回來的卡片會比收藏的少
        const rendered = container.querySelectorAll('[data-favorite-key]');

        // 篩選開關本身不受下架影響，一律重算：0 張卡片時它會自己藏起來
        applyOfferFilter(container);

        // 有收藏、但回來的卡片是空的，代表那些商品都下架了
        if (rendered.length === 0) {
            showState('favorites-gone');

            return;
        }

        bindRemoveButtons(container, options, function (remaining) {
            syncToolbar();
            applyOfferFilter(container);

            if (remaining > 0) {
                const totalStored = items().length;

                summary.textContent = summaryFor(totalStored, totalStored - remaining);

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

        summary.textContent = summaryFor(wanted.length, wanted.length - rendered.length);
    }

    /**
     * 綁定範圍內所有的收藏鈕。不給 root 就是整份文件。
     */
    function bindAll(root) {
        const scope = root || document;

        scope.querySelectorAll('[data-favorite-button]').forEach(bindButton);
        scope.querySelectorAll('[data-favorite-card]').forEach(bindCardButton);
    }

    /**
     * 把畫面上已經綁定的收藏鈕重新上色，跟 localStorage 現在的狀態對齊。
     * 用在跨分頁同步：另一個分頁改了收藏，這一頁的按鈕沒有機會自己重新
     * 讀一次 has()，畫面會停在舊狀態。
     */
    function resyncButtons(root) {
        const scope = root || document;

        scope.querySelectorAll('[data-favorite-button]').forEach(function (button) {
            paintButton(button, has(button.dataset.brand, button.dataset.productCode));
        });

        scope.querySelectorAll('[data-favorite-card]').forEach(function (button) {
            paintCardButton(button, has(button.dataset.brand, button.dataset.productCode));
        });
    }

    /**
     * 只在同一個瀏覽器的「別的」分頁改動 localStorage 時才會觸發——這個分頁
     * 自己寫不會觸發自己的 storage 事件，不用擔心跟 renderPage／toggle 打架。
     *
     * 在收藏頁（currentOptions 已經設過，代表 renderPage 至少跑過一次）：
     * 直接重新整理整份清單，最簡單也最不容易漏掉「這筆從別的分頁被移除了」
     * 這種要整理排序、summary、空狀態的情況。
     *
     * 在其他頁（商品頁、清單頁的卡片）：沒有清單可以重新整理，改成只重新
     * 上色目前畫面上已經綁定的按鈕。
     */
    function bindStorageSync() {
        window.addEventListener('storage', function (event) {
            // key 是 null 代表整個 localStorage 被 clear()，也要當成有變動處理。
            if (event.key !== null && event.key !== STORAGE_KEY) {
                return;
            }

            if (currentOptions) {
                renderPage(currentOptions);

                return;
            }

            resyncButtons();
        });
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
        bindOfferFilter: bindOfferFilter,
        bindStorageSync: bindStorageSync,
        renderPage: renderPage,
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    window.UqFavorites.bindAll();
    window.UqFavorites.bindClearAll();
    window.UqFavorites.bindOfferFilter();
    window.UqFavorites.bindStorageSync();
});
