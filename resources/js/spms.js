import { onReady } from './utils';

function debounce(fn, wait = 300) {
    let timer = null;
    return (...args) => {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(() => fn(...args), wait);
    };
}

function setButtonLoading(button) {
    if (!button || button.dataset.loading === '1') {
        return;
    }

    button.dataset.loading = '1';
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm hrms-btn__spinner me-2" role="status" aria-hidden="true"></span><span class="visually-hidden">Loading</span>';
}

function initSpmsConfirmForms() {
    document.querySelectorAll('.spms-confirm-form').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!form.dataset.confirmMessage && form.dataset.spmsConfirm) {
            form.dataset.confirmMessage = form.dataset.spmsConfirm;
        }

        if (!form.dataset.confirmTitle) {
            form.dataset.confirmTitle = 'Confirm SPMS Action';
        }
    });

    document.querySelectorAll('.spms-submit-confirm').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        if (!button.dataset.confirmMessage && button.dataset.confirmText) {
            button.dataset.confirmMessage = button.dataset.confirmText;
        }

        if (!button.dataset.confirmTitle) {
            button.dataset.confirmTitle = 'Submit Evaluation';
        }

        if (!button.dataset.confirmLabel) {
            button.dataset.confirmLabel = 'Submit';
        }
    });
}

function initFormLoadingState() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        setButtonLoading(submitButton);

        const table = form.closest('.table-responsive')?.querySelector('table');
        if (table) {
            table.classList.add('spms-table-loading');
        }
    });
}

function initCycleDirectoryInteractions() {
    const table = document.getElementById('spmsCyclesTable');
    if (!table) {
        return;
    }

    const compactToolbarMedia = window.matchMedia ? window.matchMedia('(max-width: 991.98px)') : null;
    const body = document.getElementById('spmsCyclesTableBody');
    const searchInput = document.getElementById('spmsCycleSearchInput');
    const statusFilter = document.getElementById('spmsCycleStatusFilter');
    const periodFilter = document.getElementById('spmsCyclePeriodFilter');
    const applyButton = document.getElementById('spmsCyclesApply');
    const resetButton = document.getElementById('spmsCyclesToolbarReset');

    const rows = () => Array.from(body.querySelectorAll('tr')).filter((row) => row.querySelector('td'));

    const applyFilter = () => {
        const search = (searchInput?.value || '').trim().toLowerCase();
        const status = (statusFilter?.value || '').trim().toLowerCase();
        const period = (periodFilter?.value || '').trim().toLowerCase();

        let visible = 0;
        rows().forEach((row) => {
            const title = (row.dataset.title || '').toLowerCase();
            const rowStatus = (row.dataset.status || '').toLowerCase();
            const rowPeriod = (row.dataset.period || '').toLowerCase();

            const matched = (!search || title.includes(search))
                && (!status || rowStatus === status)
                && (!period || rowPeriod.includes(period));

            row.classList.toggle('d-none', !matched);
            if (matched) {
                visible += 1;
            }
        });

        let empty = document.getElementById('spmsCyclesEmptyClient');
        if (!empty) {
            empty = document.createElement('tr');
            empty.id = 'spmsCyclesEmptyClient';
            empty.className = 'd-none';
            empty.innerHTML = '<td colspan="5" class="text-center py-4 text-muted">No cycles match the current filters.</td>';
            body.appendChild(empty);
        }
        empty.classList.toggle('d-none', visible !== 0);
    };

    const debounced = debounce(applyFilter, 300);
    const shouldAutoApply = () => !compactToolbarMedia || !compactToolbarMedia.matches;
    searchInput?.addEventListener('input', () => {
        if (!shouldAutoApply()) {
            return;
        }
        debounced();
    });
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        applyFilter();
    });
    periodFilter?.addEventListener('input', () => {
        if (!shouldAutoApply()) {
            return;
        }
        debounced();
    });
    periodFilter?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        applyFilter();
    });
    statusFilter?.addEventListener('change', () => {
        if (!shouldAutoApply()) {
            return;
        }
        applyFilter();
    });
    applyButton?.addEventListener('click', applyFilter);
    resetButton?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
        }
        if (statusFilter) {
            statusFilter.value = '';
        }
        if (periodFilter) {
            periodFilter.value = '';
        }
        applyFilter();
    });

    let sortDirection = 'asc';
    let sortKey = '';
    Array.from(table.querySelectorAll('thead th[data-sort-key]')).forEach((th) => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', () => {
            const key = th.dataset.sortKey || '';
            if (!key) {
                return;
            }

            sortDirection = sortKey === key && sortDirection === 'asc' ? 'desc' : 'asc';
            sortKey = key;

            const sorted = rows().sort((a, b) => {
                const aValue = a.dataset[key] || '';
                const bValue = b.dataset[key] || '';
                const aNumber = Number(aValue);
                const bNumber = Number(bValue);
                if (!Number.isNaN(aNumber) && !Number.isNaN(bNumber) && aValue !== '' && bValue !== '') {
                    return sortDirection === 'asc' ? aNumber - bNumber : bNumber - aNumber;
                }
                return sortDirection === 'asc'
                    ? aValue.localeCompare(bValue)
                    : bValue.localeCompare(aValue);
            });

            sorted.forEach((row) => body.appendChild(row));
            applyFilter();
        });
    });

    applyFilter();
}

function initCycleEmployeeInteractions() {
    const form = document.getElementById('spmsCycleEmployeesFilterForm');
    if (!form) {
        return;
    }

    const searchInput = document.getElementById('spmsEmployeeSearchInput');
    const deptSelect = form.querySelector('select[name="department_id"]');
    const statusFilter = document.getElementById('spmsEmployeeStatusFilter');
    const body = document.getElementById('spmsEmployeesTableBody');

    const debouncedSubmit = debounce(() => form.submit(), 300);
    searchInput?.addEventListener('input', debouncedSubmit);
    deptSelect?.addEventListener('change', () => form.submit());

    statusFilter?.addEventListener('change', () => {
        const selected = (statusFilter.value || '').trim().toLowerCase();
        Array.from(body.querySelectorAll('tr')).forEach((row) => {
            const status = (row.dataset.status || '').toLowerCase();
            const matched = !selected || status === selected;
            row.classList.toggle('d-none', !matched);
        });
    });

    document.querySelectorAll('.spms-export-btn').forEach((link) => {
        link.addEventListener('click', () => {
            if (link.dataset.loading === '1') {
                return;
            }
            link.dataset.loading = '1';
            const icon = link.querySelector('i');
            if (icon) {
                icon.className = 'cil-reload mr-1';
            }
            link.classList.add('disabled');
            setTimeout(() => {
                link.dataset.loading = '0';
                if (icon) {
                    icon.className = 'cil-spreadsheet mr-1';
                }
                link.classList.remove('disabled');
            }, 2500);
        });
    });
}

function initEvaluationsIndexFilters() {
    const page = document.getElementById('spmsEvaluationsIndexPage');
    const body = document.getElementById('spmsEvaluationsTableBody');
    if (!page || !body) {
        return;
    }

    const searchInput = document.getElementById('spmsEvaluationsSearchInput');
    const statusFilter = document.getElementById('spmsEvaluationsStatusFilter');
    const cycleFilter = document.getElementById('spmsEvaluationsCycleFilter');
    const applyButton = document.getElementById('spmsEvaluationsApply');

    const applyFilter = () => {
        const search = (searchInput?.value || '').trim().toLowerCase();
        const status = (statusFilter?.value || '').trim().toLowerCase();
        const cycle = (cycleFilter?.value || '').trim().toLowerCase();

        let visible = 0;
        Array.from(body.querySelectorAll('tr')).forEach((row) => {
            if (!row.querySelector('td')) {
                return;
            }

            const rowSearch = (row.dataset.search || '').toLowerCase();
            const rowStatus = (row.dataset.status || '').toLowerCase();
            const rowCycle = (row.dataset.cycle || '').toLowerCase();
            const matched = (!search || rowSearch.includes(search))
                && (!status || rowStatus === status)
                && (!cycle || rowCycle.includes(cycle));

            row.classList.toggle('d-none', !matched);
            if (matched) {
                visible += 1;
            }
        });

        let empty = document.getElementById('spmsEvaluationsEmptyClient');
        if (!empty) {
            empty = document.createElement('tr');
            empty.id = 'spmsEvaluationsEmptyClient';
            empty.className = 'd-none';
            empty.innerHTML = '<td colspan="4" class="text-center py-4 text-muted">No evaluations match the current filters.</td>';
            body.appendChild(empty);
        }
        empty.classList.toggle('d-none', visible !== 0);
    };

    applyButton?.addEventListener('click', applyFilter);
    searchInput?.addEventListener('input', debounce(applyFilter, 300));
    statusFilter?.addEventListener('change', applyFilter);
    cycleFilter?.addEventListener('input', debounce(applyFilter, 300));
    applyFilter();
}

function initMyPerformanceFilters() {
    const page = document.getElementById('myPerformancePage');
    const body = document.getElementById('myPerformanceTableBody');
    if (!page || !body) {
        return;
    }

    const searchInput = document.getElementById('myPerformanceSearchInput');
    const statusFilter = document.getElementById('myPerformanceStatusFilter');
    const applyButton = document.getElementById('myPerformanceApply');

    const applyFilter = () => {
        const search = (searchInput?.value || '').trim().toLowerCase();
        const status = (statusFilter?.value || '').trim().toLowerCase();

        let visible = 0;
        Array.from(body.querySelectorAll('tr')).forEach((row) => {
            if (!row.querySelector('td')) {
                return;
            }

            const rowSearch = (row.dataset.search || '').toLowerCase();
            const rowStatus = (row.dataset.status || '').toLowerCase();
            const matched = (!search || rowSearch.includes(search))
                && (!status || rowStatus === status);

            row.classList.toggle('d-none', !matched);
            if (matched) {
                visible += 1;
            }
        });

        let empty = document.getElementById('myPerformanceEmptyClient');
        if (!empty) {
            empty = document.createElement('tr');
            empty.id = 'myPerformanceEmptyClient';
            empty.className = 'd-none';
            empty.innerHTML = '<td colspan="4" class="text-center py-4 text-muted">No performance records match the current filters.</td>';
            body.appendChild(empty);
        }
        empty.classList.toggle('d-none', visible !== 0);
    };

    applyButton?.addEventListener('click', applyFilter);
    searchInput?.addEventListener('input', debounce(applyFilter, 300));
    statusFilter?.addEventListener('change', applyFilter);
    applyFilter();
}

function initEvaluationEnhancements() {
    const form = document.getElementById('spmsEvaluationForm');
    if (!form) {
        return;
    }

    const intentInput = document.getElementById('spmsEvaluationIntent');
    const totalDisplay = document.getElementById('spmsTotalScoreDisplay');
    const ratingDisplay = document.getElementById('spmsRatingLabelDisplay');

    const getRatingLabel = (score) => {
        if (score >= 4.5) return 'Outstanding';
        if (score >= 3.5) return 'Very Satisfactory';
        if (score >= 2.5) return 'Satisfactory';
        if (score >= 1.5) return 'Unsatisfactory';
        return 'Poor';
    };

    const recalculate = () => {
        const rows = Array.from(form.querySelectorAll('#spmsEvaluationCriteriaTable tbody tr'));
        let weighted = 0;
        let totalWeight = 0;

        rows.forEach((row) => {
            const weightEl = row.querySelector('.spms-criterion-weight');
            const scoreInput = row.querySelector('.spms-score-input');
            if (!weightEl || !scoreInput) {
                return;
            }

            const weight = parseFloat(weightEl.dataset.weight || '0');
            let score = parseFloat(scoreInput.value || scoreInput.dataset.score || '0');
            if (Number.isNaN(weight) || Number.isNaN(score)) {
                return;
            }

            if (score < 1) score = 1;
            if (score > 5) score = 5;

            weighted += score * weight;
            totalWeight += weight;
        });

        const total = totalWeight > 0 ? (weighted / totalWeight) : 0;
        if (totalDisplay) {
            totalDisplay.textContent = total.toFixed(2);
        }
        if (ratingDisplay) {
            ratingDisplay.textContent = getRatingLabel(total);
        }
    };

    form.querySelectorAll('.spms-score-input').forEach((input) => {
        input.addEventListener('change', recalculate);
        input.addEventListener('input', recalculate);
    });

    form.querySelectorAll('[data-spms-intent]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.addEventListener('click', () => {
            if (intentInput) {
                intentInput.value = button.dataset.spmsIntent || 'draft';
            }
        });
    });

    recalculate();

    form.addEventListener('submit', (event) => {
        const submitter = event.submitter;
        const intent = intentInput?.value || (submitter instanceof HTMLButtonElement ? submitter.value || '' : '');
        if (intent === 'submitted' && form.dataset.confirmBypass !== '1') {
            return;
        }

        if (submitter instanceof HTMLButtonElement) {
            submitter.disabled = true;
        }
    });
}

onReady(() => {
    const page = document.body?.dataset?.page || '';
    if (!page.startsWith('spms.')) {
        return;
    }

    initSpmsConfirmForms();
    initFormLoadingState();
    initCycleDirectoryInteractions();
    initCycleEmployeeInteractions();
    initEvaluationsIndexFilters();
    initMyPerformanceFilters();
    initEvaluationEnhancements();
});


