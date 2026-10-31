'use strict';

(function () {
    const root = document.querySelector('[data-social-posts]');
    if (!root) {
        return;
    }

    const strip = root.querySelector('.uq-social-strip');
    const cards = [...strip.querySelectorAll('.uq-social-card')];
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const scripts = {};
    function loadScript(src) {
        if (!scripts[src]) {
            scripts[src] = new Promise((resolve) => {
                const s = document.createElement('script');
                s.src = src;
                s.async = true;
                s.onload = resolve;
                s.onerror = resolve;
                document.body.appendChild(s);
            });
        }
        return scripts[src];
    }

    let ytApi;
    function loadYoutubeApi() {
        if (!ytApi) {
            ytApi = new Promise((resolve) => {
                window.onYouTubeIframeAPIReady = resolve;
            });
            loadScript('https://www.youtube.com/iframe_api');
        }
        return ytApi;
    }

    // 卡片上的 YouTube 靜音預覽，照 YouTube 對嵌入播放器的規定：畫面出現一半以上才播、同時只播一支、
    // 播放器至少 200×200、上面不能疊東西也不能裁切。卡片太窄（手機）就不預覽，只有縮圖
    const MIN_PLAYER_WIDTH = 200;
    const previewCards = cards.filter((card) => card.dataset.youtubeId);
    const visible = new Set();
    let previewing = !reduceMotion;
    let lightboxOpen = false;
    let preferred = null;
    let active = null;

    const canPreview = (card) => !card.dataset.blocked
        && card.querySelector('.uq-social-media').offsetWidth >= MIN_PLAYER_WIDTH;

    function showToggle(card, playing) {
        const button = card.querySelector('.uq-social-toggle');
        button.hidden = !canPreview(card);
        button.querySelector('.icon').className = `${playing ? 'pause' : 'play'} icon`;
        button.setAttribute('aria-label', playing ? '暫停預覽' : '播放預覽');
        card.classList.toggle('is-playing', playing);
    }

    function stop() {
        if (!active) {
            return;
        }
        active.player?.destroy();
        active.host.remove();
        showToggle(active.card, false);
        active = null;
    }

    // 作者不允許在其他網站播放的影片，卡片只留縮圖
    function markBlocked(card) {
        card.dataset.blocked = '1';
        card.querySelector('.uq-social-media')
            .insertAdjacentHTML('beforeend', '<span class="ts mini label uq-social-blocked">只能到 YouTube 看</span>');
        setTimeout(() => {
            if (active?.card === card) {
                stop();
            }
            showToggle(card, false);
            pick();
        });
    }

    async function start(card) {
        const host = document.createElement('div');
        card.querySelector('.uq-social-media').appendChild(host);
        const current = { card, host, player: null };
        active = current;
        showToggle(card, true);

        await loadYoutubeApi();
        if (active !== current) {
            return;
        }

        const id = card.dataset.youtubeId;
        const begin = Number(card.dataset.start || 0);
        const slot = document.createElement('div');
        host.appendChild(slot);
        current.player = new YT.Player(slot, {
            videoId: id,
            host: 'https://www.youtube-nocookie.com',
            playerVars: {
                autoplay: 1,
                mute: 1,
                controls: 0,
                // YouTube 的 loop 會回到 0 秒；有指定開始時間的改在播完時自己跳回去
                loop: begin ? 0 : 1,
                playlist: id,
                playsinline: 1,
                rel: 0,
                start: begin,
            },
            events: {
                onError: () => markBlocked(card),
                onStateChange: (e) => {
                    if (e.data === YT.PlayerState.ENDED && begin) {
                        current.player.seekTo(begin);
                        current.player.playVideo();
                    }
                },
            },
        });
        current.player.getIframe().tabIndex = -1;
    }

    function pick() {
        const candidates = previewing && !lightboxOpen
            ? previewCards.filter((card) => visible.has(card) && canPreview(card))
            : [];
        const next = candidates.includes(preferred) ? preferred : (candidates[0] ?? null);
        if (next === (active?.card ?? null)) {
            return;
        }
        stop();
        if (next) {
            start(next);
        }
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(({ target, intersectionRatio }) => {
            intersectionRatio >= 0.5 ? visible.add(target) : visible.delete(target);
        });
        pick();
    }, { threshold: [0, 0.5] });

    previewCards.forEach((card) => {
        observer.observe(card);
        showToggle(card, false);
        // 按暫停就整排都不再自動預覽，按播放才恢復
        card.querySelector('.uq-social-toggle').addEventListener('click', (e) => {
            e.stopPropagation();
            if (active?.card === card) {
                previewing = false;
                preferred = null;
            } else {
                previewing = true;
                preferred = card;
            }
            pick();
        });
    });

    window.addEventListener('resize', () => {
        previewCards.forEach((card) => card !== active?.card && showToggle(card, false));
        pick();
    }, { passive: true });

    // 往左右看的箭頭：一次翻一頁、留一張接續，滑到底就收起來
    const prevArrow = root.querySelector('.uq-social-arrow.is-prev');
    const nextArrow = root.querySelector('.uq-social-arrow.is-next');
    const page = () => Math.max(cards[0].offsetWidth, strip.clientWidth - cards[0].offsetWidth);
    const scrollStrip = (direction) => strip.scrollBy({ left: direction * page(), behavior: reduceMotion ? 'auto' : 'smooth' });
    const updateArrows = () => {
        prevArrow.hidden = strip.scrollLeft < 8;
        nextArrow.hidden = strip.scrollLeft + strip.clientWidth > strip.scrollWidth - 8;
    };
    prevArrow.addEventListener('click', () => scrollStrip(-1));
    nextArrow.addEventListener('click', () => scrollStrip(1));
    strip.addEventListener('scroll', updateArrows, { passive: true });
    window.addEventListener('resize', updateArrows, { passive: true });
    updateArrows();

    // 點開：GLightbox 一則一則左右切換，每一則放平台官方的嵌入
    const elements = cards.map((card) => {
        const id = card.dataset.youtubeId;
        if (id) {
            const vertical = card.dataset.vertical === '1';
            const params = new URLSearchParams({ autoplay: 1, playsinline: 1, rel: 0 });
            if (card.dataset.start) {
                params.set('start', card.dataset.start);
            }
            return {
                content: `<div class="uq-social-gl-yt${vertical ? ' is-vertical' : ''}">`
                    + `<iframe data-src="https://www.youtube-nocookie.com/embed/${id}?${params}" title="YouTube"`
                    + ' allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe></div>',
                // 不給寬度的話 GLightbox 會用預設的 900×506 框，直式與貼文都會被裁
                width: vertical ? '420px' : '900px',
                height: 'auto',
            };
        }
        const template = root.querySelector(`template[data-embed="${card.dataset.index}"]`);
        return { content: `<div class="uq-social-embed">${template.innerHTML}</div>`, width: '540px', height: 'auto' };
    });

    const lightbox = typeof GLightbox === 'function'
        ? GLightbox({ elements, touchNavigation: true, loop: false, zoomable: false, draggable: true })
        : null;
    let lastFocus = null;
    // 關閉時 GLightbox 會把每一則重建，這次開啟掛的大小監聽要一起拿掉
    let openListeners = new AbortController();

    // 平台腳本會把 blockquote 換成 iframe；幾秒後還沒換，就是被瀏覽器或擴充套件擋了
    async function renderEmbed(slide) {
        const embed = slide.querySelector('.uq-social-embed');
        if (!embed || embed.dataset.rendered) {
            return;
        }
        embed.dataset.rendered = '1';
        fitToScreen(embed);

        // 計時從打開就開始算，平台腳本一直沒回應時也會換成備用卡片
        setTimeout(() => {
            const fallback = embed.querySelector('.uq-social-fallback');
            if (fallback && !embed.querySelector('iframe')) {
                embed.querySelectorAll(':scope > :not(.uq-social-fallback)').forEach((el) => el.remove());
                fallback.hidden = false;
            }
        }, 5000);

        // IG 與 Threads 的嵌入腳本是同一套，都掛在 window.instgrm 底下
        if (embed.querySelector('.instagram-media, .text-post-media')) {
            await loadScript(embed.querySelector('.text-post-media') ? 'https://www.threads.com/embed.js' : 'https://www.instagram.com/embed.js');
            window.instgrm?.Embeds?.process?.();
        } else if (embed.querySelector('.twitter-tweet')) {
            await loadScript('https://platform.twitter.com/widgets.js');
            window.twttr?.widgets?.load(embed);
        } else if (embed.querySelector('.fb-post, .fb-video')) {
            // Facebook 的嵌入寬度要用數字指定、最窄 350，不會自己撐滿；比 350 窄的由 fitToScreen 等比例縮小
            embed.querySelectorAll('.fb-post, .fb-video').forEach((el) => {
                el.dataset.width = Math.max(Math.round(embed.clientWidth), 350);
            });
            await loadScript('https://connect.facebook.net/zh_TW/sdk.js#xfbml=0&version=v25.0');
            window.FB?.XFBML?.parse(embed);
        }
    }

    // 平台貼文的高度由平台決定，常比畫面高；整則等比例縮到放得下，不必在框裡捲才看得到下半部。
    // Facebook 的寬度比框寬時也一樣縮。平台載入時會自己改高度、手機也會轉向，所以跟著重算
    function fitToScreen(embed) {
        const box = embed.closest('.gslide-inline');
        const fit = () => {
            embed.querySelectorAll('.fb-post[data-width], .fb-video[data-width]').forEach((el) => {
                const zoom = embed.clientWidth / el.dataset.width;
                el.style.zoom = zoom < 1 ? zoom : '';
            });
            const scale = Math.min(1, parseFloat(getComputedStyle(box).maxHeight) / embed.offsetHeight);
            embed.style.transform = scale < 1 ? `scale(${scale})` : '';
            embed.style.marginBottom = scale < 1 ? `${embed.offsetHeight * (scale - 1)}px` : '';
        };
        const observer = new ResizeObserver(fit);
        observer.observe(embed);
        openListeners.signal.addEventListener('abort', () => observer.disconnect());
        window.addEventListener('resize', fit, { passive: true, signal: openListeners.signal });
    }

    // GLightbox 會預先載入前後相鄰的那幾則，只有目前這一則的影片能播。
    // 事件給的「目前」會落後一則，所以等畫面切完再看實際標成 current 的那一則。
    // IG、Threads、X 的嵌入沒有暫停的方法，跟 YouTube 一樣離開時換成空白頁、回來再載入；
    // 只把網址重設一次不行，網址只差 # 後面時瀏覽器不會重新載入
    function syncVideos() {
        setTimeout(() => {
            const current = document.querySelector('.gslide.current');
            document.querySelectorAll('.uq-social-embed iframe:not([data-src])').forEach((frame) => {
                frame.dataset.src = frame.src;
            });
            document.querySelectorAll('.gslide iframe[data-src]').forEach((frame) => {
                if (current?.contains(frame)) {
                    if (!frame.src || frame.src === 'about:blank') {
                        frame.src = frame.dataset.src;
                    }
                } else {
                    frame.src = 'about:blank';
                }
            });
        }, 60);
    }

    lightbox?.on('slide_after_load', ({ slideNode }) => {
        syncVideos();
        renderEmbed(slideNode);
    });
    lightbox?.on('slide_changed', syncVideos);
    lightbox?.on('close', () => {
        document.querySelectorAll('.gslide iframe[data-src]').forEach((frame) => {
            frame.src = 'about:blank';
        });
        lightboxOpen = false;
        openListeners.abort();
        openListeners = new AbortController();
        pick();
        lastFocus?.focus();
    });

    // 電腦用滑鼠在黑色區域拖曳也能換則。GLightbox 只認拖在貼文上，但貼文是平台的嵌入、拖不到，
    // 而且拖完放開會被當成點外面而關掉
    let drag = null;
    document.addEventListener('mousedown', (e) => {
        if (lightboxOpen && e.button === 0 && !e.target.closest('.gslide-media, .gnext, .gprev, .gclose')) {
            drag = { x: e.clientX, y: e.clientY, moved: false };
        }
    });
    document.addEventListener('mousemove', (e) => {
        if (drag && Math.hypot(e.clientX - drag.x, e.clientY - drag.y) > 8) {
            drag.moved = true;
        }
    });
    document.addEventListener('mouseup', (e) => {
        if (!drag) {
            return;
        }
        const { x, moved } = drag;
        drag = null;
        if (!moved) {
            return;
        }
        // GLightbox 自己的拖曳也是用這個旗標擋掉放開後的那一下點擊
        lightbox.preventOutsideClick = true;
        setTimeout(() => {
            lightbox.preventOutsideClick = false;
        });
        const dx = e.clientX - x;
        if (Math.abs(dx) > 60) {
            dx < 0 ? lightbox.nextSlide() : lightbox.prevSlide();
        }
    });

    function open(index) {
        if (!lightbox) {
            window.open(cards[index].querySelector('.uq-social-title').href, '_blank', 'noopener');
            return;
        }
        lastFocus = document.activeElement;
        lightboxOpen = true;
        pick();
        lightbox.openAt(index);
    }

    // 滑鼠移上去、手指按下（滑動卡片列也算）或鍵盤移到卡片時，才先跟那個平台建好連線，點開時少等一段；
    // 沒碰到卡片的訪客不會因此連到平台
    const PLATFORM_ORIGINS = {
        youtube: ['https://www.youtube-nocookie.com'],
        instagram: ['https://www.instagram.com'],
        threads: ['https://www.threads.com'],
        x: ['https://platform.twitter.com'],
        facebook: ['https://connect.facebook.net', 'https://www.facebook.com'],
    };
    const preconnected = new Set();
    function preconnect(card) {
        (PLATFORM_ORIGINS[card.dataset.platform] || []).forEach((origin) => {
            if (preconnected.has(origin)) {
                return;
            }
            preconnected.add(origin);
            const link = document.createElement('link');
            link.rel = 'preconnect';
            link.href = origin;
            document.head.appendChild(link);
        });
    }
    function preconnectOnIntent(target, card) {
        ['pointerenter', 'pointerdown', 'focusin'].forEach((type) => {
            target.addEventListener(type, () => preconnect(card), { passive: true });
        });
    }

    cards.forEach((card) => {
        preconnectOnIntent(card, card);
        card.addEventListener('click', (e) => {
            e.preventDefault();
            open(Number(card.dataset.index));
        });
    });
    const allButton = document.querySelector('[data-social-all]');
    if (allButton && cards[0]) {
        preconnectOnIntent(allButton, cards[0]);
    }
    allButton?.addEventListener('click', () => open(0));
})();
