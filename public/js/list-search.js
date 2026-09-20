/**
 * 清單頁的「在這個清單裡找」：邊打邊篩畫面上已經渲染好的卡片，不打 API。
 *
 * 啟用靠輸入框身上的 [data-instant-filter]，這支腳本不判斷「現在是哪一
 * 頁」。只掛在一次載入全部卡片的頁面（清單頁）——伺服器端的 q 參數走
 * Collection 篩，跟這裡篩的是同一份完整資料，先看到的結果不會跟按 Enter
 * 之後拿到的不一致。
 *
 * 分類頁有分頁，畫面上永遠只有這一頁載入到的商品，即時篩只能篩到這一頁
 * 會誤導使用者以為篩了整個分類，所以分類頁的輸入框不掛
 * [data-instant-filter]，單純是一個 GET 表單、按 Enter 交給後端對全分類
 * 下 SQL LIKE（站主試用後的裁決；曾經試過停止輸入後自動送出表單，但整頁
 * 自動 submit 會打斷中文輸入法組字與游標焦點，改回單純 Enter 送出）。
 *
 * 按 Enter 或沒有 JavaScript 時都一樣：表單照常用 GET 送出。
 *
 * 比對邏輯要跟後端 ListService::filterHmallProductsByKeyword() /
 * hmallProductMatchesKeyword() 一致，不然使用者在按 Enter 前後會看到不一樣
 * 的結果（PR #76 審查留言 4057184154）：
 * - 多個空白分開的詞要「每個詞都命中」，不是整串當一個子字串比對。
 * - 比對的欄位要含完整料號（product_code）跟卡片上顯示的短編號
 *   （short_product_code），不能只比品名加 code——不然卡片上唯一看得到的
 *   編號反而搜不到自己（審查留言 4057184155）。
 * - 卡片藏起來之後，件數（頁首副標、各性別段「共 N 件」、章節選單數字）
 *   要跟著重算，全部藏起來時要換成跟後端一樣的空狀態，不能維持篩選前的
 *   舊數字或維持四段都在的版面。
 */
(function () {
    const instantFilterInputs = document.querySelectorAll('[data-instant-filter]');

    /**
     * 把輸入切成詞：先去頭尾空白、轉小寫，再用空白分詞。跟後端
     * preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) 同一個切法。
     */
    function tokensOf(value) {
        return value
            .trim()
            .toLowerCase()
            .split(/\s+/)
            .filter(function (token) {
                return token !== '';
            });
    }

    /**
     * 這張卡是不是每個詞都命中。haystack 是品名、code、完整料號、卡片上的
     * 短編號用空白接起來的字串（見 card-base.blade.php 的 data-card-search），
     * 詞本身不含空白，「詞出現在接起來的字串裡」等於「詞出現在其中一個欄位
     * 裡」，跟後端逐欄位比對是同一件事。
     */
    function cardMatchesTokens(card, tokens) {
        if (tokens.length === 0) {
            return true;
        }

        const haystack = (card.dataset.cardSearch || '').toLowerCase();

        return tokens.every(function (token) {
            return haystack.indexOf(token) !== -1;
        });
    }

    function updateSubtitle(totalVisible, trimmedQuery) {
        const subtitle = document.querySelector('[data-instant-subtitle]');

        if (!subtitle) {
            return;
        }

        const sortText = subtitle.dataset.sortText || '';

        subtitle.textContent = trimmedQuery !== ''
            ? (totalVisible + ' 件符合「' + trimmedQuery + '」，' + sortText)
            : (totalVisible + ' 件，' + sortText);
    }

    function updateEmptyState(allHidden, trimmedQuery) {
        const emptyState = document.getElementById('list-instant-empty-state');

        if (!emptyState) {
            return;
        }

        emptyState.hidden = !allHidden;

        const titleEl = document.getElementById('list-instant-empty-title');

        if (titleEl) {
            titleEl.textContent = trimmedQuery !== ''
                ? ('沒有符合「' + trimmedQuery + '」的商品')
                : '沒有符合的商品';
        }
    }

    /**
     * 依關鍵字顯示或隱藏卡片，並且在某個性別段的卡片全部被藏起來時，
     * 把那一段的標題跟章節選單的項目也一起藏起來。只有清單頁的輸入框
     * 會掛 data-instant-filter，分類頁不會走到這裡。
     *
     * allHidden（篩完一張卡片都不剩）時整頁換成跟後端 $count === 0 一樣的
     * 空狀態：連「這段本來就沒有商品」那幾段固定顯示的「沒有商品」也要一起
     * 藏起來——那是「四段都在、只是某段沒貨」的版面，不是「搜尋沒有結果」
     * 的版面，兩種空狀態的意思不一樣，不能混著顯示。
     */
    function filterCards(rawQuery) {
        const tokens = tokensOf(rawQuery);
        const cards = document.querySelectorAll('[data-card-name]');
        let totalVisible = 0;

        cards.forEach(function (card) {
            const visible = cardMatchesTokens(card, tokens);

            card.hidden = !visible;

            if (visible) {
                totalVisible += 1;
            }
        });

        const allHidden = cards.length > 0 && totalVisible === 0;

        document.querySelectorAll('[data-gender-heading]').forEach(function (heading) {
            const gender = heading.dataset.genderHeading;
            const body = document.querySelector('[data-gender-body="' + gender + '"]');
            const menuItem = document.querySelector('#gender_menu a[href="#' + gender + '"]');

            if (allHidden) {
                heading.hidden = true;

                if (body) {
                    body.hidden = true;
                }

                if (menuItem) {
                    menuItem.hidden = true;
                }

                return;
            }

            // 這段本來就沒有商品（清單頁一律列出四段，沒貨的那段寫「沒有商品」），
            // 不受搜尋關鍵字影響，維持顯示——但如果上一輪因為 allHidden 把它藏過，
            // 這裡要恢復顯示。
            if (!body || !body.querySelector('[data-card-name]')) {
                heading.hidden = false;

                if (menuItem) {
                    menuItem.hidden = false;
                }

                return;
            }

            const visibleCount = body.querySelectorAll('[data-card-name]:not([hidden])').length;
            const hasVisibleCard = visibleCount > 0;

            heading.hidden = !hasVisibleCard;
            body.hidden = !hasVisibleCard;

            const countEl = heading.querySelector('[data-gender-count]');

            if (countEl) {
                countEl.textContent = visibleCount;
            }

            if (menuItem) {
                menuItem.hidden = !hasVisibleCard;

                const badge = menuItem.querySelector('.label');

                if (badge) {
                    badge.textContent = visibleCount;
                }
            }
        });

        const menu = document.querySelector('.uq-section-menu');

        if (menu) {
            menu.hidden = allHidden;
        }

        const trimmedQuery = rawQuery.trim();

        updateSubtitle(totalVisible, trimmedQuery);
        updateEmptyState(allHidden, trimmedQuery);
    }

    instantFilterInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            filterCards(input.value);
        });
    });
})();
