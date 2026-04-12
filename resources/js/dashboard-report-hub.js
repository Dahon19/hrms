import Chart from 'chart.js/auto';
import { onReady } from './utils';

function buildColors(length) {
    const palette = [
        '#2563eb',
        '#0ea5e9',
        '#14b8a6',
        '#22c55e',
        '#f59e0b',
        '#ef4444',
        '#7c3aed',
        '#64748b',
    ];

    return Array.from({ length }, (_, index) => palette[index % palette.length]);
}

function renderChart(chart) {
    const canvas = document.getElementById(`dashboard-chart-${chart.id}`);
    if (!canvas) {
        return;
    }

    const frame = canvas.closest('.dashboard-chart-frame');
    const loadingEl = frame?.querySelector('[data-dashboard-chart-loading]');

    if (canvas._chartInstance) {
        canvas._chartInstance.destroy();
    }

    const chartType = chart.type || 'line';
    const labels = chart.labels || [];
    const colors = buildColors(labels.length || 8);
    const customDatasets = Array.isArray(chart.datasets) ? chart.datasets : [];
    const hasCustomDatasets = customDatasets.length > 0;
    const datasets = hasCustomDatasets
        ? customDatasets.map((dataset, index) => {
            const fallbackColor = colors[index % colors.length];
            const isLine = chartType === 'line';
            const isBar = chartType === 'bar';

            return {
                label: dataset.label || `Series ${index + 1}`,
                data: Array.isArray(dataset.data) ? dataset.data : [],
                borderColor: dataset.borderColor || fallbackColor,
                backgroundColor: dataset.backgroundColor || (isLine ? 'rgba(37, 99, 235, 0.12)' : fallbackColor),
                borderWidth: dataset.borderWidth ?? (isLine ? 2 : 0),
                fill: dataset.fill ?? false,
                tension: dataset.tension ?? (isLine ? 0.32 : 0),
                borderRadius: dataset.borderRadius ?? (isBar ? 8 : 0),
                maxBarThickness: dataset.maxBarThickness ?? (isBar ? (window.matchMedia('(max-width: 767.98px)').matches ? 20 : 28) : undefined),
                pointRadius: dataset.pointRadius ?? (isLine ? 0 : undefined),
                pointHitRadius: dataset.pointHitRadius ?? (isLine ? 10 : undefined),
                pointHoverRadius: dataset.pointHoverRadius ?? (isLine ? 3 : undefined),
            };
        })
        : [{
            data: chart.values || [],
            borderColor: chartType === 'line' ? '#2563eb' : colors,
            backgroundColor: chartType === 'line' ? 'rgba(37, 99, 235, 0.12)' : colors,
            borderWidth: chartType === 'line' ? 2 : 0,
            fill: chartType === 'line',
            tension: chartType === 'line' ? 0.32 : 0,
            borderRadius: chartType === 'bar' ? 8 : 0,
            maxBarThickness: chartType === 'bar' ? (window.matchMedia('(max-width: 767.98px)').matches ? 20 : 28) : undefined,
            pointRadius: chartType === 'line' ? 0 : undefined,
            pointHitRadius: chartType === 'line' ? 10 : undefined,
            pointHoverRadius: chartType === 'line' ? 3 : undefined,
        }];

    const isCompactViewport = window.matchMedia('(max-width: 767.98px)').matches;
    const isDarkMode = document.body?.classList?.contains('dark-mode');
    const rootStyles = window.getComputedStyle(document.documentElement);
    const mutedColor = (rootStyles.getPropertyValue('--hrms-muted') || '').trim() || (isDarkMode ? '#b0b0b0' : '#64748b');
    const gridLineColor = isDarkMode ? 'rgba(176, 176, 176, 0.16)' : 'rgba(148, 163, 184, 0.12)';
    const isTrendChart = chartType === 'line';
    const stacked = Boolean(chart.stacked);
    const indexAxis = chartType === 'bar' && chart.index_axis === 'y' ? 'y' : 'x';
    const sharedOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: isCompactViewport ? 0 : 450,
        },
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                display: typeof chart.legend_display === 'boolean'
                    ? chart.legend_display
                    : (chartType !== 'line' || datasets.length > 1),
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    boxWidth: 8,
                    color: mutedColor,
                    padding: isCompactViewport ? 12 : 16,
                    font: {
                        size: isCompactViewport ? 10 : 11,
                    },
                },
            },
            tooltip: {
                padding: 10,
                displayColors: chartType !== 'line' || datasets.length > 1,
                titleFont: {
                    size: isCompactViewport ? 11 : 12,
                },
                bodyFont: {
                    size: isCompactViewport ? 11 : 12,
                },
            },
        },
    };

    const config = {
        type: chartType,
        data: {
            labels,
            datasets,
        },
        options: sharedOptions,
    };

    if (chartType === 'line' || chartType === 'bar') {
        if (chartType === 'bar') {
            config.options.indexAxis = indexAxis;
        }

        config.options.scales = indexAxis === 'y'
            ? {
                x: {
                    stacked,
                    beginAtZero: true,
                    border: {
                        display: false,
                    },
                    grid: {
                        color: gridLineColor,
                        drawTicks: false,
                    },
                    ticks: {
                        precision: 0,
                        maxTicksLimit: isCompactViewport ? 4 : 6,
                        color: mutedColor,
                        padding: 6,
                        font: {
                            size: isCompactViewport ? 10 : 11,
                        },
                    },
                },
                y: {
                    stacked,
                    grid: { display: false },
                    ticks: {
                        autoSkip: false,
                        color: mutedColor,
                        font: {
                            size: isCompactViewport ? 10 : 11,
                        },
                    },
                },
            }
            : {
                x: {
                    stacked,
                    grid: { display: false },
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: isCompactViewport ? 4 : 7,
                        color: mutedColor,
                        font: {
                            size: isCompactViewport ? 10 : 11,
                        },
                    },
                },
                y: {
                    stacked,
                    beginAtZero: true,
                    border: {
                        display: false,
                    },
                    grid: {
                        color: gridLineColor,
                        drawTicks: false,
                    },
                    ticks: {
                        precision: 0,
                        maxTicksLimit: isCompactViewport ? 4 : 5,
                        color: mutedColor,
                        padding: 6,
                        font: {
                            size: isCompactViewport ? 10 : 11,
                        },
                    },
                },
            };

        if (isTrendChart) {
            config.options.plugins.legend.display = datasets.length > 1;
        }
    }

    if (chartType === 'doughnut') {
        config.options.cutout = '62%';
    }

    canvas._chartInstance = new Chart(canvas, config);
    if (loadingEl) {
        loadingEl.remove();
    }
}

function initDashboardCharts() {
    if (!document.body || document.body.dataset.page !== 'dashboard') {
        return;
    }

    const host = document.getElementById('dashboardCharts');
    if (!host) {
        return;
    }

    let charts = [];
    try {
        charts = JSON.parse(host.getAttribute('data-dashboard-charts') || '[]');
    } catch (error) {
        console.error('Failed to parse dashboard chart payload.', error);
        return;
    }

    charts.forEach(renderChart);
}

onReady(initDashboardCharts);
