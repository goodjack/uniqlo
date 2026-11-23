'use strict';

(function () {
    // 沒跑到這裡時清單維持全開，展開鈕維持 hidden
    function setUpCollapsibles() {
        document.querySelectorAll('[data-collapse-after]').forEach(list => {
            const button = list.nextElementSibling;

            if (!button?.matches('.uq-expand')) {
                return;
            }

            [...list.children].slice(Number(list.dataset.collapseAfter)).forEach(item => item.classList.add('uq-folded'));
            list.classList.add('uq-collapsed');
            button.hidden = false;
            button.addEventListener('click', () => {
                const firstRevealed = list.querySelector('.uq-folded');

                list.classList.remove('uq-collapsed');
                // 按鈕要拿掉，焦點先交給剛出現的第一張，鍵盤使用者才不會跳過它們
                (firstRevealed.matches('a') ? firstRevealed : firstRevealed.querySelector('a'))?.focus();
                button.remove();
            });
        });
    }

    // 使用者按了暫停，滑出再滑回來不自己續播；系統設定減少動態效果時不自動播
    function setUpVideos() {
        const players = document.querySelectorAll('.uq-video');

        if (players.length === 0) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            players.forEach(player => {
                const video = player.querySelector('video');
                video.src = video.dataset.src;
                video.controls = true;
                video.hidden = false;
            });

            return;
        }

        const visibleRatio = 0.25;

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const pausedByUser = new WeakSet();
        const play = video => video.play().catch(() => {});

        players.forEach(player => {
            const video = player.querySelector('video');
            const button = player.querySelector('.uq-video-toggle');
            const icon = button.querySelector('.icon');

            video.hidden = false;
            button.hidden = false;

            if (reduceMotion) {
                pausedByUser.add(video);
            }

            const showState = () => {
                icon.className = (video.paused ? 'play' : 'pause') + ' icon';
                button.setAttribute('aria-label', video.paused ? '播放影片' : '暫停影片');
            };

            const toggle = () => {
                if (!video.src) {
                    video.src = video.dataset.src;
                }

                if (video.paused) {
                    pausedByUser.delete(video);
                    play(video);
                } else {
                    pausedByUser.add(video);
                    video.pause();
                }
            };

            video.addEventListener('play', showState);
            video.addEventListener('pause', showState);
            button.addEventListener('click', toggle);
        });

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                const video = entry.target.querySelector('video');

                if (!entry.isIntersecting || entry.intersectionRatio < visibleRatio) {
                    video.pause();

                    return;
                }

                if (!video.src) {
                    video.preload = 'metadata';
                    video.src = video.dataset.src;
                }

                if (!pausedByUser.has(video)) {
                    play(video);
                }
            });
        }, { threshold: [0, visibleRatio] });

        players.forEach(player => observer.observe(player));
    }

    /**
     * 各段有沒有被看到、怎麼被打開的 GA 事件（UqAnalytics 沒設 GA 時什麼都不送）：
     * section_view       段標題進入畫面並停留 1 秒（exposure: heading），歷史價格與日本版的價格內容一半進入畫面並停留 1 秒時再送一次（exposure: content）
     * section_menu_click 點章節選單
     * media_open         開大圖或社群貼文，source 是哪一組
     * go_official        前往官網，location 是哪一顆按鈕
     */
    function setUpTracking() {
        const track = window.UqAnalytics ? window.UqAnalytics.track : () => {};

        if ('IntersectionObserver' in window) {
            // 停留夠久才算看到：點章節選單平滑捲動、或手機快速甩動時，一路滑過的段落不算
            const dwell = 1000;

            const observe = (elements, threshold) => {
                const timers = new Map();
                const observer = new IntersectionObserver((entries, self) => {
                    entries.forEach(entry => {
                        if (!entry.isIntersecting || entry.intersectionRatio < threshold) {
                            clearTimeout(timers.get(entry.target));
                            timers.delete(entry.target);

                            return;
                        }

                        if (timers.has(entry.target)) {
                            return;
                        }

                        timers.set(entry.target, setTimeout(() => {
                            timers.delete(entry.target);
                            self.unobserve(entry.target);
                            track('section_view', {
                                section: entry.target.dataset.sectionView,
                                exposure: entry.target.dataset.sectionExposure || 'heading',
                            });
                        }, dwell));
                    });
                }, { threshold: threshold });

                elements.forEach(element => observer.observe(element));
            };

            observe(document.querySelectorAll('[data-section-view]:not([data-section-exposure])'), 0);
            observe(document.querySelectorAll('[data-section-view][data-section-exposure="content"]'), 0.5);
        }

        document.addEventListener('click', event => {
            const menuItem = event.target.closest('.uq-section-menu a.item[href^="#"]');
            // 社群卡片上的預覽播放鈕只在卡片裡播，不算打開
            const media = event.target.closest('.uq-social-toggle')
                ? null
                : event.target.closest('a[data-lightbox], .uq-social-card, [data-social-all]');
            const official = event.target.closest('a[data-analytics-official]');

            if (menuItem) {
                track('section_menu_click', { section: menuItem.hash.slice(1) });
            }

            if (media) {
                track('media_open', { source: media.dataset.lightbox || 'social' });
            }

            if (official) {
                track('go_official', { location: official.dataset.analyticsOfficial });
            }
        });
    }

    setUpCollapsibles();
    setUpVideos();
    setUpTracking();
})();
