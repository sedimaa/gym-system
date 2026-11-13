import Chart from 'chart.js/auto';

/*
 | Renders every <canvas data-chart='{...Chart.js config...}'> on the page.
 | Bundled locally by Vite, so charts work offline.
 */
const peso = (v) => '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.color = '#6b7280';
Chart.defaults.plugins.legend.labels.boxWidth = 12;

export function renderCharts(root = document) {
    root.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const config = JSON.parse(canvas.dataset.chart);

        if (config.options?.money) {
            delete config.options.money;
            config.options.scales ??= {};
            config.options.scales.y ??= {};
            config.options.scales.y.ticks = { callback: peso };
            config.options.plugins ??= {};
            config.options.plugins.tooltip = { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${peso(ctx.parsed.y)}` } };
        }

        new Chart(canvas, { ...config, options: { responsive: true, maintainAspectRatio: false, ...config.options } });
    });
}

document.addEventListener('DOMContentLoaded', () => renderCharts());
