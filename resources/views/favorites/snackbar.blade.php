{{--
    移除與清空收藏共用的復原提示條。外觀是 Tocas 原生的 .ts.snackbar，
    但 8 秒與滑鼠、鍵盤焦點暫停倒數不能交給 Tocas 內建的 JavaScript
    （它的計時固定約 3.5 秒、也不能暫停），全部由 favorites.js 自己控制：
    這裡只放 class="ts snackbar"、不加 active，js 直接切換 active 開關、
    自己管計時器。

    role="status" + aria-live="polite"：內容改變會被螢幕閱讀器讀出來，
    但不會像 alert 那樣搶走使用者正在做的事，也不會自動搬動鍵盤焦點。

    「復原」是真的 <button>，不是 Tocas 文件範例裡的 <a>：按鈕本身天生就能
    用 Tab 移到、用 Enter 或空白鍵觸發，不用另外寫 keydown。
--}}
<div class="ts snackbar" id="favorites-snackbar" role="status" aria-live="polite">
    <div class="content"></div>
    <button type="button" class="action" id="favorites-snackbar-undo">復原</button>
</div>
