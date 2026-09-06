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
 */
(function () {
    const instantFilterInputs = document.querySelectorAll('[data-instant-filter]');

    function normalize(value) {
        return value.trim().toLowerCase();
    }

    /**
     * 依關鍵字顯示或隱藏卡片，並且在某個性別段的卡片全部被藏起來時，
     * 把那一段的標題跟章節選單的項目也一起藏起來。只有清單頁的輸入框
     * 會掛 data-instant-filter，分類頁不會走到這裡。
     */
    function filterCards(keyword) {
        const cards = document.querySelectorAll('[data-card-name]');

        cards.forEach(function (card) {
            const name = (card.dataset.cardName || '').toLowerCase();
            card.hidden = keyword !== '' && name.indexOf(keyword) === -1;
        });

        document.querySelectorAll('[data-gender-heading]').forEach(function (heading) {
            const gender = heading.dataset.genderHeading;
            const body = document.querySelector('[data-gender-body="' + gender + '"]');

            // 這段本來就沒有商品（清單頁一律列出四段，沒貨的那段寫「沒有商品」），
            // 不受搜尋關鍵字影響，維持顯示
            if (!body || !body.querySelector('[data-card-name]')) {
                return;
            }

            const hasVisibleCard = body.querySelector('[data-card-name]:not([hidden])') !== null;

            heading.hidden = !hasVisibleCard;
            body.hidden = !hasVisibleCard;

            const menuItem = document.querySelector('#gender_menu a[href="#' + gender + '"]');

            if (menuItem) {
                menuItem.hidden = !hasVisibleCard;
            }
        });
    }

    instantFilterInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            filterCards(normalize(input.value));
        });
    });
})();
