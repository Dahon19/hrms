import { $, onReady } from './utils';

function initAuditLogsTable() {
    if (!$ || !$.fn.DataTable) return;
    const $table = $('#auditLogsTable');
    if (!$table.length) return;
    if (!$.fn.DataTable.isDataTable($table[0])) return;
    const table = $table.DataTable();

    const $action = $('#auditFilterAction');
    const $user = $('#auditFilterUser');
    const $start = $('#auditFilterStart');
    const $end = $('#auditFilterEnd');

    const normalizeFilterValue = (value) => String(value || '')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();

    const getFilterState = () => ({
        action: normalizeFilterValue($action.val()),
        user: normalizeFilterValue($user.val()),
    });

    function parseRowDate(value) {
        if (!value) return null;
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function dateOnly(value) {
        if (!value) return null;
        const parsed = new Date(value + 'T00:00:00');
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    $.fn.dataTable.ext.search.push(function (settings, data) {
        if (settings.nTable !== $table[0]) return true;
        const rowDate = parseRowDate(data[0]);
        const startDate = dateOnly($start.val());
        const endDate = dateOnly($end.val());
        const filters = getFilterState();
        const rowUser = normalizeFilterValue(data[1]);
        const rowAction = normalizeFilterValue(data[2]);

        if (filters.action && rowAction !== filters.action) return false;
        if (filters.user && rowUser !== filters.user) return false;

        if (!rowDate) return true;
        const rowDateOnly = new Date(rowDate.getFullYear(), rowDate.getMonth(), rowDate.getDate());
        if (startDate && rowDateOnly < startDate) return false;
        if (endDate && rowDateOnly > endDate) return false;
        return true;
    });

    function applyFilters() {
        table.column(1).search('');
        table.column(2).search('');
        table.draw();
    }

    $action.on('change', applyFilters);
    $user.on('change', applyFilters);
    $start.on('change', applyFilters);
    $end.on('change', applyFilters);
}

function initAuditLogDetailsModal() {
    const modalEl = document.getElementById('auditLogDetailsModal');
    if (!modalEl) return;

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.view-audit-log');
        if (!trigger) return;

        const row = trigger.closest('tr');
        if (!row) return;

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value || '-';
        };

        setText('audit-log-details-time', row.dataset.time);
        setText('audit-log-details-user', row.dataset.user);
        setText('audit-log-details-action', row.dataset.action);
        setText('audit-log-details-model', row.dataset.model);
        setText('audit-log-details-record', row.dataset.record);
        setText('audit-log-details-summary', row.dataset.summary);
        setText('audit-log-details-ip', row.dataset.ip);

        const metadataEl = document.getElementById('audit-log-details-metadata');
        if (metadataEl) {
            const rawMetadata = row.dataset.metadata;
            if (!rawMetadata) {
                metadataEl.textContent = 'No additional metadata.';
            } else {
                try {
                    const parsed = JSON.parse(rawMetadata);
                    metadataEl.textContent = parsed && Object.keys(parsed).length
                        ? JSON.stringify(parsed, null, 2)
                        : 'No additional metadata.';
                } catch (error) {
                    metadataEl.textContent = rawMetadata;
                }
            }
        }

        if (window.hrmsShowModal) {
            window.hrmsShowModal(modalEl);
        } else if (window.jQuery) {
            window.jQuery(modalEl).modal('show');
        }
    });
}

function initAuditLogExports() {
    const readFilters = () => {
        const params = new URLSearchParams();
        const action = String(document.getElementById('auditFilterAction')?.value || '').trim();
        const user = String(document.getElementById('auditFilterUser')?.value || '').trim();
        const start = String(document.getElementById('auditFilterStart')?.value || '').trim();
        const end = String(document.getElementById('auditFilterEnd')?.value || '').trim();

        if (action) params.set('action', action);
        if (user) params.set('user', user);
        if (start) params.set('start', start);
        if (end) params.set('end', end);

        return params;
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.js-audit-print, .js-audit-export');
        if (!trigger) return;

        event.preventDefault();
        const baseUrl = trigger.dataset.printUrl || trigger.dataset.exportUrl || '';
        if (!baseUrl) return;

        const query = readFilters().toString();
        const targetUrl = query ? `${baseUrl}?${query}` : baseUrl;

        if (trigger.classList.contains('js-audit-print')) {
            window.open(targetUrl, '_blank', 'noopener');
            return;
        }

        window.location.assign(targetUrl);
    });
}

onReady(function () {
    initAuditLogsTable();
    initAuditLogDetailsModal();
    initAuditLogExports();
});


