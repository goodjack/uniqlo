{{-- 掃吊牌條碼，行為在 public/js/barcode-scan.js。總開關關閉時整段不輸出 --}}

<dialog class="uq-scanner" id="barcode-scanner" aria-labelledby="barcode-scanner-title"
    data-endpoint="{{ url('barcodes') }}">
    <div class="uq-scanner-camera">
        <video playsinline muted autoplay></video>
        <div class="uq-scanner-frame"></div>
    </div>

    <div class="uq-scanner-bar">
        <button type="button" class="ts circular icon button" data-scanner-close aria-label="關閉">
            <i class="close icon" aria-hidden="true"></i>
        </button>
        <h2 class="unstyled uq-scanner-title" id="barcode-scanner-title">掃吊牌條碼</h2>
        <button type="button" class="ts circular compact button" data-scanner-torch aria-pressed="false" hidden>
            <i class="lightbulb icon" aria-hidden="true"></i>手電筒
        </button>
    </div>

    <p class="uq-scanner-status" data-scanner-status role="status" aria-live="polite"></p>

    <div class="uq-scanner-actions">
        <button type="button" class="ts circular button" data-scanner-manual>輸入條碼數字</button>
    </div>

    {{-- 掃到了但沒有商品頁可跳：不會自己消失，按「再掃一件」才恢復 --}}
    <div class="uq-scanner-card" data-camera-result hidden>
        <h3 class="unstyled uq-scanner-card-title" data-result-title tabindex="-1"></h3>
        <p class="uq-scanner-card-text" data-result-detail></p>
        <p class="uq-scanner-hint" data-result-hint></p>
        <div class="uq-scanner-card-actions" data-result-actions></div>
    </div>
</dialog>

{{-- 輸入條碼、查詢結果、相機不能用的說明，共用一個 Tocas 對話框 --}}
<dialog class="ts tiny modal uq-scanner-panel" id="barcode-panel" aria-labelledby="barcode-panel-title">
    <i class="close icon" role="button" tabindex="0" aria-label="關閉" data-panel-close></i>
    <div class="header" id="barcode-panel-title" data-panel-title tabindex="-1"></div>
    <div class="content">
        <form id="barcode-panel-form" data-panel-form novalidate>
            <div class="ts fluid input">
                <input id="barcode-panel-input" type="text" inputmode="numeric" autocomplete="off" maxlength="13"
                    placeholder="例如 2000216727355" aria-labelledby="barcode-panel-title"
                    aria-describedby="barcode-panel-hint barcode-panel-error">
            </div>
            <p class="uq-scanner-hint" id="barcode-panel-hint">條碼下方那排 13 位數字</p>
            <p class="uq-scanner-error" id="barcode-panel-error" data-panel-error aria-live="assertive"></p>
        </form>
        <div data-panel-message>
            <p class="uq-scanner-card-text" data-panel-detail></p>
            <ol class="uq-scanner-steps" data-panel-steps></ol>
            <p class="uq-scanner-hint" data-panel-hint></p>
        </div>
    </div>
    <div class="actions">
        <button type="button" class="ts link button uq-scanner-use-camera" data-panel-use-camera hidden>改用鏡頭掃</button>
        <span class="uq-scanner-panel-buttons" data-panel-actions></span>
        <button type="submit" class="ts button" form="barcode-panel-form" data-panel-submit>查詢</button>
    </div>
</dialog>
