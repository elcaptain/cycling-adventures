/**
 * Lightbox
 *
 * Opens any <img data-lightbox="…"> on the page in a fullscreen viewer.
 * All lightbox images on the page form one sequence (in document order).
 *
 * Controls:
 *   Desktop: click image to open, ←/→ to navigate, Esc or ✕ to close
 *   Mobile:  swipe left/right to navigate, swipe down or tap backdrop to close
 *
 * Image attributes (see site/snippets/lightbox-attrs.php):
 *   data-lightbox          large image URL
 *   data-lightbox-srcset   optional srcset for the large image
 *   data-lightbox-caption  optional caption
 */

(function() {
    'use strict';

    var SWIPE_THRESHOLD = 50;   // px before a horizontal swipe navigates
    var CLOSE_THRESHOLD = 90;   // px before a downward swipe closes

    var triggers = Array.prototype.slice.call(document.querySelectorAll('img[data-lightbox]'));
    if (triggers.length === 0) return;

    var items = triggers.map(function(img) {
        return {
            src: img.dataset.lightbox,
            srcset: img.dataset.lightboxSrcset || '',
            caption: img.dataset.lightboxCaption || '',
            alt: img.getAttribute('alt') || ''
        };
    });

    var current = 0;
    var lastFocus = null;
    var root, stage, image, caption, counter, prevBtn, nextBtn, closeBtn;

    function build() {
        root = document.createElement('div');
        root.className = 'lightbox';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-label', 'Image viewer');
        root.hidden = true;
        root.innerHTML =
            '<div class="lightbox-stage">' +
                '<img class="lightbox-image" alt="" draggable="false">' +
            '</div>' +
            '<div class="lightbox-bar">' +
                '<span class="lightbox-counter" aria-live="polite"></span>' +
                '<button type="button" class="lightbox-btn lightbox-close" aria-label="Close">' +
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>' +
                '</button>' +
            '</div>' +
            '<button type="button" class="lightbox-btn lightbox-prev" aria-label="Previous image">' +
                '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>' +
            '</button>' +
            '<button type="button" class="lightbox-btn lightbox-next" aria-label="Next image">' +
                '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>' +
            '</button>' +
            '<p class="lightbox-caption"></p>';

        document.body.appendChild(root);

        stage    = root.querySelector('.lightbox-stage');
        image    = root.querySelector('.lightbox-image');
        caption  = root.querySelector('.lightbox-caption');
        counter  = root.querySelector('.lightbox-counter');
        prevBtn  = root.querySelector('.lightbox-prev');
        nextBtn  = root.querySelector('.lightbox-next');
        closeBtn = root.querySelector('.lightbox-close');

        prevBtn.addEventListener('click', function() { go(-1); });
        nextBtn.addEventListener('click', function() { go(1); });
        closeBtn.addEventListener('click', close);

        // Tap/click on the empty area around the image closes
        stage.addEventListener('click', function(e) {
            if (e.target === stage) close();
        });

        image.addEventListener('load', function() { root.classList.remove('is-loading'); });

        bindSwipe();
    }

    function show(index) {
        current = (index + items.length) % items.length;
        var item = items[current];

        root.classList.add('is-loading');
        image.removeAttribute('srcset');
        if (item.srcset) {
            image.srcset = item.srcset;
            image.sizes = '100vw';
        }
        image.src = item.src;
        image.alt = item.alt;

        caption.textContent = item.caption;
        caption.hidden = !item.caption;
        counter.textContent = (current + 1) + ' / ' + items.length;

        var single = items.length < 2;
        prevBtn.hidden = single;
        nextBtn.hidden = single;

        preload(current + 1);
        preload(current - 1);
    }

    function preload(index) {
        var item = items[(index + items.length) % items.length];
        var img = new Image();
        if (item.srcset) {
            img.sizes = '100vw';
            img.srcset = item.srcset;
        }
        img.src = item.src;
    }

    function go(step) {
        if (items.length < 2) return;
        show(current + step);
    }

    function open(index) {
        if (!root) build();
        lastFocus = document.activeElement;
        show(index);
        root.hidden = false;
        document.documentElement.classList.add('lightbox-open');
        document.addEventListener('keydown', onKey);
        closeBtn.focus({ preventScroll: true });
    }

    function close() {
        root.hidden = true;
        resetDrag();
        document.documentElement.classList.remove('lightbox-open');
        document.removeEventListener('keydown', onKey);
        if (lastFocus) lastFocus.focus({ preventScroll: true });
    }

    function onKey(e) {
        if (e.key === 'Escape') { close(); }
        else if (e.key === 'ArrowLeft') { go(-1); }
        else if (e.key === 'ArrowRight') { go(1); }
        else if (e.key === 'Tab') {
            // keep focus inside the dialog
            var focusable = Array.prototype.filter.call(
                root.querySelectorAll('button'),
                function(b) { return !b.hidden; }
            );
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    }

    // --- Touch: swipe left/right to navigate, swipe down to close ---

    var startX = 0, startY = 0, dx = 0, dy = 0, axis = null, tracking = false;

    function bindSwipe() {
        stage.addEventListener('touchstart', function(e) {
            if (e.touches.length !== 1) { tracking = false; return; }
            tracking = true;
            axis = null;
            dx = dy = 0;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            image.style.transition = 'none';
        }, { passive: true });

        stage.addEventListener('touchmove', function(e) {
            if (!tracking || e.touches.length !== 1) return;
            dx = e.touches[0].clientX - startX;
            dy = e.touches[0].clientY - startY;

            // lock to the dominant direction once the finger has moved a bit
            if (!axis && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) {
                axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
            }

            if (axis === 'x') {
                image.style.transform = 'translateX(' + dx + 'px)';
            } else if (axis === 'y' && dy > 0) {
                image.style.transform = 'translateY(' + dy + 'px) scale(' + Math.max(0.85, 1 - dy / 1500) + ')';
                root.style.setProperty('--lightbox-backdrop', Math.max(0.3, 1 - dy / 600));
            }
            e.preventDefault();
        }, { passive: false });

        stage.addEventListener('touchend', function() {
            if (!tracking) return;
            tracking = false;

            if (axis === 'x' && Math.abs(dx) > SWIPE_THRESHOLD && items.length > 1) {
                resetDrag();
                go(dx < 0 ? 1 : -1);
            } else if (axis === 'y' && dy > CLOSE_THRESHOLD) {
                close();
            } else {
                image.style.transition = '';
                resetDrag();
            }
        });

        stage.addEventListener('touchcancel', function() {
            tracking = false;
            resetDrag();
        });
    }

    function resetDrag() {
        image.style.transform = '';
        root.style.removeProperty('--lightbox-backdrop');
        requestAnimationFrame(function() { image.style.transition = ''; });
    }

    // --- Wire up the page images ---

    triggers.forEach(function(img, index) {
        img.classList.add('lightbox-trigger');
        img.setAttribute('tabindex', '0');
        img.setAttribute('role', 'button');
        img.setAttribute('aria-label', 'View larger' + (img.alt ? ': ' + img.alt : ''));

        img.addEventListener('click', function() { open(index); });
        img.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                open(index);
            }
        });
    });
})();
