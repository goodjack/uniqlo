{{-- 標籤篩選的外觀。清單頁與分類頁共用。 --}}
<style>
    .tag-filter summary {
        cursor: pointer;
        padding: 0.5rem 0;
        font-weight: 500;
    }

    .tag-filter-options {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin: 0.5rem 0;
    }

    .tag-filter-options label {
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    /* 勾起來的要看得出來，不然按了跟沒按一樣 */
    .tag-filter-options label:has(input:checked) {
        background: #ce5f58 !important;
        border-color: #ce5f58 !important;
        color: #fff !important;
    }

    .tag-filter-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 0.5rem;
    }
</style>
