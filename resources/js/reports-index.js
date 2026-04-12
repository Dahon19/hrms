import { $, onReady } from './utils';

function parseDate(value) {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

function initReportsWorkspace() {
    if (!document.body || document.body.getAttribute('data-page') !== 'reports.index') return;

    const workspaceEl = document.getElementById('reportsWorkspace');
    const departmentEl = document.getElementById('reportDepartment');
    const dateFromEl = document.getElementById('reportDateFrom');
    const dateToEl = document.getElementById('reportDateTo');
    const searchEl = document.getElementById('reportSearch');
    const applyBtn = document.getElementById('applyReportFilters');
    const printBtn = document.getElementById('printReportHero');
    const printTitleEl = document.getElementById('reportsPrintTitle');
    const printMetaEl = document.getElementById('reportsPrintMeta');
    const hasDataTables = Boolean($ && $.fn && $.fn.DataTable);

    const tableSelectors = [
        '#departmentMetricsTable',
        '#attendanceAnomaliesTable',
        '#leaveSummaryTable',
        '#documentExpiryTable',
        '#travelOrdersTable'
    ];

    const filterState = {
        department: '',
        dateFrom: '',
        dateTo: '',
        search: '',
    };

    const getActiveReportPane = () => {
        const activePane = document.querySelector('#reportsModuleTabsContent .tab-pane.active');
        return activePane instanceof HTMLElement ? activePane : document.querySelector('#reportsModuleTabsContent .tab-pane');
    };

    const buildPrintMeta = () => {
        const parts = [];

        if (filterState.department) {
            const selectedDepartment = departmentEl?.selectedOptions?.[0]?.textContent?.trim();
            if (selectedDepartment) {
                parts.push(`Department: ${selectedDepartment}`);
            }
        }

        if (filterState.dateFrom) {
            parts.push(`From: ${filterState.dateFrom}`);
        }

        if (filterState.dateTo) {
            parts.push(`To: ${filterState.dateTo}`);
        }

        if (filterState.search) {
            parts.push(`Search: ${filterState.search}`);
        }

        return parts.join(' | ');
    };

    const syncPrintableSection = () => {
        const activePane = getActiveReportPane();
        const sectionId = activePane?.id || '';
        const sectionTitle = activePane?.dataset.reportTitle || 'Report Summary';

        if (workspaceEl) {
            workspaceEl.dataset.printSection = sectionId;
        }

        if (printTitleEl) {
            printTitleEl.textContent = sectionTitle;
        }

        if (printMetaEl) {
            printMetaEl.textContent = buildPrintMeta();
        }
    };

    if (!hasDataTables) {
        if (printBtn && !printBtn.hasAttribute('onclick')) {
            printBtn.addEventListener('click', () => {
                syncPrintableSection();
                window.print();
            });
        }
        return;
    }

    const extFilter = function (settings, data, dataIndex) {
        const tableId = settings.nTable ? settings.nTable.id : '';
        if (!tableSelectors.some((selector) => selector === `#${tableId}`)) {
            return true;
        }

        const row = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
        if (!row) return true;

        const rowDepartment = String(row.getAttribute('data-report-department') || '').toLowerCase().trim();
        const rowDate = String(row.getAttribute('data-report-date') || '').trim();

        if (filterState.department && rowDepartment !== filterState.department) {
            return false;
        }

        const fromDate = parseDate(filterState.dateFrom);
        const toDate = parseDate(filterState.dateTo);
        const currentDate = parseDate(rowDate);

        if (fromDate && currentDate && currentDate < fromDate) {
            return false;
        }

        if (toDate && currentDate && currentDate > toDate) {
            return false;
        }

        return true;
    };

    $.fn.dataTable.ext.search.push(extFilter);

    const drawAllTables = () => {
        tableSelectors.forEach((selector) => {
            if (!$.fn.DataTable.isDataTable(selector)) return;
            const instance = $(selector).DataTable();
            instance.search(filterState.search).draw();
        });
    };

    const applyFilters = () => {
        filterState.department = String(departmentEl ? departmentEl.value : '').toLowerCase().trim();
        filterState.dateFrom = dateFromEl ? dateFromEl.value : '';
        filterState.dateTo = dateToEl ? dateToEl.value : '';
        filterState.search = String(searchEl ? searchEl.value : '').trim();
        drawAllTables();
        syncPrintableSection();
    };

    if (applyBtn) {
        applyBtn.addEventListener('click', applyFilters);
    }
    if (printBtn && !printBtn.hasAttribute('onclick')) {
        printBtn.addEventListener('click', () => {
            applyFilters();
            syncPrintableSection();
            window.print();
        });
    }

    if (searchEl) {
        searchEl.addEventListener('keyup', (event) => {
            if (event.key === 'Enter') {
                applyFilters();
            }
        });
    }

    const hashToTab = {
        '#table-department-metrics': '#tab-department-tab',
        '#table-attendance-anomalies': '#tab-anomalies-tab',
        '#table-leave-summary': '#tab-leaves-tab',
        '#table-document-expiry': '#tab-documents-tab',
        '#table-travel-orders': '#tab-travel-orders-tab',
    };

    const openTabFromHash = () => {
        const currentHash = window.location.hash || '';
        const tabTrigger = hashToTab[currentHash];
        if (tabTrigger && $(tabTrigger).length) {
            $(tabTrigger).tab('show');
            setTimeout(() => {
                const target = document.querySelector(currentHash);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 120);
        }
    };

    openTabFromHash();
    syncPrintableSection();

    $(window).on('hashchange', openTabFromHash);

    $('a[data-toggle="tab"][href^="#tab-"]').on('shown.bs.tab', function () {
        syncPrintableSection();
        tableSelectors.forEach((selector) => {
            if (!$.fn.DataTable.isDataTable(selector)) return;
            $(selector).DataTable().columns.adjust();
        });
    });

    window.addEventListener('beforeprint', syncPrintableSection);
}

onReady(function () {
    initReportsWorkspace();
});


