/**
 * GA4 自訂事件。gtag 只在設定了 GA_TRACKING_ID 時才載入（layouts/google-analytics），
 * 沒有就整個略過，本機開發不用設定 GA 也不會報錯。
 *
 * 商品資料由伺服器寫在 data-analytics-item（JSON，GA4 items 陣列的一筆），
 * 來源寫在外層的 data-analytics-list。
 */
window.UqAnalytics = (function () {
    function track(name, params) {
        if (typeof window.gtag !== 'function') {
            return;
        }

        window.gtag('event', name, params);
    }

    // 壞掉的 JSON 寧可少送一筆事件，不能讓點擊或收藏跟著壞
    function itemOf(element) {
        const holder = element.closest('[data-analytics-item]');

        if (!holder) {
            return null;
        }

        try {
            return JSON.parse(holder.dataset.analyticsItem);
        } catch (e) {
            return null;
        }
    }

    /**
     * 點卡片或收藏列表裡的商品連結。收藏鈕是 <button> 不是連結，按愛心不算。
     * 中鍵開新分頁只有 auxclick，比價常這樣開，一起算。
     */
    function trackSelectItem(event) {
        if (event.type === 'auxclick' && event.button !== 1) {
            return;
        }

        const link = event.target.closest('a[href]');
        const list = link && link.closest('[data-analytics-list]');
        const item = list && itemOf(link);

        if (!item) {
            return;
        }

        track('select_item', {
            item_list_name: list.dataset.analyticsList,
            items: [item],
        });
    }

    // 只有搜尋結果頁有；零結果也要送，才找得出大家找不到的字
    function trackSearchResults() {
        const results = document.querySelector('[data-analytics-search-term]');

        if (!results) {
            return;
        }

        track('search_results', {
            search_term: results.dataset.analyticsSearchTerm,
            result_count: Number(results.dataset.analyticsResultCount),
        });
    }

    document.addEventListener('click', trackSelectItem);
    document.addEventListener('auxclick', trackSelectItem);
    document.addEventListener('DOMContentLoaded', trackSearchResults);

    return { track: track, itemOf: itemOf };
})();
