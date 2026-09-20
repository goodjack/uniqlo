{{--
    清空收藏的確認視窗。工具列、「都已下架」、「載入失敗」三顆
    [data-favorites-clear] 按鈕共用同一顆對話視窗，favorites.js 記住是哪一顆
    被按下，關閉後把焦點還給它。

    確認鈕故意用 negative 外觀（品牌紅，警示這是清掉全部收藏），但 Tocas
    對話視窗預設把 .negative 當成「拒絕」；favorites.js 呼叫 .modal() 時
    明確指定 approve/deny 用 js-favorites-clear-confirm／js-favorites-clear-cancel
    這兩個選擇器，不靠 .negative 這個外觀 class 判斷，才不會按下確認反而被當成取消。
--}}
<div class="ts modals dimmer">
    <dialog class="ts tiny closable modal" id="favorites-clear-modal">
        <i class="close icon" role="button" tabindex="0" aria-label="關閉"></i>
        <div class="header">清空收藏？</div>
        <div class="content">
            將移除這個瀏覽器裡的 <span id="favorites-clear-count">0</span> 件收藏。
        </div>
        <div class="actions">
            <button type="button" class="ts basic button js-favorites-clear-cancel">取消</button>
            <button type="button" class="ts negative button js-favorites-clear-confirm">清空收藏</button>
        </div>
    </dialog>
</div>
