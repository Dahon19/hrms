import { debounce, onReady } from './utils';

function getFieldValue(field) {
    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
        return field.value;
    }

    return '';
}

function setExpandedState(buttons, expanded) {
    buttons.forEach((button) => {
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });
}

function setDetailMode(row, mode = 'view') {
    if (!(row instanceof HTMLTableRowElement)) {
        return;
    }

    const normalizedMode = mode === 'edit' ? 'edit' : 'view';
    row.dataset.idpMode = normalizedMode;

    const readonlyPanel = row.querySelector('[data-idp-readonly="1"]');
    const editorForm = row.querySelector('[data-idp-editor="1"]');

    if (readonlyPanel instanceof HTMLElement) {
        readonlyPanel.classList.toggle('d-none', normalizedMode === 'edit');
    }

    if (editorForm instanceof HTMLElement) {
        editorForm.classList.toggle('d-none', normalizedMode !== 'edit');
    }
}

function closeDetailRows(exceptRowId = '') {
    document.querySelectorAll('#idpIndexPage .idp-detail-row').forEach((row) => {
        if (!(row instanceof HTMLTableRowElement)) {
            return;
        }

        if (exceptRowId && row.id === exceptRowId) {
            return;
        }

        row.classList.add('d-none');
        setExpandedState(
            Array.from(document.querySelectorAll(`[data-idp-target="${row.id}"]`)),
            false
        );
    });
}

function openDetailRow(rowId, { focusEditor = false, scrollIntoView = false, mode = 'view' } = {}) {
    const row = document.getElementById(rowId);
    if (!(row instanceof HTMLTableRowElement)) {
        return;
    }

    closeDetailRows(rowId);
    row.classList.remove('d-none');
    setDetailMode(row, mode);
    setExpandedState(Array.from(document.querySelectorAll(`[data-idp-target="${rowId}"]`)), true);

    if (scrollIntoView) {
        row.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    if (focusEditor) {
        window.setTimeout(() => {
            const firstFocusable = row.querySelector('[data-idp-editor] [data-idp-field], [data-idp-editor] textarea, [data-idp-editor] input');
            if (firstFocusable instanceof HTMLElement) {
                firstFocusable.focus({ preventScroll: true });
            }
        }, 0);
    }
}

function syncSaveButton(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const saveButton = form.querySelector('[data-idp-save]');
    if (!(saveButton instanceof HTMLButtonElement)) {
        return;
    }

    const fields = Array.from(form.querySelectorAll('[data-idp-field]'));
    const dirty = fields.some((field) => getFieldValue(field) !== (field.dataset.initialValue ?? ''));
    saveButton.disabled = !dirty;
}

function bindEditorForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const updateState = () => syncSaveButton(form);
    const debouncedState = debounce(updateState, 0);

    form.querySelectorAll('[data-idp-field]').forEach((field) => {
        field.addEventListener('input', debouncedState);
        field.addEventListener('change', updateState);
    });

    form.addEventListener('reset', () => {
        window.setTimeout(updateState, 0);
    });

    updateState();
}

function bindToolbar(toolbar) {
    if (!(toolbar instanceof HTMLFormElement)) {
        return;
    }
}

onReady(() => {
    const page = document.getElementById('idpIndexPage');
    if (!(page instanceof HTMLElement)) {
        return;
    }

    const toolbar = document.getElementById('idpIndexToolbar');
    bindToolbar(toolbar);

    document.querySelectorAll('[data-idp-editor="1"]').forEach((form) => {
        bindEditorForm(form);
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target?.closest?.('[data-idp-toggle="row"]');
        if (!trigger) {
            return;
        }

        const rowId = trigger.getAttribute('data-idp-target') || '';
        if (!rowId) {
            return;
        }

        const row = document.getElementById(rowId);
        if (!(row instanceof HTMLTableRowElement)) {
            return;
        }

        const isOpen = !row.classList.contains('d-none');
        const mode = trigger.dataset.idpMode === 'edit' ? 'edit' : 'view';
        if (isOpen && mode === 'view' && row.dataset.idpMode !== 'edit') {
            row.classList.add('d-none');
            setExpandedState(Array.from(document.querySelectorAll(`[data-idp-target="${rowId}"]`)), false);
            return;
        }

        openDetailRow(rowId, {
            focusEditor: mode === 'edit' || trigger.dataset.idpFocus === '1',
            scrollIntoView: true,
            mode,
        });
    });

    document.addEventListener('click', (event) => {
        const cancelButton = event.target?.closest?.('[data-idp-cancel="1"]');
        if (!cancelButton) {
            return;
        }

        const row = cancelButton.closest('.idp-detail-row');
        if (!(row instanceof HTMLTableRowElement)) {
            return;
        }

        window.setTimeout(() => {
            setDetailMode(row, 'view');
        }, 0);
    });

    const openPlanId = page.dataset.openPlanId || '';
    if (openPlanId) {
        openDetailRow(`idpDetailRow-${openPlanId}`, { scrollIntoView: false, mode: 'edit' });
    }
});
