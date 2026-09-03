{{-- 標籤篩選的外觀。清單頁與分類頁共用。 --}}
<style>
    /* 上面的品牌與排序按鈕群是置中的，篩選是同一組控制項，對齊要一致 */
    .tag-filter {
        text-align: center;
    }

    .tag-filter summary {
        cursor: pointer;
        padding: 0.5rem 0;
        /* summary 原生的三角標記在 Tocas 底下看不出來，換成右邊自己畫的箭頭 */
        list-style: none;
    }

    .tag-filter summary::-webkit-details-marker {
        display: none;
    }

    .tag-filter summary .tag-filter-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .tag-filter .tag-filter-caret {
        transition: transform 0.15s;
    }

    .tag-filter details[open] .tag-filter-caret {
        transform: rotate(180deg);
    }

    .tag-filter-options {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5rem;
        margin: 0.75rem 0 0;
    }

    .tag-filter-options label {
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        position: relative;
        /* .ts.label 自帶左右 margin，跟 gap 疊起來每一顆的間距會不一樣 */
        margin: 0;
    }

    /*
     * checkbox 是藏起來、不是拿掉：display:none 會讓它連鍵盤焦點都拿不到，
     * 整組篩選就只剩滑鼠能用。作業系統原生的藍色勾選框跟站上的顏色也搭不起來，
     * 選中與否改由標籤本身的底色表示。
     */
    .tag-filter-options input[type="checkbox"] {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
        margin: 0;
    }

    /* 勾起來的要看得出來，不然按了跟沒按一樣 */
    .tag-filter-options label:has(input:checked) {
        background: #ce5e57 !important;
        border-color: #ce5e57 !important;
        color: #fff !important;
    }

    /* 焦點框是藏掉 checkbox 的代價，要在標籤上補回來 */
    .tag-filter-options label:has(input:focus-visible) {
        outline: 2px solid #ce5e57;
        outline-offset: 2px;
    }

    .tag-filter-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 0.75rem;
    }

    /*
     * 套用鍵原本是 primary，但 primary 的 cyan 在這個站是「特價」「歷史新低價」
     * 那些狀態標籤的顏色，放在操作按鈕上會被讀成狀態。改用品牌紅，跟選中的標籤同色。
     */
    .tag-filter-actions .tag-filter-apply,
    .tag-filter-actions .tag-filter-apply:hover,
    .tag-filter-actions .tag-filter-apply:focus,
    .tag-filter-actions .tag-filter-apply:active {
        background: #ce5e57;
        border-color: #ce5e57;
        color: #fff;
    }

    .tag-filter-actions .tag-filter-apply:hover,
    .tag-filter-actions .tag-filter-apply:focus,
    .tag-filter-actions .tag-filter-apply:active {
        background: #b8524c;
        border-color: #b8524c;
    }
</style>
