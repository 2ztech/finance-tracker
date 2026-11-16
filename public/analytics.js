/**
 * Analytics page: Chart.js renderers + layout edit interactions.
 * Reads per-widget chart data from canvas[data-chart] and persists layout
 * changes through the JSON API.
 */
(function () {
    'use strict';

    var COLORS = {
        income: '#159b7e',
        expense: '#e34f52',
        accent: '#3155c8',
        neutral: '#94a3b8',
        other: '#cbd5e1',
        warning: '#d98a22',
        purple: '#7c63d9',
        grid: 'rgba(148,163,184,.18)',
        text: '#64748b'
    };

    var PALETTE = ['#3155c8', '#159b7e', '#e34f52', '#d98a22', '#7c63d9', '#4f8df7', '#2dbe9b', '#f05a5a', '#84cc16', '#ec4899'];

    function money(v) {
        return 'RM ' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function pct(v) {
        return Number(v).toFixed(1) + '%';
    }

    function baseTooltip(fmt) {
        return {
            callbacks: {
                label: function (ctx) {
                    var label = ctx.dataset.label || ctx.label || '';
                    return label + ': ' + fmt(ctx.raw);
                }
            }
        };
    }

    function xyOptions(fmt, horizontal) {
        var opts = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: baseTooltip(fmt) },
            scales: {
                x: { grid: { display: false }, ticks: { color: COLORS.text, maxRotation: 45 } },
                y: { beginAtZero: true, grid: { color: COLORS.grid }, ticks: { color: COLORS.text, callback: fmt } }
            }
        };
        if (horizontal) {
            opts.indexAxis = 'y';
            opts.scales = {
                x: { beginAtZero: true, grid: { color: COLORS.grid }, ticks: { color: COLORS.text, callback: fmt } },
                y: { grid: { display: false }, ticks: { color: COLORS.text } }
            };
        }
        return opts;
    }

    function doughnutOptions(fmt, cutout) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            cutout: cutout || '70%',
            plugins: { legend: { display: false }, tooltip: baseTooltip(fmt) }
        };
    }

    function chartColors(items, fallback) {
        return items.map(function (item, i) {
            return item.color_hex || PALETTE[i % PALETTE.length] || fallback;
        });
    }

    var RENDERERS = {
        income_expense: function (cfg, data) {
            var type = cfg.chart === 'line' ? 'line' : 'bar';
            return {
                type: type,
                data: {
                    labels: data.display_labels,
                    datasets: [
                        { label: 'Income', data: data.income, backgroundColor: COLORS.income, borderColor: COLORS.income, borderRadius: 4, maxBarThickness: 18, fill: type === 'line' ? false : undefined, tension: 0.35 },
                        { label: 'Expenses', data: data.expenses, backgroundColor: COLORS.expense, borderColor: COLORS.expense, borderRadius: 4, maxBarThickness: 18, fill: type === 'line' ? false : undefined, tension: 0.35 }
                    ]
                },
                options: xyOptions(money, false)
            };
        },

        spending_category: function (cfg, data) {
            var items = data.items.slice();
            if (data.other_total > 0.005) {
                items.push({ name: 'Other', color_hex: COLORS.other, total: data.other_total });
            }
            var type = cfg.chart === 'bar' ? 'bar' : 'doughnut';
            if (type === 'doughnut') {
                return {
                    type: 'doughnut',
                    data: {
                        labels: items.map(function (i) { return i.name; }),
                        datasets: [{ data: items.map(function (i) { return i.total; }), backgroundColor: chartColors(items, COLORS.accent), borderWidth: 0, hoverOffset: 3 }]
                    },
                    options: doughnutOptions(money, '70%')
                };
            }
            return {
                type: 'bar',
                data: {
                    labels: items.map(function (i) { return i.name; }),
                    datasets: [{ label: 'Spending', data: items.map(function (i) { return i.total; }), backgroundColor: chartColors(items, COLORS.accent), borderRadius: 4, maxBarThickness: 18 }]
                },
                options: xyOptions(money, true)
            };
        },

        spending_institution: function (cfg, data) {
            return {
                type: 'bar',
                data: {
                    labels: data.items.map(function (i) { return i.name; }),
                    datasets: [{ label: 'Spending', data: data.items.map(function (i) { return i.total; }), backgroundColor: chartColors(data.items, COLORS.accent), borderRadius: 4, maxBarThickness: 18 }]
                },
                options: xyOptions(money, true)
            };
        },

        top_merchants: function (cfg, data) {
            var items = data.items.slice();
            if (data.other_total > 0.005) {
                items.push({ name: 'Other', total: data.other_total });
            }
            return {
                type: 'bar',
                data: {
                    labels: items.map(function (i) { return i.name; }),
                    datasets: [{ label: 'Spending', data: items.map(function (i) { return i.total; }), backgroundColor: items.map(function (i, idx) { return i.color_hex || PALETTE[idx % PALETTE.length]; }), borderRadius: 4, maxBarThickness: 18 }]
                },
                options: xyOptions(money, true)
            };
        },

        credit_utilization: function (cfg, data) {
            return {
                type: 'bar',
                data: {
                    labels: data.items.map(function (i) { return i.account_name; }),
                    datasets: [
                        { label: 'Outstanding', data: data.items.map(function (i) { return i.outstanding; }), backgroundColor: COLORS.expense, borderRadius: 4, maxBarThickness: 18, stack: 'u' },
                        { label: 'Available', data: data.items.map(function (i) { return i.available; }), backgroundColor: COLORS.other, borderRadius: 4, maxBarThickness: 18, stack: 'u' }
                    ]
                },
                options: Object.assign(xyOptions(money, true), { scales: { x: { stacked: true, beginAtZero: true, grid: { color: COLORS.grid }, ticks: { color: COLORS.text, callback: money } }, y: { stacked: true, grid: { display: false }, ticks: { color: COLORS.text } } } })
            };
        },

        net_worth: function (cfg, data) {
            return {
                type: 'line',
                data: {
                    labels: data.display_labels,
                    datasets: [{ label: 'Net worth', data: data.values, borderColor: COLORS.purple, backgroundColor: 'rgba(124,99,217,.12)', fill: true, tension: 0.35, pointRadius: 3, pointBackgroundColor: COLORS.purple }]
                },
                options: xyOptions(money, false)
            };
        },

        recurring_oneoff: function (cfg, data) {
            var type = cfg.chart === 'bar' ? 'bar' : 'doughnut';
            var labels = ['Recurring', 'One-off'];
            var values = [data.recurring, data.one_off];
            var colors = [COLORS.accent, COLORS.neutral];
            if (type === 'doughnut') {
                return {
                    type: 'doughnut',
                    data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0, hoverOffset: 3 }] },
                    options: doughnutOptions(money, '70%')
                };
            }
            return {
                type: 'bar',
                data: { labels: labels, datasets: [{ label: 'Spending', data: values, backgroundColor: colors, borderRadius: 4, maxBarThickness: 18 }] },
                options: xyOptions(money, false)
            };
        },

        savings_rate: function (cfg, data) {
            return {
                type: 'line',
                data: {
                    labels: data.display_labels,
                    datasets: [{ label: 'Savings rate', data: data.rate_pct, borderColor: COLORS.income, backgroundColor: 'rgba(21,155,126,.1)', fill: true, tension: 0.35, spanGaps: false, pointRadius: 3, pointBackgroundColor: COLORS.income }]
                },
                options: xyOptions(function (v) { return Number(v).toFixed(0) + '%'; }, false)
            };
        }
    };

    function initCharts() {
        if (typeof Chart === 'undefined') return;
        document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
            var payload;
            try {
                payload = JSON.parse(canvas.getAttribute('data-chart'));
            } catch (e) {
                return;
            }
            var renderer = RENDERERS[payload.key];
            if (!renderer) return;
            var cfg = renderer(payload.config || {}, payload.data || {});
            new Chart(canvas.getContext('2d'), cfg);
        });
    }

    // --- Layout editing -------------------------------------------------

    var state = window.ANALYTICS_STATE || null;

    function persist(layout) {
        return fetch('/api/analytics/layout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrf },
            body: JSON.stringify({ layout: layout })
        }).then(function (res) {
            if (!res.ok) {
                return res.json().then(function (body) {
                    throw new Error(body.error && body.error.message ? body.error.message : 'Could not save the layout.');
                });
            }
            return res.json();
        }).then(function (body) {
            state.layout = body.layout;
            return body.layout;
        }).then(function () {
            window.location.href = state.editUrl;
        }).catch(function (err) {
            if (typeof window.appNotice === 'function') {
                window.appNotice(err.message || 'Could not save the layout.');
            }
        });
    }

    function widgetIndex(id) {
        return state.layout.widgets.findIndex(function (w) { return w.id === id; });
    }

    function newWidgetId(key) {
        var base = 'w_' + key.replace(/[^a-z0-9_]/gi, '') + '_' + Math.random().toString(36).slice(2, 8);
        var id = base;
        var n = 1;
        while (widgetIndex(id) !== -1) {
            id = base + '_' + n;
            n++;
        }
        return id;
    }

    function handleAction(action, widgetEl) {
        var id = widgetEl.getAttribute('data-widget-id');
        var layout = JSON.parse(JSON.stringify(state.layout));
        var i = widgetIndex(id);
        if (i === -1) return;

        if (action === 'remove') {
            layout.widgets.splice(i, 1);
        } else if (action === 'resize') {
            var order = ['half', 'wide', 'full'];
            var current = layout.widgets[i].size;
            layout.widgets[i].size = order[(order.indexOf(current) + 1) % order.length];
        }
        persist(layout);
    }

    function moveWidget(widgetEl, dir) {
        var id = widgetEl.getAttribute('data-widget-id');
        var layout = JSON.parse(JSON.stringify(state.layout));
        var i = widgetIndex(id);
        var j = i + dir;
        if (i === -1 || j < 0 || j >= layout.widgets.length) return;
        var item = layout.widgets.splice(i, 1)[0];
        layout.widgets.splice(j, 0, item);
        persist(layout);
    }

    function widgetAlreadyAdded(key) {
        return state.layout.widgets.some(function (w) { return w.key === key; });
    }

    function syncAddOptions() {
        if (!state) return;
        document.querySelectorAll('.analytics-add-option').forEach(function (btn) {
            var used = widgetAlreadyAdded(btn.getAttribute('data-key'));
            btn.disabled = used;
            btn.classList.toggle('is-used', used);
            btn.setAttribute('aria-disabled', used ? 'true' : 'false');
        });
    }

    function addWidget(key) {
        var def = (state.catalog || []).find(function (w) { return w.key === key; });
        if (!def || widgetAlreadyAdded(key)) return;
        if (state.layout.widgets.length >= 12) {
            if (typeof window.appNotice === 'function') {
                window.appNotice('A layout can contain at most 12 widgets.');
            }
            return;
        }
        var layout = JSON.parse(JSON.stringify(state.layout));
        layout.widgets.push({
            id: newWidgetId(key),
            key: key,
            size: def.default_size || 'half',
            config: def.default_config || {}
        });
        persist(layout);
    }

    var configModalKey = null;

    function openConfigModal(widgetEl) {
        var key = widgetEl.getAttribute('data-widget-key');
        var id = widgetEl.getAttribute('data-widget-id');
        var def = (state.catalog || []).find(function (w) { return w.key === key; });
        if (!def || !def.config_fields || def.config_fields.length === 0) return;
        var idx = widgetIndex(id);
        if (idx === -1) return;

        configModalKey = id;
        var fields = document.getElementById('analyticsConfigFields');
        fields.innerHTML = '';
        def.config_fields.forEach(function (field) {
            var wrap = document.createElement('div');
            var label = document.createElement('label');
            label.className = 'mb-1 block text-xs font-medium';
            label.style.color = 'var(--text-secondary)';
            label.textContent = field.label;
            var select = document.createElement('select');
            select.className = 'w-full rounded-lg border px-3 py-2 text-sm';
            select.style.background = 'var(--bg-alt)';
            select.style.borderColor = 'var(--border)';
            select.style.color = 'var(--text)';
            select.dataset.fieldName = field.name;
            field.options.forEach(function (opt) {
                var o = document.createElement('option');
                o.value = String(opt.value);
                o.textContent = opt.label;
                var current = state.layout.widgets[idx].config[field.name];
                if (current === undefined) current = field.default;
                if (String(current) === String(opt.value)) o.selected = true;
                select.appendChild(o);
            });
            wrap.appendChild(label);
            wrap.appendChild(select);
            fields.appendChild(wrap);
        });
        openModal('analyticsConfigModal');
    }

    function saveConfigModal() {
        if (!configModalKey) return;
        var layout = JSON.parse(JSON.stringify(state.layout));
        var i = widgetIndex(configModalKey);
        if (i === -1) return;
        document.querySelectorAll('#analyticsConfigFields select').forEach(function (select) {
            var name = select.dataset.fieldName;
            var raw = select.value;
            layout.widgets[i].config = layout.widgets[i].config || {};
            if (name === 'top') {
                layout.widgets[i].config[name] = parseInt(raw, 10);
            } else {
                layout.widgets[i].config[name] = raw;
            }
        });
        closeModal('analyticsConfigModal');
        configModalKey = null;
        persist(layout);
    }

    function openModal(id) {
        var m = document.getElementById(id);
        if (m) {
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
    }

    function closeModal(id) {
        var m = document.getElementById(id);
        if (m) {
            m.classList.add('hidden');
            m.classList.remove('flex');
        }
    }

    function resetLayout() {
        fetch('/api/analytics/layout/reset', {
            method: 'POST',
            headers: { 'X-CSRF-Token': state.csrf }
        }).then(function (res) {
            if (!res.ok) throw new Error('Could not reset the layout.');
            return res.json();
        }).then(function (body) {
            state.layout = body.layout;
            window.location.href = state.editUrl;
        }).catch(function (err) {
            if (typeof window.appNotice === 'function') {
                window.appNotice(err.message || 'Could not reset the layout.');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCharts();

        if (!state) return;

        syncAddOptions();

        var addBtn = document.getElementById('analyticsAddWidget');
        var addTile = document.getElementById('analyticsAddWidgetTile');
        var resetBtn = document.getElementById('analyticsResetLayout');
        var configSave = document.getElementById('analyticsConfigSave');

        function openAddModal() {
            syncAddOptions();
            openModal('analyticsAddModal');
        }

        if (addBtn) addBtn.addEventListener('click', openAddModal);
        if (addTile) addTile.addEventListener('click', openAddModal);

        document.querySelectorAll('.analytics-add-option').forEach(function (btn) {
            btn.addEventListener('click', function () {
                closeModal('analyticsAddModal');
                addWidget(btn.getAttribute('data-key'));
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = btn.closest('[id]');
                if (modal) closeModal(modal.id);
            });
        });

        ['analyticsAddModal', 'analyticsConfigModal'].forEach(function (id) {
            var m = document.getElementById(id);
            if (m) {
                m.addEventListener('click', function (e) {
                    if (e.target === m) closeModal(id);
                });
            }
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (typeof window.openAppDialog === 'function') {
                    openAppDialog({
                        title: 'Reset layout',
                        message: 'Restore the default analytics widgets? Your current layout will be replaced.',
                        confirmLabel: 'Reset',
                        danger: true
                    }).then(function (accepted) {
                        if (accepted) resetLayout();
                    });
                } else {
                    resetLayout();
                }
            });
        }

        if (configSave) {
            configSave.addEventListener('click', saveConfigModal);
        }

        document.querySelectorAll('.analytics-widget .analytics-ctl').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = btn.getAttribute('data-action');
                var widgetEl = btn.closest('.analytics-widget');
                if (!widgetEl) return;
                if (action === 'move') {
                    moveWidget(widgetEl, parseInt(btn.getAttribute('data-dir'), 10));
                } else if (action === 'edit') {
                    openConfigModal(widgetEl);
                } else {
                    handleAction(action, widgetEl);
                }
            });
        });
    });
})();
