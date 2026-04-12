export const $ = window.jQuery || null;

export function onReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
}

export function debounce(callback, wait = 250) {
    let timer = null;

    return (...args) => {
        if (timer) {
            clearTimeout(timer);
        }

        timer = window.setTimeout(() => callback(...args), wait);
    };
}

export function whenDataTableReady(table, onReadyCallback, {
    maxAttempts = 40,
    intervalMs = 150,
} = {}) {
    if (!(table instanceof HTMLTableElement) || typeof onReadyCallback !== 'function' || !$ || !$.fn?.dataTable) {
        return false;
    }

    const tryBind = () => {
        if (!$.fn.dataTable.isDataTable(table)) {
            return false;
        }

        onReadyCallback($(table).DataTable());
        return true;
    };

    if (tryBind()) {
        return true;
    }

    let attempts = 0;
    const timer = window.setInterval(() => {
        attempts += 1;
        if (tryBind() || attempts >= maxAttempts) {
            window.clearInterval(timer);
        }
    }, intervalMs);

    return false;
}

export function bindToolbarInteractions({
    namespace = 'toolbar',
    applySelector = null,
    searchSelector = null,
    changeSelectors = [],
    onApply,
    liveSearchDelay = 220,
    shouldAutoApply = null,
} = {}) {
    if (!$ || typeof onApply !== 'function') {
        return;
    }

    const canAutoApply = () => typeof shouldAutoApply === 'function' ? shouldAutoApply() : true;
    const applyNow = () => onApply();
    const debouncedApply = debounce(applyNow, liveSearchDelay);

    if (applySelector) {
        $(applySelector)
            .off(`.${namespace}`)
            .on(`click.${namespace}`, function (event) {
                event.preventDefault();
                applyNow();
            });
    }

    if (searchSelector) {
        $(searchSelector)
            .off(`.${namespace}`)
            .on(`input.${namespace}`, function () {
                if (!canAutoApply()) {
                    return;
                }
                debouncedApply();
            })
            .on(`keydown.${namespace}`, function (event) {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                applyNow();
            });
    }

    if (changeSelectors.length) {
        $(changeSelectors.join(', '))
            .off(`.${namespace}`)
            .on(`change.${namespace}`, function () {
                if (!canAutoApply()) {
                    return;
                }
                applyNow();
            });
    }
}


