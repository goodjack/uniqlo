/**
 * 清單頁的「在這個清單裡找」：邊打邊篩已經渲染好的卡片，不打 API。按 Enter
 * 或沒有 JavaScript 時照常用 GET 送出，由後端篩。
 *
 * 只掛在一次載入全部卡片的清單頁；分類頁有分頁，畫面上只有一頁的商品，即時篩
 * 會讓人以為篩了整個分類，所以那邊的輸入框不掛 [data-instant-filter]。
 *
 * 比對規則要跟後端 ListService::hmallProductMatchesKeyword() 一致（每個詞都要
 * 命中；比品名、code、完整料號、短編號），不然按 Enter 前後結果會不一樣。
 */
(function () {
    const instantFilterInputs = document.querySelectorAll('[data-instant-filter]');

    function tokensOf(value) {
        return value
            .trim()
            .toLowerCase()
            .split(/\s+/)
            .filter(function (token) {
                return token !== '';
            });
    }

    // data-card-search 是各欄位用空白接起來的字串，詞本身不含空白，所以
    // 「詞出現在整串裡」等於「詞出現在某一欄裡」
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
     * 篩完一張都不剩時，整頁換成跟後端沒有結果時一樣的空狀態，連本來就寫著
     * 「沒有商品」的性別段也藏起來：「某段沒貨」跟「搜尋沒有結果」是兩種版面。
     */
    function filterCards(rawQuery) {
        const tokens = tokensOf(rawQuery);
        const cards = document.querySelectorAll('[data-card-name]');

        // 男女適穿的商品在男裝與女裝段各有一張卡，件數要跟伺服器一樣算不重複的
        // 商品。data-card-search 含完整料號，可以當作商品的識別
        const visibleProducts = new Set();

        cards.forEach(function (card) {
            const visible = cardMatchesTokens(card, tokens);

            card.hidden = !visible;

            if (visible) {
                visibleProducts.add(card.dataset.cardSearch);
            }
        });

        const totalVisible = visibleProducts.size;
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

            // 本來就沒有商品的那段不受關鍵字影響，上一輪全藏過的話要放回來
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

    function withQuery(href, query) {
        const url = new URL(href, location.href);

        if (query === '') {
            url.searchParams.delete('q');
        } else {
            url.searchParams.set('q', query);
        }

        return url.href;
    }

    /**
     * 即時篩的字也寫進網址與頁面上會換頁的連結、表單（[data-keeps-q]），
     * 換排序、品牌或標籤時才不會把打好的字丟掉，重新整理也還在。
     */
    function keepQuery(rawQuery) {
        const query = rawQuery.trim();

        history.replaceState(history.state, '', withQuery(location.href, query));

        document.querySelectorAll('[data-keeps-q]').forEach(function (container) {
            container.querySelectorAll('a[href]').forEach(function (link) {
                link.href = withQuery(link.href, query);
            });

            if (container.tagName !== 'FORM') {
                return;
            }

            let hidden = container.querySelector('input[type="hidden"][name="q"]');

            if (query === '') {
                if (hidden) {
                    hidden.remove();
                }

                return;
            }

            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'q';
                container.appendChild(hidden);
            }

            hidden.value = query;
        });
    }

    instantFilterInputs.forEach(function (input) {
        function apply() {
            filterCards(input.value);
            keepQuery(input.value);
        }

        // 注音組字途中不篩，不然「ㄨㄞ」這種半成品會讓整頁閃成「沒有符合」。
        // Chrome 組字中的 input 帶 isComposing，Safari 則是 compositionend 之後
        // 才送 input，兩邊都由 compositionend 補一次
        input.addEventListener('input', function (event) {
            if (!event.isComposing) {
                apply();
            }
        });
        input.addEventListener('compositionend', apply);
    });
})();
