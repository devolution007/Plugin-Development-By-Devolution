(function () {
    'use strict';

    function $$(root, sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); }

    function initWidget(wrap) {
        var tabs = $$(wrap, '.ma-tab');
        var panelsBox = wrap.querySelector('.ma-panels');
        var monthSelect = wrap.querySelector('.ma-month-select');
        var filters = $$(wrap, '.ma-filter');
        var activeKey = null;

        /* ---------- Tabs ---------- */
        function selectTab(tab, focus) {
            tabs.forEach(function (t) {
                var on = t === tab;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
            });
            activeKey = tab.id;
            showActivePanel();
            if (focus) tab.focus();
        }

        function showActivePanel() {
            var activeTab = wrap.querySelector('.ma-tab.is-active');
            $$(wrap, '.ma-panel').forEach(function (p) {
                var on = !activeTab || activeTab.getAttribute('aria-controls') === p.id;
                p.hidden = !on;
                p.classList.toggle('is-active', on);
            });
            refreshCarousels();
        }

        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { selectTab(tab); });
            tab.addEventListener('keydown', function (e) {
                var next = null;
                if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
                if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
                if (next) { e.preventDefault(); selectTab(next, true); }
            });
        });

        /* ---------- Filters (client side) ---------- */
        function applyFilters() {
            var want = {};
            filters.forEach(function (f) { want[f.getAttribute('data-filter')] = f.value; });
            $$(wrap, '.ma-panel').forEach(function (panel) {
                var shown = 0;
                $$(panel, '.ma-card').forEach(function (card) {
                    var ok = (!want.campus || card.getAttribute('data-campus') === want.campus) &&
                             (!want.section || card.getAttribute('data-section') === want.section);
                    card.hidden = !ok;
                    if (ok) shown++;
                });
                var msg = panel.querySelector('.ma-nomatch');
                if (msg) {
                    msg.hidden = shown > 0;
                    msg.textContent = shown > 0 ? '' : MA_Config.noMatch;
                }
                var track = panel.querySelector('.ma-track');
                if (track) track.scrollLeft = 0;
            });
            refreshCarousels();
        }
        filters.forEach(function (f) { f.addEventListener('change', applyFilters); });

        /* ---------- Month (AJAX) ---------- */
        if (monthSelect) {
            monthSelect.addEventListener('change', function () {
                var body = new URLSearchParams();
                body.append('action', 'ma_load_month');
                body.append('month', monthSelect.value);
                body.append('groups', wrap.getAttribute('data-groups'));
                body.append('campus', wrap.getAttribute('data-campus') || '');
                body.append('section', wrap.getAttribute('data-section') || '');
                body.append('uid', wrap.id);

                wrap.classList.add('is-loading');
                fetch(MA_Config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.success) {
                            panelsBox.innerHTML = res.data.html;
                            showActivePanel();
                            applyFilters();
                            $$(panelsBox, '.ma-carousel').forEach(initCarousel);
                        }
                    })
                    .catch(function () {})
                    .then(function () { wrap.classList.remove('is-loading'); });
            });
        }

        /* ---------- Carousels ---------- */
        function refreshCarousels() {
            $$(wrap, '.ma-panel:not([hidden]) .ma-carousel').forEach(function (c) { c._ma && c._ma.update(); });
        }

        $$(wrap, '.ma-carousel').forEach(initCarousel);
        window.addEventListener('resize', refreshCarousels);
        showActivePanel();
    }

    function initCarousel(carousel) {
        var track = carousel.querySelector('.ma-track');
        var prev = carousel.querySelector('.ma-prev');
        var next = carousel.querySelector('.ma-next');
        var dotsBox = carousel.parentNode.querySelector('.ma-dots');
        var pages = 1;

        function maxScroll() { return Math.max(0, track.scrollWidth - track.clientWidth); }

        function update() {
            if (!track.clientWidth) return; // hidden panel; refreshed when shown
            var max = maxScroll();
            var overflow = max > 2;
            pages = overflow ? Math.ceil(track.scrollWidth / track.clientWidth) : 1;
            carousel.classList.toggle('is-static', !overflow);

            dotsBox.innerHTML = '';
            if (pages > 1) {
                for (var i = 0; i < pages; i++) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'ma-dot';
                    b.setAttribute('aria-label', 'Page ' + (i + 1));
                    b.addEventListener('click', goTo.bind(null, i));
                    dotsBox.appendChild(b);
                }
            }
            sync();
        }

        function goTo(i) {
            track.scrollTo({ left: pages > 1 ? i * maxScroll() / (pages - 1) : 0, behavior: 'smooth' });
        }

        function sync() {
            var max = maxScroll();
            var cur = max > 0 && pages > 1 ? Math.round(track.scrollLeft / max * (pages - 1)) : 0;
            Array.prototype.forEach.call(dotsBox.children, function (d, i) { d.classList.toggle('is-active', i === cur); });
            prev.disabled = track.scrollLeft <= 2;
            next.disabled = track.scrollLeft >= max - 2;
        }

        prev.addEventListener('click', function () { track.scrollBy({ left: -track.clientWidth, behavior: 'smooth' }); });
        next.addEventListener('click', function () { track.scrollBy({ left: track.clientWidth, behavior: 'smooth' }); });
        track.addEventListener('scroll', function () { window.requestAnimationFrame(sync); });
        track.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { e.preventDefault(); next.click(); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); prev.click(); }
        });

        carousel._ma = { update: update };
        update();
    }

    document.addEventListener('DOMContentLoaded', function () {
        $$(document, '.ma-tabs').forEach(initWidget);
    });
})();
