/**
 * 掃吊牌條碼找商品。畫面在 layouts/barcode-scanner.blade.php，查詢在 BarcodeController。
 *
 * 辨識照瀏覽器標準的 BarcodeDetector 介面寫：有原生（手機版 Chrome）就用原生，
 * 沒有（iPhone、Firefox）才在按下圖示時載入 barcode-detector 套件補上，程式只寫一套。
 */
(function () {
    'use strict';

    const trigger = document.querySelector('[data-barcode-scan]');
    const dialog = document.getElementById('barcode-scanner');

    if (!trigger || !dialog) {
        return;
    }

    // 太舊的瀏覽器開不了對話視窗，圖示留著也只是按了沒反應
    if (typeof dialog.showModal !== 'function') {
        trigger.hidden = true;

        return;
    }

    // 跟 app.css 的手機斷點一致
    const queryInput = trigger.form && trigger.form.querySelector('input[data-placeholder-narrow]');

    if (queryInput) {
        const narrow = window.matchMedia('(max-width: 767px)');
        const fullPlaceholder = queryInput.placeholder;
        const fitPlaceholder = function () {
            queryInput.placeholder = narrow.matches ? queryInput.dataset.placeholderNarrow : fullPlaceholder;
        };

        fitPlaceholder();
        narrow.addEventListener('change', fitPlaceholder);
    }

    const DETECTOR_SRC = 'https://cdn.jsdelivr.net/npm/barcode-detector@3.2.2/dist/iife/ponyfill.js';
    const DETECTOR_INTEGRITY = 'sha384-KJVUIUGb4pmfpWXfD8j3y9ARn6KoGC2vPC/wNUX1O1ZkYemh0BsuvsmdEU2+wS7O';
    const WASM_BASE = 'https://cdn.jsdelivr.net/npm/zxing-wasm@';
    const WASM_FILE = '/dist/reader/zxing_reader.wasm';

    // 吊牌是 EAN-13；只開需要的格式，辨識比較快也比較不會誤判
    const FORMATS = ['ean_13'];
    const SCAN_INTERVAL = 150;
    const SLOW_HINT_AFTER = 5000;
    // 掃到後停格讓人看清楚「掃到了」，再換頁
    const CONFIRM_FOR = 500;

    const TEXT = {
        starting: '正在開啟鏡頭…',
        aim: '把條碼放進框框裡',
        slow: '靠近一點，或換個角度避開反光',
        scanned: '掃到了，找一下…',
        wrongDigits: '數字好像少了或打錯了，再對一次吊牌',
        noNeed: '不想開也沒關係，直接輸入條碼數字就好。',
    };

    // 查詢結果的文字；按鈕依是鏡頭畫面還是輸入框而不同
    const RESULTS = {
        not_listed: {
            title: '這件我們還沒收錄',
            detail: '可能是剛上架的新品，先到官網看看吧。',
            camera: ['retry', 'official'],
            manual: ['retry', 'official'],
        },
        not_product: {
            title: '這好像不是 UNIQLO 或 GU 的吊牌條碼',
            detail: '可以改用搜尋框找找看。',
            camera: ['retry', 'search'],
            manual: ['retry', 'search'],
        },
        invalid: {
            title: '數字好像少了或打錯了',
            detail: '再對一次吊牌上的條碼。',
            camera: ['retry', 'type'],
            manual: ['retry'],
        },
        unavailable: {
            title: '現在查不到，等一下再試試',
            detail: '也可以在搜尋框打吊牌上的商品編號。',
            camera: ['retry', 'search'],
            manual: ['retry', 'search'],
        },
    };

    // 鏡頭不能用時的說明；相機權限的步驟依裝置換，見 permissionSteps()
    const NOTICES = {
        denied: { title: '沒辦法使用相機', detail: '想用鏡頭掃的話，要先允許這個網站使用相機：' },
        unusable: { title: '鏡頭打不開', detail: '可能有其他程式正在用鏡頭。' },
        noDetector: { title: '掃描功能沒載入成功', detail: '可能是網路不穩，等一下再試試。' },
    };

    const video = dialog.querySelector('video');
    const status = dialog.querySelector('[data-scanner-status]');
    const torchButton = dialog.querySelector('[data-scanner-torch]');
    const card = dialog.querySelector('[data-camera-result]');
    const panel = document.getElementById('barcode-panel');
    const panelTitle = panel.querySelector('[data-panel-title]');
    const form = panel.querySelector('[data-panel-form]');
    const input = form.querySelector('input');
    const formError = panel.querySelector('[data-panel-error]');
    const message = panel.querySelector('[data-panel-message]');
    const submitButton = panel.querySelector('[data-panel-submit]');
    const useCameraButton = panel.querySelector('[data-panel-use-camera]');
    const panelActions = panel.querySelector('[data-panel-actions]');
    const panelClose = panel.querySelector('[data-panel-close]');
    const searchInput = document.querySelector('input[name="query"]');

    // 觸控為主的裝置一打開就開鏡頭；電腦先給輸入框，偵測到鏡頭才給「改用鏡頭掃」
    const cameraFirst = window.matchMedia('(pointer: coarse)').matches;
    const canUseCamera = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

    let detectorPromise = null;
    let stream = null;
    let hasCamera = false;
    // 每次開、關、重來都換一個號碼，晚到的非同步結果對不上就丟掉
    let session = 0;
    let state = 'closed';
    let scanTimer = null;
    let hintTimer = null;
    let torchOn = false;
    let querySeq = 0;
    // 關掉之後焦點要回到哪裡；沒指定就回掃描圖示
    let returnFocusTo = null;
    // 對話框是自己要換畫面才關的（回鏡頭），不是使用者要整個關掉
    let panelSwitching = false;

    function isValidEan13(value) {
        if (!/^\d{13}$/.test(value)) {
            return false;
        }

        let sum = 0;

        for (let i = 0; i < 12; i++) {
            sum += Number(value[i]) * (i % 2 === 0 ? 1 : 3);
        }

        return (10 - (sum % 10)) % 10 === Number(value[12]);
    }

    function hexToBase64(hex) {
        return btoa(hex.match(/../g).map(function (byte) {
            return String.fromCharCode(parseInt(byte, 16));
        }).join(''));
    }

    function loadScript(src, integrity) {
        return new Promise(function (resolve, reject) {
            const script = document.createElement('script');

            script.src = src;
            script.integrity = integrity;
            script.crossOrigin = 'anonymous';
            script.referrerPolicy = 'no-referrer';
            script.onload = resolve;
            script.onerror = function () {
                script.remove();
                reject(new Error('detector script failed to load'));
            };
            document.head.appendChild(script);
        });
    }

    function getDetector() {
        if (!detectorPromise) {
            detectorPromise = (async function () {
                if ('BarcodeDetector' in window) {
                    try {
                        const supported = await window.BarcodeDetector.getSupportedFormats();

                        if (FORMATS.every(function (format) { return supported.includes(format); })) {
                            return new window.BarcodeDetector({ formats: FORMATS });
                        }
                    } catch (error) {
                        // 原生的不能用就改用套件
                    }
                }

                if (!window.BarcodeDetectionAPI) {
                    await loadScript(DETECTOR_SRC, DETECTOR_INTEGRITY);
                }

                const api = window.BarcodeDetectionAPI;

                // 辨識核心 .wasm 自己下載：套件預設從 fastly.jsdelivr.net 抓且不驗內容。
                // 版本與 SHA-256 取自上面驗過完整性的套件，雜湊對不上 fetch 會直接失敗。
                // 先等它載好，載不到才能在開鏡頭當下就改成手動輸入
                const response = await fetch(WASM_BASE + api.ZXING_WASM_VERSION + WASM_FILE, {
                    integrity: 'sha256-' + hexToBase64(api.ZXING_WASM_SHA256),
                    credentials: 'omit',
                });

                if (!response.ok) {
                    throw new Error('detector core failed to load');
                }

                await api.prepareZXingModule({
                    overrides: { wasmBinary: await response.arrayBuffer() },
                    fireImmediately: true,
                });

                return new api.BarcodeDetector({ formats: FORMATS });
            })();

            // 網路斷掉之類的失敗，下次按還能重試
            detectorPromise.catch(function () {
                detectorPromise = null;
            });
        }

        return detectorPromise;
    }

    /**
     * 開相機權限的步驟。畫面上不寫平台名稱，直接給步驟；判斷不出來就給一句通用說法。
     */
    function permissionSteps() {
        const ua = navigator.userAgent;
        // 加到主畫面、或在通訊軟體與社群內開的網頁沒有網址列，給通用說法
        const noAddressBar = window.matchMedia('(display-mode: standalone)').matches
            || navigator.standalone === true
            || /\bLine\/|FBAN|FBAV|Instagram|MicroMessenger/.test(ua);

        if (noAddressBar) {
            return null;
        }
        const isIos = /iPhone|iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        if (isIos && !/CriOS|FxiOS|EdgiOS/.test(ua)) {
            return ['點網址列左邊的「大小」', '選「網站設定」', '相機改成「允許」'];
        }

        if (/Android/.test(ua) && /Chrome/.test(ua)) {
            return ['點網址列左邊的圖示', '選「權限」', '打開相機'];
        }

        if (!isIos && !/Mobile|Android/.test(ua) && /Chrome|Edg/.test(ua)) {
            return ['點網址列左邊的圖示', '相機改成「允許」', '重新整理頁面'];
        }

        return null;
    }

    function setState(next) {
        state = next;
        dialog.dataset.state = next;
    }

    function setStatus(text) {
        status.textContent = text;
    }

    function clearTimers() {
        clearTimeout(scanTimer);
        clearTimeout(hintTimer);
    }

    function lockPage(locked) {
        document.documentElement.classList.toggle('uq-scanner-open', locked);
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
        }

        stream = null;
        video.srcObject = null;
        torchOn = false;
        torchButton.hidden = true;
        torchButton.setAttribute('aria-pressed', 'false');
    }

    async function startCamera(token) {
        const media = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1920 },
                height: { ideal: 1080 },
            },
        });

        // 等權限的時候使用者可能已經關掉或改成輸入數字了
        if (token !== session) {
            media.getTracks().forEach(function (track) {
                track.stop();
            });

            return false;
        }

        stream = media;
        video.srcObject = media;

        try {
            await video.play();
        } catch (error) {
            // 靜音影片一般不會被擋；擋了也還有 autoplay 屬性
        }

        const track = media.getVideoTracks()[0];
        const capabilities = track && track.getCapabilities ? track.getCapabilities() : {};

        torchButton.hidden = !capabilities.torch;

        return true;
    }

    // ---- 按鈕 ------------------------------------------------------------

    function makeButton(kind, data, where) {
        const isCamera = where === 'camera';
        let element;

        if (kind === 'official') {
            const isGu = data.brand === 'GU';

            element = document.createElement('a');
            element.href = data.url;
            element.target = '_blank';
            element.rel = 'nofollow noopener';
            // 品牌色跟商品頁的「前往官網」一樣
            element.className = 'ts button ' + (isGu ? 'info' : 'negative');
            element.textContent = isGu ? '到 GU 官網看' : '到 UNIQLO 官網看';

            return element;
        }

        element = document.createElement('button');
        element.type = 'button';

        if (kind === 'retry') {
            element.className = 'ts basic button';
            element.textContent = isCamera ? '再掃一件' : '重新輸入';
            element.addEventListener('click', isCamera ? resumeScanning : function () {
                showForm();
                input.select();
            });
        } else if (kind === 'type') {
            element.className = 'ts button';
            element.textContent = '輸入條碼數字';
            element.addEventListener('click', function () {
                showForm();
            });
        } else if (kind === 'search') {
            element.className = 'ts button';
            element.textContent = '改用搜尋框';
            element.addEventListener('click', function () {
                returnFocusTo = searchInput;
                closeAll();
            });
        }

        return element;
    }

    function fillButtons(container, kinds, data, where) {
        container.replaceChildren.apply(container, kinds
            .filter(function (kind) { return kind !== 'official' || !!data.url; })
            .map(function (kind) { return makeButton(kind, data, where); }));
    }

    // ---- 對話框（輸入條碼、查詢結果、說明） ------------------------------------

    function openPanel() {
        lockPage(true);

        if (!panel.open) {
            panel.showModal();
        }
    }

    // 有鏡頭畫面在後面時，回去接著掃；沒有就開鏡頭
    function backToCamera() {
        if (panel.open) {
            // close 事件是之後才排進來的，旗標由事件處理時收回
            panelSwitching = true;
            panel.close();
        }

        if (dialog.open) {
            resumeScanning();
        } else {
            openCamera();
        }
    }

    function setBusy(busy) {
        submitButton.disabled = busy;
        submitButton.classList.toggle('loading', busy);
    }

    function showForm() {
        // 還在等權限或辨識程式的開鏡頭工作一起作廢，晚到了不會再開始掃描
        session++;
        clearTimers();

        if (dialog.open) {
            setState('manual');
            setStatus('');
            card.hidden = true;
        }

        panelTitle.textContent = '輸入條碼數字';
        form.hidden = false;
        message.hidden = true;
        panelActions.replaceChildren();
        submitButton.hidden = false;
        useCameraButton.hidden = !(dialog.open || hasCamera);
        formError.textContent = '';
        setBusy(false);
        openPanel();
        input.focus();
    }

    function showPanelMessage(title, detail, steps, hint) {
        panelTitle.textContent = title;
        form.hidden = true;
        message.hidden = false;
        submitButton.hidden = true;
        useCameraButton.hidden = true;

        const list = message.querySelector('[data-panel-steps]');

        message.querySelector('[data-panel-detail]').textContent = detail;
        message.querySelector('[data-panel-hint]').textContent = hint || '';
        list.replaceChildren.apply(list, (steps || []).map(function (step) {
            const item = document.createElement('li');

            item.textContent = step;

            return item;
        }));

        openPanel();
        panelTitle.focus();
    }

    // 鏡頭不能用：關掉鏡頭畫面，改成對話框說明，並給「輸入條碼數字」
    function showNotice(kind) {
        session++;
        clearTimers();
        stopCamera();

        if (dialog.open) {
            dialog.close();
        }

        let detail = NOTICES[kind].detail;
        let steps = null;

        if (kind === 'denied') {
            steps = permissionSteps();

            if (!steps) {
                detail = '想用鏡頭掃的話，到瀏覽器的網站設定，允許這個網站使用相機。';
            }
        }

        showPanelMessage(NOTICES[kind].title, detail, steps, TEXT.noNeed);
        fillButtons(panelActions, ['type'], {}, 'manual');
    }

    // 沒授權前也查得到「有沒有鏡頭」，只是沒有名稱
    async function detectCamera() {
        if (!canUseCamera || !navigator.mediaDevices.enumerateDevices) {
            return;
        }

        try {
            const devices = await navigator.mediaDevices.enumerateDevices();

            hasCamera = devices.some(function (device) { return device.kind === 'videoinput'; });
        } catch (error) {
            hasCamera = false;
        }

        if (panel.open && !form.hidden && !dialog.open) {
            useCameraButton.hidden = !hasCamera;
        }
    }

    // ---- 鏡頭 ------------------------------------------------------------

    async function openCamera() {
        session++;

        const token = session;

        lockPage(true);

        if (!dialog.open) {
            dialog.showModal();
        }

        dialog.classList.remove('is-locked');
        card.hidden = true;
        setState('starting');
        setStatus(TEXT.starting);

        // 相機權限與辨識程式同時準備，按下圖示到能掃的等待比較短
        const detectorReady = getDetector();

        try {
            if (!(await startCamera(token))) {
                return;
            }
        } catch (error) {
            if (token === session) {
                showNotice(error && (error.name === 'NotAllowedError' || error.name === 'SecurityError')
                    ? 'denied'
                    : 'unusable');
            }

            return;
        }

        let detector;

        try {
            detector = await detectorReady;
        } catch (error) {
            if (token === session) {
                showNotice('noDetector');
            }

            return;
        }

        if (token === session) {
            startScanning(detector);
        }
    }

    function startScanning(detector) {
        const token = session;

        clearTimers();
        setState('scanning');
        setStatus(TEXT.aim);
        card.hidden = true;
        dialog.classList.remove('is-locked');

        if (video.paused) {
            video.play().catch(function () {});
        }

        hintTimer = setTimeout(function () {
            if (token === session && state === 'scanning') {
                setStatus(TEXT.slow);
            }
        }, SLOW_HINT_AFTER);

        async function tick() {
            if (token !== session || state !== 'scanning') {
                return;
            }

            if (video.readyState >= 2) {
                try {
                    const codes = await detector.detect(video);
                    const hit = codes.find(function (code) { return isValidEan13(code.rawValue); });

                    if (hit && token === session && state === 'scanning') {
                        lockOn(hit.rawValue);

                        return;
                    }
                } catch (error) {
                    // 單一張辨識失敗就換下一張
                }
            }

            scanTimer = setTimeout(tick, SCAN_INTERVAL);
        }

        tick();
    }

    // 回到鏡頭畫面：鏡頭還開著就直接接著掃，關掉了（切過背景）就重開
    function resumeScanning() {
        if (!stream) {
            openCamera();

            return;
        }

        const token = ++session;

        getDetector().then(function (detector) {
            if (token === session) {
                startScanning(detector);
            }
        }, function () {
            if (token === session) {
                showNotice('noDetector');
            }
        });
    }

    function lockOn(barcode) {
        clearTimers();
        setState('locked');
        video.pause();
        dialog.classList.add('is-locked');
        setStatus(TEXT.scanned);
        query(barcode, 'camera');
    }

    // ---- 查詢 ------------------------------------------------------------

    async function query(barcode, where) {
        const token = session;
        // 只有最後一次查詢的回應能更新畫面或跳頁
        const seq = ++querySeq;
        const startedAt = performance.now();
        let data = null;

        try {
            const response = await fetch(dialog.dataset.endpoint + '/' + barcode, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            data = await response.json();
        } catch (error) {
            // 斷網、或功能剛被關掉（404 不是 JSON），都當成暫時查不到
        }

        if (!data || !data.status) {
            data = { status: 'unavailable', barcode: barcode };
        }

        await new Promise(function (resolve) {
            setTimeout(resolve, Math.max(0, CONFIRM_FOR - (performance.now() - startedAt)));
        });

        if (token !== session || seq !== querySeq) {
            return;
        }

        if (data.status === 'found' && data.url) {
            stopCamera();
            window.location.assign(data.url);

            return;
        }

        setBusy(false);
        showResult(data, where);
    }

    function showResult(data, where) {
        const config = RESULTS[data.status] || RESULTS.unavailable;
        // 只給吊牌上看得到的商品編號，不給 13 位條碼
        const hint = data.code && data.status === 'not_listed' ? '商品編號 ' + data.code : '';

        clearTimers();

        if (where === 'camera') {
            setState('result');
            setStatus('');
            card.hidden = false;
            card.querySelector('[data-result-title]').textContent = config.title;
            card.querySelector('[data-result-detail]').textContent = config.detail;
            card.querySelector('[data-result-hint]').textContent = hint;
            fillButtons(card.querySelector('[data-result-actions]'), config.camera, data, 'camera');
            card.querySelector('[data-result-title]').focus();

            return;
        }

        showPanelMessage(config.title, config.detail, null, hint);
        fillButtons(panelActions, config.manual, data, 'manual');
    }

    // ---- 開與關 ------------------------------------------------------------

    function open() {
        returnFocusTo = null;
        session++;

        if (cameraFirst && canUseCamera) {
            openCamera();
        } else {
            showForm();
            detectCamera();
        }
    }

    function closeAll() {
        if (panel.open) {
            panel.close();
        }

        if (dialog.open) {
            dialog.close();
        }
    }

    function restoreFocus() {
        const target = returnFocusTo || (trigger.offsetParent !== null ? trigger : null);

        if (target) {
            target.focus();
        }
    }

    trigger.addEventListener('click', open);

    // Esc、關閉鈕、離開頁面都會走到這裡
    dialog.addEventListener('close', function () {
        session++;
        setState('closed');
        clearTimers();
        stopCamera();
        dialog.classList.remove('is-locked');

        if (panel.open) {
            return;
        }

        lockPage(false);

        restoreFocus();
    });

    panel.addEventListener('close', function () {
        if (panelSwitching) {
            panelSwitching = false;

            return;
        }

        session++;
        setBusy(false);
        input.value = '';
        closeAll();
        lockPage(false);

        restoreFocus();
    });

    dialog.querySelector('[data-scanner-close]').addEventListener('click', closeAll);

    // 關閉鈕維持 Tocas 原生的 <i class="close icon">（位置依賴它），自己補鍵盤操作
    panelClose.addEventListener('click', closeAll);
    panelClose.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            closeAll();
        }
    });

    dialog.querySelector('[data-scanner-manual]').addEventListener('click', showForm);
    useCameraButton.addEventListener('click', backToCamera);

    torchButton.addEventListener('click', async function () {
        const track = stream && stream.getVideoTracks()[0];

        if (!track) {
            return;
        }

        try {
            await track.applyConstraints({ advanced: [{ torch: !torchOn }] });
            torchOn = !torchOn;
            torchButton.setAttribute('aria-pressed', torchOn ? 'true' : 'false');
        } catch (error) {
            torchButton.hidden = true;
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (submitButton.disabled) {
            return;
        }

        const barcode = input.value.replace(/\D/g, '');

        // 打錯一碼先在這裡擋下，不白查一次
        if (!isValidEan13(barcode)) {
            formError.textContent = TEXT.wrongDigits;
            input.focus();

            return;
        }

        formError.textContent = '';
        setBusy(true);
        query(barcode, 'manual');
    });

    input.addEventListener('input', function () {
        formError.textContent = '';
    });

    // 切到背景就關鏡頭（省電、也讓別的程式能用），回來時還在掃描才重新取得
    document.addEventListener('visibilitychange', function () {
        if (!dialog.open) {
            return;
        }

        if (document.hidden) {
            if (state === 'scanning' || state === 'starting') {
                // 還在等權限或辨識程式的那一輪也作廢，回來時重開
                session++;
                clearTimers();
                setState('paused');
            }

            stopCamera();

            return;
        }

        if (state === 'paused') {
            openCamera();
        }
    });

    // 跳到商品頁或離開時收掉；按上一頁回來時不會停在停格畫面
    window.addEventListener('pagehide', closeAll);
})();
