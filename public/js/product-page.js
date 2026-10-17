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
            video.addEventListener('click', toggle);
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

    setUpCollapsibles();
    setUpVideos();
})();
