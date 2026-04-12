import { debounce, onReady } from './utils';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function updateStatus(form, message, tone = 'muted') {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const status = form.querySelector('[data-pds-autosave-status="1"]');
    if (!(status instanceof HTMLElement)) {
        return;
    }

    status.textContent = message;
    status.classList.remove('text-muted', 'text-success', 'text-danger', 'text-warning');
    status.classList.add(`text-${tone}`);
}

function serializeForm(form) {
    return new FormData(form);
}

async function parseJson(response) {
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
        return null;
    }

    try {
        return await response.json();
    } catch (error) {
        return null;
    }
}

function updateAllStatuses(page, message, tone = 'muted') {
    if (!(page instanceof HTMLElement)) {
        return;
    }

    page.querySelectorAll('form.pds-section-form').forEach((form) => {
        updateStatus(form, message, tone);
    });
}

async function autoSubmitPds(page) {
    if (!(page instanceof HTMLElement)) {
        return false;
    }

    if (page.dataset.pdsAutoSubmit !== '1' || page.dataset.pdsStatus === 'submitted') {
        return false;
    }

    if (page.dataset.pdsAutoSubmitting === '1') {
        return false;
    }

    const submitUrl = page.dataset.pdsSubmitUrl;
    if (!submitUrl) {
        return false;
    }

    page.dataset.pdsAutoSubmitting = '1';
    updateAllStatuses(page, 'Submitting PDS...', 'warning');

    try {
        const response = await fetch(submitUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });

        const payload = await parseJson(response);

        if (!response.ok) {
            throw new Error(payload?.message || 'Auto-submit failed.');
        }

        page.dataset.pdsStatus = payload?.status || 'submitted';
        page.dataset.pdsAutoSubmit = '0';
        updateAllStatuses(page, payload?.message || 'PDS submitted for HR verification.', 'success');
        window.setTimeout(() => window.location.reload(), 500);
        return true;
    } catch (error) {
        updateAllStatuses(page, error.message || 'Auto-submit failed.', 'danger');
        return false;
    } finally {
        page.dataset.pdsAutoSubmitting = '0';
    }
}

async function autosaveForm(form) {
    if (!(form instanceof HTMLFormElement) || form.dataset.pdsAutosave !== '1') {
        return true;
    }

    if (form.dataset.autosaveSubmitting === '1') {
        form.dataset.autosaveDirty = '1';
        return false;
    }

    form.dataset.autosaveSubmitting = '1';
    form.dataset.autosaveDirty = '0';
    updateStatus(form, 'Autosaving...', 'warning');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: serializeForm(form),
            credentials: 'same-origin',
        });

        const payload = await parseJson(response);

        if (!response.ok) {
            throw new Error(payload?.message || 'Autosave failed.');
        }

        updateStatus(form, `Saved ${new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`, 'success');
        const page = form.closest('#pdsShowPage');
        if (page instanceof HTMLElement) {
            if (payload?.status) {
                page.dataset.pdsStatus = payload.status;
            }

            if (payload?.ready_to_submit) {
                await autoSubmitPds(page);
            }
        }

        return true;
    } catch (error) {
        updateStatus(form, 'Autosave failed. Check your connection and keep editing to retry.', 'danger');
        return false;
    } finally {
        form.dataset.autosaveSubmitting = '0';

        if (form.dataset.autosaveDirty === '1') {
            queueAutosave(form);
        }
    }
}

const autosaveQueue = new WeakMap();

function queueAutosave(form) {
    if (!(form instanceof HTMLFormElement) || form.dataset.pdsAutosave !== '1') {
        return;
    }

    let saver = autosaveQueue.get(form);
    if (!saver) {
        saver = debounce(() => autosaveForm(form), 1200);
        autosaveQueue.set(form, saver);
    }

    updateStatus(form, 'Unsaved changes...', 'muted');
    saver();
}

function bindAutosave(form) {
    if (!(form instanceof HTMLFormElement) || form.dataset.pdsAutosave !== '1') {
        return;
    }

    form.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.closest('[data-pds-autosave-ignore="1"]')) {
            return;
        }

        queueAutosave(form);
    });

    form.addEventListener('change', () => {
        queueAutosave(form);
    });

    form.addEventListener('submit', () => {
        form.dataset.autosaveSubmitting = '1';
        updateStatus(form, 'Saving...', 'warning');
    });
}

function bindDynamicActionAutosave(page) {
    if (!(page instanceof HTMLElement)) {
        return;
    }

    page.addEventListener('click', (event) => {
        const action = event.target?.closest?.(
            '[data-pds-child-add="1"], [data-pds-child-remove="1"], [data-pds-repeatable-add="1"], [data-pds-repeatable-remove="1"], [data-pds-simple-add="1"], [data-pds-simple-remove="1"]'
        );
        if (!action) {
            return;
        }

        const form = action.closest('form');
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        window.setTimeout(() => queueAutosave(form), 0);
    });
}

onReady(() => {
    const page = document.getElementById('pdsShowPage');
    if (!(page instanceof HTMLElement)) {
        return;
    }

    page.querySelectorAll('form.pds-section-form').forEach((form) => {
        bindAutosave(form);
    });

    bindDynamicActionAutosave(page);
});
