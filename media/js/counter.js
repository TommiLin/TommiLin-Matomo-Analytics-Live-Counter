/**
 * Matomo Analytics Live Counter
 *  - period switchers and the line/bars chart switch (no inline handlers: strict CSP friendly)
 *  - manual refresh button for administrators (asks Matomo right now, through com_ajax)
 */
(function () {
    'use strict';

    var WRAP = '.matomo-live-wrap';
    var STYLE_KEY = 'mmcChartStyle';

    /* ---------- period switchers ---------- */

    function applySwitch(select) {
        var wrap = select.closest(WRAP);
        var group = select.getAttribute('data-mmc-switch');

        if (!wrap || !group) {
            return;
        }

        wrap.querySelectorAll('[data-mmc-group="' + group + '"]').forEach(function (el) {
            el.hidden = el.getAttribute('data-mmc-key') !== select.value;
        });
    }

    /* ---------- chart style: line / bars ---------- */

    function storedStyle() {
        try {
            var value = window.localStorage.getItem(STYLE_KEY);

            return value === 'line' || value === 'bars' ? value : null;
        } catch (e) {
            return null;
        }
    }

    function setChartStyle(section, style) {
        section.setAttribute('data-mmc-chart-style', style);

        var button = section.querySelector('[data-mmc-chart-toggle]');

        if (button) {
            // The button switches to the other style, so it is labelled with that one
            var label = button.getAttribute(style === 'line' ? 'data-label-bars' : 'data-label-line');

            if (label) {
                button.setAttribute('title', label);
                button.setAttribute('aria-label', label);
            }
        }
    }

    function applyStoredStyle(root) {
        var style = storedStyle();

        if (style) {
            root.querySelectorAll('.matomo-chart-section').forEach(function (section) {
                setChartStyle(section, style);
            });
        }
    }

    /* ---------- events ---------- */

    document.addEventListener('change', function (event) {
        var select = event.target;

        if (select && select.matches && select.matches('select[data-mmc-switch]')) {
            applySwitch(select);
        }
    });

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!target || !target.closest) {
            return;
        }

        var toggle = target.closest('[data-mmc-chart-toggle]');

        if (toggle) {
            var section = toggle.closest('.matomo-chart-section');
            var next = section && section.getAttribute('data-mmc-chart-style') === 'line' ? 'bars' : 'line';

            // One choice for every counter on the page, remembered in this browser
            document.querySelectorAll('.matomo-chart-section').forEach(function (s) {
                setChartStyle(s, next);
            });

            try {
                window.localStorage.setItem(STYLE_KEY, next);
            } catch (e) { /* storage may be blocked: the switch still works for this page view */ }

            return;
        }

        var refreshButton = target.closest('[data-mmc-refresh]');

        if (refreshButton) {
            var wrap = refreshButton.closest(WRAP);

            if (wrap && !refreshButton.disabled) {
                refreshButton.classList.add('is-loading');
                refreshButton.disabled = true;
                refresh(wrap);
            }
        }
    });

    /* ---------- refresh through com_ajax ---------- */

    function refresh(wrap) {
        var url = wrap.getAttribute('data-ajax-url');

        if (!url || wrap.mmcBusy || !window.fetch) {
            resetRefreshButton(wrap);

            return;
        }

        wrap.mmcBusy = true;

        fetch(url + '&force=1', {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(function (response) {
                return response.ok ? response.json() : Promise.reject(new Error('HTTP ' + response.status));
            })
            .then(function (json) {
                var data = json && json.success ? json.data : null;

                if (!data || typeof data.html !== 'string' || data.html === '') {
                    return;
                }

                // Keep the periods the visitor selected
                var chosen = {};
                wrap.querySelectorAll('select[data-mmc-switch]').forEach(function (s) {
                    chosen[s.id] = s.value;
                });

                wrap.innerHTML = data.html;

                wrap.querySelectorAll('select[data-mmc-switch]').forEach(function (s) {
                    if (chosen[s.id] !== undefined) {
                        s.value = chosen[s.id];
                        applySwitch(s);
                    }
                });

                applyStoredStyle(wrap);
            })
            .catch(function () { /* keep the current numbers */ })
            .then(function () {
                wrap.mmcBusy = false;
                resetRefreshButton(wrap);
            });
    }

    function resetRefreshButton(wrap) {
        var button = wrap.querySelector('[data-mmc-refresh]');

        if (button) {
            button.classList.remove('is-loading');
            button.disabled = false;
        }
    }

    function init() {
        applyStoredStyle(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
