import { $, onReady } from './utils';

function hasIrregularBodyRows(table) {
    const expectedColumns = table.tHead?.rows?.[0]?.cells?.length ?? 0;
    if (!expectedColumns || !table.tBodies.length) return false;

    const rows = Array.from(table.tBodies[0].rows);
    return rows.some((row) => {
        const cells = Array.from(row.cells);

        if (!cells.length) return false;
        if (cells.some((cell) => cell.colSpan > 1 || cell.rowSpan > 1)) return true;

        return cells.length !== expectedColumns;
    });
}

function decorateDataTable(table) {
    const wrapper = table.closest('.dataTables_wrapper');
    if (!(wrapper instanceof HTMLDivElement)) return;

    wrapper.classList.add('hrms-datatable-shell');

    const tableCard = table.closest('.ui-table-card');
    if (tableCard instanceof HTMLDivElement) {
        tableCard.classList.add('ui-table-card--datatable');
    }

    const filterLabel = wrapper.querySelector('.dataTables_filter label');
    if (filterLabel instanceof HTMLLabelElement) {
        filterLabel.classList.add('hrms-datatable-filter');
    }

    const searchInput = wrapper.querySelector('.dataTables_filter input[type="search"]');
    if (searchInput instanceof HTMLInputElement) {
        searchInput.placeholder = 'Records';
        searchInput.classList.add('hrms-search-input');
        searchInput.classList.add('hrms-search-field');
        searchInput.setAttribute('aria-label', 'Search table records');
    }
}

function initDataTables() {
    if (!$ || !$.fn.DataTable) return;

    document.querySelectorAll('table.hrms-table').forEach((table) => {
        if (!(table instanceof HTMLTableElement)) return;
        if (table.dataset.noDatatable === '1') return;
        if ($.fn.DataTable.isDataTable(table)) return;
        if (hasIrregularBodyRows(table)) return;

        const searching = table.dataset.dtSearch === '0' ? false : true;
        const hasLaravelPagination = table.closest('.ui-table-card')?.querySelector('.pagination') !== null;
        const usePaging = !hasLaravelPagination && table.dataset.dtPaging !== '0';

        const instance = $(table).DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 10,
            lengthChange: false,
            paging: usePaging,
            info: usePaging,
            ordering: true,
            searching,
            pagingType: 'simple_numbers',
            language: {
                search: '',
                searchPlaceholder: 'Records',
                emptyTable: 'No records available',
                zeroRecords: 'No matching records found',
                info: 'Showing _START_-_END_ of _TOTAL_ records',
                infoEmpty: 'Showing 0-0 of 0 records',
                paginate: {
                    previous: 'Prev',
                    next: 'Next',
                },
            },
        });

        decorateDataTable(table);

        instance.on('draw', function () {
            decorateDataTable(table);
        });
    });
}

onReady(initDataTables);
