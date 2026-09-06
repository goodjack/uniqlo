/**
 * 清單頁與分類頁的「在這個清單裡找」。
 *
 * 邊打邊篩畫面上已經渲染好的卡片，不打 API：伺服器端已經在 q 這個 GET
 * 參數上做了同樣的篩選（清單頁篩品名／編號，分類頁篩品名／編號的 SQL
 * LIKE），這裡只是讓使用者在按 Enter、送出表單之前就先看到結果，加速
 * 「打字→看結果」這個迴圈。按 Enter 或沒有 JavaScript 時，表單照樣用
 * GET 送出、換一頁真正命中資料庫／預熱好的清單。
 *
 * 分類頁的即時篩只篩「這一頁已經載入的商品」（分頁通常一頁 24 件），
 * 不是整個分類——分類頁的 placeholder 已經寫明「篩這一頁的商品」，
 * 這裡不用再另外處理分頁造成的落差。
 */
(function () {
    const searchInputs = document.querySelectorAll('.uq-search-input');

    if (!searchInputs.length) {
        return;
    }

    function normalize(value) {
        return value.trim().toLowerCase();
    }

    /**
     * 依關鍵字顯示或隱藏卡片，並且在某個性別段的卡片全部被藏起來時，
     * 把那一段的標題跟章節選單的項目也一起藏起來。
     *
     * 分類頁沒有性別分段（data-gender-heading 找不到任何元素），
     * 下半段的 forEach 直接是空的，不影響上半段的卡片篩選。
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

    searchInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            filterCards(normalize(input.value));
        });
    });
})();
