/**
 * @file
 * Initializes the IIIF carousel behavior.
 */
(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.iiifCarousel = {
    attach: function (context, settings) {
      // Get duration from drupalSettings, with a fallback to 10 seconds.
      const duration = drupalSettings.iiif_random_block?.carousel?.duration || 10000;
      const infoEnabled = drupalSettings.iiif_random_block?.carousel?.infoEnabled !== false; // default true
      const waitAllOnFirstCycle = true; // 初回のみ全画像のロード完了を待つ

      // Find all carousel containers in the current context that haven't been processed yet.
      const carousels = context.querySelectorAll('.iiif-carousel-container:not(.iiif-carousel-processed)');

      carousels.forEach(function (carousel) {
        // Mark as processed to prevent re-attaching the behavior.
        carousel.classList.add('iiif-carousel-processed');

        const slides = carousel.getElementsByClassName('iiif-carousel-item');
        if (slides.length === 0) {
          return;
        }

        const dotsWrapper = carousel.parentElement.querySelector('.iiif-carousel-dots');
        const dots = dotsWrapper ? dotsWrapper.querySelectorAll('.iiif-carousel-dot') : [];

        // Store state on the element itself.
        carousel.slideIndex = 0;
        let started = false;
        let timerId = null;
        let pausedByInfo = false;

        // Prepare images for smooth first render: add is-loaded when image completes.
        const imgs = carousel.querySelectorAll('.iiif-carousel-item img');
        imgs.forEach((img, idx) => {
          const markLoaded = () => img.classList.add('is-loaded');
          if (img.complete && img.naturalWidth > 0) {
            // Already loaded from cache
            markLoaded();
          } else {
            img.addEventListener('load', markLoaded, { once: true });
            img.addEventListener('error', () => img.classList.add('is-loaded'), { once: true });
          }
          // Hint: make the first image eager to reduce flash on first cycle.
          if (idx === 0) {
            img.setAttribute('loading', 'eager');
            img.setAttribute('decoding', 'async');
          }
        });

        // 初回のために全画像を事前にプリロード（非表示でも確実に取得させる）。
        const preloadPromises = Array.from(imgs).map((img) => new Promise((resolve) => {
          const done = () => {
            img.classList.add('is-loaded');
            resolve();
          };
          if (img.complete && img.naturalWidth > 0) {
            done();
            return;
          }
          // ブラウザの遅延読み込みヒューリスティクスを回避するため、明示的にプリロード。
          const pre = new Image();
          if (img.crossOrigin) pre.crossOrigin = img.crossOrigin;
          pre.onload = done;
          pre.onerror = done;
          pre.src = img.currentSrc || img.src;
          // 一方で実体imgにもload/errorリスナーは既に設定済み。
        }));

        function setActive(index) {
          for (let i = 0; i < slides.length; i++) {
            slides[i].classList.remove('active');
          }
          carousel.slideIndex = index;
          if (slides[index - 1]) {
            slides[index - 1].classList.add('active');
          }

          // Update dots
          if (dots && dots.length) {
            dots.forEach((dot) => {
              const di = parseInt(dot.getAttribute('data-slide-index'), 10) || 0;
              dot.classList.toggle('active', di === index);
            });
          }
        }

        function scheduleNext() {
          timerId = setTimeout(advance, duration);
        }

        function pause() {
          if (timerId) {
            clearTimeout(timerId);
            timerId = null;
          }
        }

        function resume() {
          if (!timerId && started && !pausedByInfo) {
            scheduleNext();
          }
        }

        function advance() {
          // If the element is no longer in the DOM, stop the loop.
          if (!document.body.contains(carousel)) {
            return;
          }
          let next = (carousel.slideIndex || 0) + 1;
          if (next > slides.length) next = 1;
          setActive(next);
          scheduleNext();
        }

        // 初期表示: 1枚目だけactiveにしてアニメーションを効かせる。
        setTimeout(() => setActive(1), 0);

        // 初回は全画像のロード完了を待ってから回転を開始。
        const startAfterPreload = () => {
          if (started) return;
          started = true;
          scheduleNext();
        };

        if (waitAllOnFirstCycle) {
          Promise.allSettled(preloadPromises).then(startAfterPreload);
        } else {
          // 互換: すぐに開始
          startAfterPreload();
        }

        // Dots click: jump to specific slide and reset timer.
        if (dotsWrapper && dots && dots.length) {
          dotsWrapper.addEventListener('click', (e) => {
            const btn = e.target.closest('.iiif-carousel-dot');
            if (!btn) return;
            e.preventDefault();
            const targetIndex = parseInt(btn.getAttribute('data-slide-index'), 10) || 1;
            pause();
            setActive(targetIndex);
            if (!pausedByInfo) {
              started = true;
              scheduleNext();
            }
          });
        }

        if (infoEnabled) {
          // Info panel interactions
          const updateScrollHints = (panel) => {
            const scroller = panel.querySelector('.iiif-info-scroll') || panel;
            const { scrollTop, scrollHeight, clientHeight } = scroller;
            const canScroll = scrollHeight > clientHeight + 1; // tolerate rounding
            panel.classList.toggle('can-scroll', canScroll);
            panel.classList.toggle('not-at-top', canScroll && scrollTop > 0);
            panel.classList.toggle('not-at-bottom', canScroll && (scrollTop + clientHeight < scrollHeight - 1));
          };

          const onClickInfo = (btn) => {
            const item = btn.closest('.iiif-carousel-item');
            const panel = item.querySelector('.iiif-info-panel');
            if (!panel) return;
            // Toggle open state
            const willOpen = !panel.classList.contains('open');
            document.querySelectorAll('.iiif-carousel-item .iiif-info-panel.open').forEach(p => p.classList.remove('open'));
            if (willOpen) {
              panel.classList.add('open');
              pausedByInfo = true;
              pause();
              // Update scroll hints initially and bind listeners
              updateScrollHints(panel);
              const scroller = panel.querySelector('.iiif-info-scroll') || panel;
              const onPanelScroll = () => updateScrollHints(panel);
              const onPanelResize = () => updateScrollHints(panel);
              scroller.addEventListener('scroll', onPanelScroll, { passive: true });
              panel._onPanelScroll = onPanelScroll;
              panel._scroller = scroller;
              // Use ResizeObserver when available
              if (window.ResizeObserver) {
                const ro = new ResizeObserver(onPanelResize);
                ro.observe(panel);
                panel._resizeObserver = ro;
              } else {
                // Fallback to periodic checks
                panel._resizeInterval = setInterval(() => updateScrollHints(panel), 250);
              }
            } else {
              panel.classList.remove('open');
              pausedByInfo = false;
              resume();
              // Cleanup scroll hint listeners
              panel.removeEventListener('scroll', panel._onPanelScroll || (() => { }));
              if (panel._resizeObserver) {
                panel._resizeObserver.disconnect();
                panel._resizeObserver = null;
              }
              if (panel._resizeInterval) {
                clearInterval(panel._resizeInterval);
                panel._resizeInterval = null;
              }
            }
          };

          const onClickClose = (btn) => {
            const panel = btn.closest('.iiif-info-panel');
            if (!panel) return;
            panel.classList.remove('open');
            pausedByInfo = false;
            resume();
            // Cleanup scroll hint listeners
            if (panel._onPanelScroll && panel._scroller) panel._scroller.removeEventListener('scroll', panel._onPanelScroll);
            if (panel._resizeObserver) {
              panel._resizeObserver.disconnect();
              panel._resizeObserver = null;
            }
            if (panel._resizeInterval) {
              clearInterval(panel._resizeInterval);
              panel._resizeInterval = null;
            }
          };

          // Delegate clicks inside this carousel
          carousel.addEventListener('click', (e) => {
            const target = e.target.closest('.iiif-info-btn, .iiif-info-close');
            if (!target || !carousel.contains(target)) return;
            e.preventDefault();
            if (target.classList.contains('iiif-info-btn')) onClickInfo(target);
            if (target.classList.contains('iiif-info-close')) onClickClose(target);
          });

          // Close on outside click
          document.addEventListener('click', (e) => {
            const openPanel = carousel.querySelector('.iiif-info-panel.open');
            if (!openPanel) return;
            if (!openPanel.contains(e.target) && !e.target.closest('.iiif-info-btn')) {
              openPanel.classList.remove('open');
              pausedByInfo = false;
              resume();
            }
          });

          // Close on Escape
          document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
              const openPanel = carousel.querySelector('.iiif-info-panel.open');
              if (openPanel) {
                openPanel.classList.remove('open');
                pausedByInfo = false;
                resume();
              }
            }
          });
        }
      });
    }
  };

})(Drupal, drupalSettings);
