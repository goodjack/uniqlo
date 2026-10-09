{{-- 三個「清空收藏」入口共用這一顆，行為在 favorites.js 的 bindClearAll --}}
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
