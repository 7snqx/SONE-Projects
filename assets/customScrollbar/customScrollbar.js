/**
 * Custom Scrollbar Component - JavaScript
 * ========================================
 * Reużywalny custom scrollbar dla projektów SONE
 * 
 * WYMAGANIA:
 * - quickJS.js dla funkcji qs() lub zdefiniuj własną:
 *   const qs = (selector) => document.querySelector(selector);
 * 
 * UŻYCIE:
 * 1. Dodaj HTML w body:
 *    <div class="customScrollbarTrack" id="scrollbarTrack">
 *        <div class="customScrollbarThumb" id="scrollbarThumb"></div>
 *    </div>
 * 
 * 2. Zaimportuj ten skrypt po HTML:
 *    <script src="assets/customScrollbar/customScrollbar.js"></script>
 * 
 * OPCJE:
 * Możesz zainicjalizować z własnymi selektorami:
 *    initCustomScrollbar('#myTrack', '#myThumb');
 */

(function() {
    'use strict';

    // Fallback dla qs jeśli quickJS nie jest załadowany
    const qs = window.qs || ((selector) => document.querySelector(selector));

    let isDragging = false;
    let dragStartY = 0;
    let dragStartScrollTop = 0;
    let track, thumb;

    const html = document.documentElement;

    /**
     * Aktualizuje pozycję i rozmiar thumba
     */
    function updateThumb() {
        if (!track || !thumb) return;

        const scrollMax = html.scrollHeight - window.innerHeight;
        const scrollPercent = html.scrollTop / scrollMax || 0;
        const ratio = window.innerHeight / html.scrollHeight;
        const thumbHeight = Math.max(ratio * 100, 8); // minimum 8%

        if (scrollMax > 0) {
            track.style.display = 'block';
            thumb.style.height = thumbHeight + '%';
            thumb.style.top = (scrollPercent * (100 - thumbHeight)) + '%';
        } else {
            track.style.display = 'none';
        }
    }

    /**
     * Handler dla ruchu myszy podczas przeciągania
     */
    function onMouseMove(e) {
        if (!isDragging || !track || !thumb) return;

        const scrollMax = html.scrollHeight - window.innerHeight;
        if (scrollMax <= 0) return;

        const trackRect = track.getBoundingClientRect();
        const thumbRect = thumb.getBoundingClientRect();

        const trackHeight = trackRect.height;
        const thumbHeight = thumbRect.height;
        const availableSpace = trackHeight - thumbHeight;

        const deltaY = e.clientY - dragStartY;
        const scrollPerPixel = scrollMax / availableSpace;
        const newScrollTop = dragStartScrollTop + deltaY * scrollPerPixel;

        html.scrollTop = Math.max(0, Math.min(scrollMax, newScrollTop));
        updateThumb();
    }

    /**
     * Handler dla puszczenia przycisku myszy
     */
    function onMouseUp() {
        if (thumb) {
            thumb.style.cursor = 'grab';
            thumb.style.transform = 'scale(1)';
        }
        isDragging = false;
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
    }

    /**
     * Inicjalizuje custom scrollbar
     * @param {string} trackSelector - Selektor dla track (default: '#scrollbarTrack')
     * @param {string} thumbSelector - Selektor dla thumb (default: '#scrollbarThumb')
     */
    function initCustomScrollbar(trackSelector = '#scrollbarTrack', thumbSelector = '#scrollbarThumb') {
        track = qs(trackSelector);
        thumb = qs(thumbSelector);

        if (!track || !thumb) {
            console.warn('Custom Scrollbar: Nie znaleziono elementów track lub thumb');
            return;
        }

        // Event: przeciąganie thumba
        thumb.addEventListener('mousedown', (e) => {
            e.preventDefault();
            thumb.style.cursor = 'grabbing';
            thumb.style.transform = 'scale(0.95)';
            isDragging = true;
            dragStartY = e.clientY;
            dragStartScrollTop = html.scrollTop;
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        // Event: kliknięcie na track (skok do pozycji)
        track.addEventListener('mousedown', (e) => {
            if (e.target === thumb) return;

            const trackRect = track.getBoundingClientRect();
            const thumbRect = thumb.getBoundingClientRect();

            const trackHeight = trackRect.height;
            const thumbHeight = thumbRect.height;
            const availableSpace = trackHeight - thumbHeight;

            const scrollMax = html.scrollHeight - window.innerHeight;
            if (scrollMax <= 0) return;

            const clickY = e.clientY - trackRect.top;
            const clickPercent = Math.max(0, Math.min(1, clickY / availableSpace));

            html.scrollTop = clickPercent * scrollMax;
            updateThumb();
        });

        // Eventy: scroll i resize
        window.addEventListener('scroll', updateThumb, { passive: true });
        window.addEventListener('resize', updateThumb, { passive: true });

        // Inicjalna aktualizacja
        updateThumb();

        console.log('Custom Scrollbar: Zainicjalizowano');
    }

    // Auto-inicjalizacja po załadowaniu DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initCustomScrollbar());
    } else {
        // DOM już załadowany
        initCustomScrollbar();
    }

    // Eksportuj funkcje globalnie
    window.initCustomScrollbar = initCustomScrollbar;
    window.updateScrollbarThumb = updateThumb;

})();
