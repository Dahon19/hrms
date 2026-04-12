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

function toggleRowDetails(button) {
    const mainRow = button.closest('tr');
    const detailRow = mainRow?.nextElementSibling;
    if (!detailRow || !detailRow.classList.contains('eligibility-row-detail')) {
        return;
    }

    const expanded = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    detailRow.classList.toggle('d-none', expanded);
    const icon = button.querySelector('i');
    if (icon) {
        icon.className = expanded ? 'cil-chevron-bottom' : 'cil-chevron-top';
    }
}

function bindRowDetails() {
    if (document.body?.dataset?.eligibilityDetailBound === '1') {
        return;
    }

    document.body.dataset.eligibilityDetailBound = '1';
    document.addEventListener('click', (event) => {
        const button = event.target.closest('#eligibilityTable .js-eligibility-toggle-detail');
        if (!button) {
            return;
        }

        event.preventDefault();
        toggleRowDetails(button);
    });
}

function bindExportButton() {
    const exportButton = document.querySelector('.js-eligibility-export');
    if (!exportButton) {
        return;
    }

    exportButton.addEventListener('click', () => {
        if (exportButton.dataset.loading === '1') {
            return;
        }

        exportButton.dataset.loading = '1';
        exportButton.classList.add('disabled');
        const icon = exportButton.querySelector('i');
        if (icon) {
            icon.className = 'cil-reload mr-1';
        }

        setTimeout(() => {
            exportButton.dataset.loading = '0';
            exportButton.classList.remove('disabled');
            if (icon) {
                icon.className = 'cil-print mr-1';
            }
        }, 2500);
    });
}

function updateContainerFromHtml(html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const next = doc.getElementById('eligibilityListContainer');
    const current = document.getElementById('eligibilityListContainer');
    if (!next || !current) {
        return false;
    }

    current.replaceWith(next);
    return true;
}

function bootstrapEligibilityAjax() {
    let activeController = null;

    const runAjax = async (url) => {
        if (activeController) {
            activeController.abort();
        }

        activeController = new AbortController();

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: activeController.signal,
            });

            if (!response.ok) {
                return;
            }

            const html = await response.text();
            if (!updateContainerFromHtml(html)) {
                return;
            }

            window.history.replaceState({}, '', url);
            bindInteractions(runAjax);
            document.dispatchEvent(new CustomEvent('hrms:eligibility-content-updated'));
        } catch (error) {
            if (error?.name !== 'AbortError') {
                // no-op
            }
        }
    };

    return runAjax;
}

function bindInteractions(runAjax) {
    bindRowDetails();
    bindExportButton();

    const form = document.getElementById('eligibilityFilterForm');
    if (!form) {
        return;
    }

    const debounced = debounce(() => {
        const params = new URLSearchParams(new FormData(form));
        const url = `${form.action}?${params.toString()}`;
        runAjax(url);
    }, 300);

    form.querySelectorAll('select, input[type="text"], input[type="search"], input[type="number"]').forEach((field) => {
        field.addEventListener('input', debounced);
        field.addEventListener('change', debounced);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        runAjax(`${form.action}?${params.toString()}`);
    });

    document.querySelectorAll('#eligibilityPaginationWrap a.page-link').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const href = link.getAttribute('href');
            if (href) {
                runAjax(href);
            }
        });
    });
}

onReady(() => {
    const page = document.body?.dataset?.page || '';
    if (!page.startsWith('eligibility.') && !page.startsWith('rewards.eligibility.')) {
        return;
    }

    const runAjax = bootstrapEligibilityAjax();
    bindInteractions(runAjax);
});


