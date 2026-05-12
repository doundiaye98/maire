/*
 * dashboard-charts.js — initialise tous les canvas <canvas data-chart="..."> trouvés dans la page.
 *
 * Conventions HTML :
 *   <canvas data-chart="line" data-payload='{"labels":[...],"data":[...]}'></canvas>
 *   <canvas data-chart="bar"  data-payload='{...}' data-label="Téléchargements"></canvas>
 *   <canvas data-chart="bar-h" data-payload='{...}'></canvas>  (barres horizontales)
 *   <canvas data-chart="doughnut" data-payload='{"labels":[...],"data":[...],"colors":[...]}'></canvas>
 *
 * Le payload est échappé HTML par le serveur (htmlspecialchars), donc JSON.parse direct.
 * Couleur primaire : vert profond #0c4a3e (cohérent avec le branding municipal).
 */

(function () {
    'use strict';

    function getCssVar(name, fallback) {
        try {
            const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return v || fallback;
        } catch (_) {
            return fallback;
        }
    }

    function readPayload(canvas) {
        const raw = canvas.getAttribute('data-payload');
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            console.warn('Payload JSON invalide pour', canvas, e);
            return null;
        }
    }

    function buildLine(canvas, payload) {
        const color = canvas.getAttribute('data-color') || '#0c4a3e';
        return {
            type: 'line',
            data: {
                labels: payload.labels || [],
                datasets: [{
                    label: canvas.getAttribute('data-label') || '',
                    data: payload.data || [],
                    fill: true,
                    backgroundColor: color + '22',
                    borderColor: color,
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: color,
                    pointHoverRadius: 5,
                }]
            },
            options: commonOptions(canvas, { showLegend: false })
        };
    }

    function buildBar(canvas, payload, horizontal) {
        const color = canvas.getAttribute('data-color') || '#0c4a3e';
        return {
            type: 'bar',
            data: {
                labels: payload.labels || [],
                datasets: [{
                    label: canvas.getAttribute('data-label') || '',
                    data: payload.data || [],
                    backgroundColor: color + 'CC',
                    borderColor: color,
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: Object.assign(commonOptions(canvas, { showLegend: false }), {
                indexAxis: horizontal ? 'y' : 'x',
            })
        };
    }

    function buildDoughnut(canvas, payload) {
        const colors = payload.colors && payload.colors.length
            ? payload.colors
            : ['#0c4a3e', '#f59e0b', '#0ea5e9', '#dc2626', '#7c3aed', '#16a34a', '#94a3b8'];
        return {
            type: 'doughnut',
            data: {
                labels: payload.labels || [],
                datasets: [{
                    data: payload.data || [],
                    backgroundColor: colors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                }]
            },
            options: Object.assign(commonOptions(canvas, { showLegend: true }), {
                cutout: '62%',
            })
        };
    }

    function commonOptions(canvas, opts) {
        opts = opts || {};
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: opts.showLegend !== false,
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 12, font: { size: 11 } }
                },
                tooltip: {
                    backgroundColor: '#0c4a3e',
                    padding: 10,
                    cornerRadius: 6,
                    titleFont: { weight: '600' }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: {
                    beginAtZero: true,
                    grid: { color: '#e2e8f0', drawBorder: false },
                    ticks: { font: { size: 11 }, precision: 0 }
                }
            }
        };
    }

    function initAll() {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js non chargé.');
            return;
        }
        Chart.defaults.font.family = 'Plus Jakarta Sans, system-ui, sans-serif';
        Chart.defaults.color = getCssVar('--maire-text', '#1f2937');

        const canvases = document.querySelectorAll('canvas[data-chart]');
        canvases.forEach(function (canvas) {
            if (canvas.dataset.chartInited === '1') return;
            const payload = readPayload(canvas);
            if (!payload) return;
            const type = canvas.getAttribute('data-chart') || 'line';
            let config = null;
            switch (type) {
                case 'bar':       config = buildBar(canvas, payload, false); break;
                case 'bar-h':     config = buildBar(canvas, payload, true); break;
                case 'doughnut':  config = buildDoughnut(canvas, payload); break;
                case 'line':
                default:          config = buildLine(canvas, payload); break;
            }
            if (config) {
                try {
                    new Chart(canvas, config);
                    canvas.dataset.chartInited = '1';
                } catch (e) {
                    console.warn('Erreur Chart.js', e);
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
