import { $, onReady } from './utils';

function emitSidebarStateChange() {
    document.dispatchEvent(new CustomEvent('hrms:sidebar-toggled', {
        detail: {
            collapsed: document.body.classList.contains('sidebar-collapse'),
            open: document.body.classList.contains('sidebar-open'),
        },
    }));
}

function getSelect2DropdownParent($modal, $offcanvas) {
    if ($modal?.length) {
        return $modal;
    }

    if ($offcanvas?.length) {
        return $(document.body);
    }

    return $(document.body);
}

function initSelect2() {
    if (!$ || !$.fn.select2) return;

    $('.select2').select2({ width: '100%' });
    $('.select2bs4')
        .not('[data-you-badge="1"]')
        .not('[data-toolbar-select2="1"]')
        .not('[data-employee-select="1"]')
        .not('.employee-department')
        .not('.employee-positions')
        .each(function () {
            const $el = $(this);
            const $modal = $el.closest('.modal');
            const $offcanvas = $el.closest('.offcanvas');

            if ($el.hasClass('select2-hidden-accessible')) {
                $el.select2('destroy');
            }

            $el.select2({
                theme: 'bootstrap4',
                width: '100%',
                dropdownParent: getSelect2DropdownParent($modal, $offcanvas),
            });
        });

    $('.select2bs4[data-you-badge="1"]').not('[data-toolbar-select2="1"]').each(function () {
        const $el = $(this);
        const $modal = $el.closest('.modal');
        const $offcanvas = $el.closest('.offcanvas');

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        $el.select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: getSelect2DropdownParent($modal, $offcanvas),
            templateResult: formatSelect2WithBadge,
            templateSelection: formatSelect2WithBadge,
            escapeMarkup: function (markup) {
                return markup;
            },
        });
    });
}

function formatSelect2WithBadge(state) {
    if (!state || !state.id) return state?.text || '';
    const $option = $(state.element);
    const isYou = String($option.data('you')) === '1';
    if (!isYou) return state.text;
    return `<span class="select2-option-with-badge">${state.text}<span class="select2-you-badge">You</span></span>`;
}

function initEmployeeSelects() {
    if (!$ || !$.fn.select2) return;

    const syncEmployeeToolbarSelectState = ($el) => {
        const $container = $el.next('.select2-container--bootstrap4');
        if (!$container.length) {
            return;
        }

        const value = String($el.val() || '').trim();
        $container.toggleClass('hrms-toolbar-select2--empty', value === '');
    };

    $('[data-employee-select="1"]').each(function () {
        const $el = $(this);
        if ($el.data('employee-select-initialized') === 1) return;

        const searchUrl = String($el.data('search-url') || '');
        if (!searchUrl) return;

        const placeholder = String($el.data('placeholder') || 'Search employee by name or ID');
        const allowClear = String($el.data('allow-clear')) === '1';
        const includeArchived = String($el.data('include-archived')) === '1';
        const $modal = $el.closest('.modal');
        const $offcanvas = $el.closest('.offcanvas');

        $el.select2({
            theme: 'bootstrap4',
            width: '100%',
            allowClear,
            placeholder,
            dropdownParent: getSelect2DropdownParent($modal, $offcanvas),
            minimumInputLength: 0,
            ajax: {
                url: searchUrl,
                dataType: 'json',
                delay: 300,
                cache: true,
                data: function (params) {
                    return {
                        q: params.term || '',
                        limit: 20,
                        include_archived: includeArchived ? 1 : 0,
                    };
                },
                processResults: function (data) {
                    const results = Array.isArray(data?.results) ? data.results : [];
                    return {
                        results: results.map((item) => ({ id: item.id, text: item.text })),
                        pagination: { more: Boolean(data?.pagination?.more) },
                    };
                },
            },
        });

        syncEmployeeToolbarSelectState($el);
        $el.on('change', function () {
            syncEmployeeToolbarSelectState($el);
        });

        $el.data('employee-select-initialized', 1);
    });
}

function initToolbarSelect2() {
    if (!$ || !$.fn.select2) return;

    const syncToolbarSelectState = ($el) => {
        const $container = $el.next('.select2-container--bootstrap4');
        if (!$container.length) return;

        const value = String($el.val() || '').trim();
        $container.toggleClass('hrms-toolbar-select2--empty', value === '');
    };

    $('[data-toolbar-select2="1"]').each(function () {
        const $el = $(this);
        if ($el.data('toolbar-select2-initialized') === 1) return;

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        const $modal = $el.closest('.modal');
        const $offcanvas = $el.closest('.offcanvas');
        const placeholder = String($el.data('placeholder') || '');
        const allowClear = String($el.data('allow-clear') || '1') === '1';
        const templateSelection = (state) => {
            const hasValue = state && state.id != null && String(state.id).trim() !== '';
            return hasValue ? state.text : placeholder;
        };

        $el.select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownAutoWidth: false,
            allowClear,
            placeholder,
            dropdownCssClass: 'hrms-toolbar-select2-dropdown',
            dropdownParent: getSelect2DropdownParent($modal, $offcanvas),
            minimumResultsForSearch: 0,
            templateSelection,
        });

        syncToolbarSelectState($el);
        $el.on('change', function () {
            syncToolbarSelectState($el);
        });

        $el.data('toolbar-select2-initialized', 1);
    });
}

function initToolbarAutocomplete() {
    const toolbarInputs = document.querySelectorAll(
        '.ui-table-card__toolbar input[type="search"], ' +
        '.ui-table-card__toolbar input[type="text"], ' +
        '.ui-table-card__controls input[type="search"], ' +
        '.ui-table-card__controls input[type="text"], ' +
        '.reports-filter-card input[type="search"], ' +
        '.reports-filter-card input[type="text"]'
    );

    const normalizeText = (value) => String(value || '')
        .replace(/\s+/g, ' ')
        .replace(/\s*·\s*/g, ' · ')
        .trim();

    const uniqueValues = (values, limit = 30) => {
        const seen = new Set();
        const items = [];

        values.forEach((value) => {
            const text = normalizeText(value);
            if (!text || text.length < 2 || text.length > 90) {
                return;
            }

            const key = text.toLowerCase();
            if (seen.has(key)) {
                return;
            }

            seen.add(key);
            items.push(text);
        });

        return items.slice(0, limit);
    };

    const isManagedSearchInput = (input) => {
        if (!(input instanceof HTMLInputElement)) {
            return false;
        }

        if (
            input.type === 'hidden' ||
            input.classList.contains('select2-search__field') ||
            input.classList.contains('select2-hidden-accessible') ||
            input.closest('.select2-container') ||
            String(input.getAttribute('aria-controls') || '').startsWith('select2-')
        ) {
            return false;
        }

        return true;
    };

    const extractRowLines = (row) => {
        const values = [];

        Array.from(row.querySelectorAll('td')).forEach((cell) => {
            const text = normalizeText(cell.textContent || '');
            if (text) {
                values.push(text);
            }

            String(cell.innerText || '')
                .split('\n')
                .map((line) => normalizeText(line))
                .filter(Boolean)
                .forEach((line) => values.push(line));
        });

        Object.values(row.dataset || {})
            .map((value) => normalizeText(value))
            .filter(Boolean)
            .forEach((value) => values.push(value));

        return values;
    };

    const extractExplicitRowSuggestions = (row) => {
        const datasetValues = [
            row.dataset.search,
            row.dataset.searchPrimary,
            row.dataset.searchSecondary,
            row.dataset.autocomplete,
        ];

        return datasetValues
            .flatMap((value) => String(value || '').split('|'))
            .map((value) => normalizeText(value))
            .filter(Boolean);
    };

    const extractPrimaryColumnSuggestions = (row, maxColumns = 2) => {
        const values = [];

        Array.from(row.querySelectorAll('td'))
            .slice(0, maxColumns)
            .forEach((cell) => {
                const lineValues = String(cell.innerText || '')
                    .split('\n')
                    .map((line) => normalizeText(line))
                    .filter(Boolean);

                lineValues.forEach((line) => values.push(line));
            });

        return values;
    };

    const buildSuggestions = (input) => {
        const card = input.closest('.ui-table-card, .reports-filter-card, .card');
        if (!(card instanceof HTMLElement)) {
            return [];
        }

        const rows = Array.from(card.querySelectorAll('tbody tr'))
            .filter((row) => row.querySelectorAll('td').length > 0);
        if (!rows.length) {
            return [];
        }

        const hint = [
            input.name || '',
            input.id || '',
            input.placeholder || '',
            input.getAttribute('aria-label') || '',
        ].join(' ').toLowerCase();

        const values = [];

        if (/year|period/.test(hint)) {
            rows.forEach((row) => {
                extractRowLines(row).forEach((entry) => {
                    const matches = entry.match(/\b(19|20)\d{2}\b/g) || [];
                    matches.forEach((match) => values.push(match));
                });
            });

            return uniqueValues(values, 12);
        }

        const explicitValues = rows.flatMap((row) => extractExplicitRowSuggestions(row));
        if (explicitValues.length) {
            return uniqueValues(explicitValues, 24);
        }

        rows.forEach((row) => {
            extractPrimaryColumnSuggestions(row).forEach((entry) => values.push(entry));
        });

        if (/employee|name/.test(hint)) {
            return uniqueValues(values.filter((value) => /[a-z]/i.test(value)), 24);
        }

        if (values.length) {
            return uniqueValues(values, 24);
        }

        rows.forEach((row) => {
            extractRowLines(row).forEach((entry) => values.push(entry));
        });

        return uniqueValues(values, 24);
    };

    const closeOpenPanels = (exceptInput = null) => {
        toolbarInputs.forEach((candidate) => {
            if (!(candidate instanceof HTMLInputElement) || candidate === exceptInput) {
                return;
            }

            const panelId = candidate.dataset.toolbarAutocompletePanelId || '';
            if (!panelId) return;

            const panel = document.getElementById(panelId);
            if (!(panel instanceof HTMLElement)) return;

            panel.hidden = true;
            candidate.setAttribute('aria-expanded', 'false');
        });
    };

    const ensurePanel = (input, index) => {
        const panelId = input.dataset.toolbarAutocompletePanelId || `${input.id || input.name || 'toolbar-search'}-autocomplete-panel-${index}`;
        input.dataset.toolbarAutocompletePanelId = panelId;

        let panel = document.getElementById(panelId);
        if (!(panel instanceof HTMLElement)) {
            panel = document.createElement('div');
            panel.id = panelId;
            panel.className = 'ui-toolbar-autocomplete';
            panel.setAttribute('role', 'listbox');
            panel.hidden = true;
            document.body.appendChild(panel);
        }

        return panel;
    };

    const positionPanel = (input, panel) => {
        const rect = input.getBoundingClientRect();
        panel.style.width = `${rect.width}px`;
        panel.style.left = `${window.scrollX + rect.left}px`;
        panel.style.top = `${window.scrollY + rect.bottom + 6}px`;
    };

    toolbarInputs.forEach((input, index) => {
        if (!isManagedSearchInput(input) || input.dataset.toolbarAutocomplete === '1') {
            return;
        }

        const panel = ensurePanel(input, index);
        input.removeAttribute('list');
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('aria-haspopup', 'listbox');
        input.setAttribute('aria-expanded', 'false');

        const syncSuggestions = () => {
            const suggestions = buildSuggestions(input);
            if (!suggestions.length) {
                panel.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                return;
            }

            panel.innerHTML = suggestions
                .map((value) => {
                    const escaped = String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                    return `<button type="button" class="ui-toolbar-autocomplete__item" data-autocomplete-value="${escaped}" role="option">${escaped}</button>`;
                })
                .join('');

            positionPanel(input, panel);
            panel.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };

        input.addEventListener('click', () => {
            closeOpenPanels(input);
            syncSuggestions();
        });
        input.addEventListener('input', () => {
            closeOpenPanels(input);
            syncSuggestions();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                panel.hidden = true;
                input.setAttribute('aria-expanded', 'false');
            }
        });

        panel.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        panel.addEventListener('click', (event) => {
            const button = event.target instanceof HTMLElement
                ? event.target.closest('.ui-toolbar-autocomplete__item')
                : null;

            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            input.value = button.dataset.autocompleteValue || '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            panel.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.focus();
        });

        input.dataset.toolbarAutocomplete = '1';
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Node)) return;

        toolbarInputs.forEach((input) => {
            if (!(input instanceof HTMLInputElement)) return;
            const panelId = input.dataset.toolbarAutocompletePanelId || '';
            if (!panelId) return;
            const panel = document.getElementById(panelId);
            if (!(panel instanceof HTMLElement)) return;

            if (input.contains(target) || panel.contains(target)) {
                return;
            }

            panel.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        });
    });

    window.addEventListener('resize', () => {
        toolbarInputs.forEach((input) => {
            if (!(input instanceof HTMLInputElement)) return;
            const panelId = input.dataset.toolbarAutocompletePanelId || '';
            const panel = panelId ? document.getElementById(panelId) : null;
            if (!(panel instanceof HTMLElement) || panel.hidden) return;
            positionPanel(input, panel);
        });
    });

    window.addEventListener('scroll', () => {
        toolbarInputs.forEach((input) => {
            if (!(input instanceof HTMLInputElement)) return;
            const panelId = input.dataset.toolbarAutocompletePanelId || '';
            const panel = panelId ? document.getElementById(panelId) : null;
            if (!(panel instanceof HTMLElement) || panel.hidden) return;
            positionPanel(input, panel);
        });
    }, true);
}

function initStandardTableToolbarAutoSubmit() {
    const toolbars = document.querySelectorAll('form.ui-table-standard-toolbar');
    if (!toolbars.length) return;

    const compactToolbarMedia = window.matchMedia ? window.matchMedia('(max-width: 991.98px)') : null;

    const isManagedField = (field) => {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
            return false;
        }

        if (
            field.type === 'hidden' ||
            field.classList.contains('select2-search__field') ||
            field.classList.contains('select2-hidden-accessible') ||
            field.closest('.select2-container') ||
            String(field.getAttribute('aria-controls') || '').startsWith('select2-')
        ) {
            return false;
        }

        return true;
    };

    const submitForm = (form, submitter = null) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter || undefined);
            return;
        }

        form.submit();
    };

    toolbars.forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.dataset.standardToolbarBound === '1') {
            return;
        }

        const method = String(form.getAttribute('method') || 'GET').toUpperCase();
        if (method !== 'GET') {
            return;
        }

        const usesCompactFilterToggle = Boolean(
            form.querySelector('[data-coreui-toggle="collapse"][data-coreui-target*="FiltersCollapse"], [data-toggle="collapse"][data-target*="FiltersCollapse"]')
        );

        const submitButtons = Array.from(form.querySelectorAll('.ui-table-standard-toolbar__submit'));
        const searchInputs = Array.from(form.querySelectorAll('input[type="search"], input[type="text"]'))
            .filter(isManagedField);
        const selects = Array.from(form.querySelectorAll('select')).filter(isManagedField);

        submitButtons.forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                submitForm(form, button);
            });
        });

        if (!searchInputs.length && !selects.length) {
            return;
        }

        const shouldAutoSubmit = () => {
            if (!usesCompactFilterToggle) {
                return true;
            }

            if (!compactToolbarMedia) {
                return true;
            }

            return !compactToolbarMedia.matches;
        };

        let timer = null;
        const scheduleSubmit = (submitter = null) => {
            if (!shouldAutoSubmit()) {
                return;
            }

            if (timer) {
                clearTimeout(timer);
            }

            timer = window.setTimeout(() => {
                timer = null;
                submitForm(form, submitter);
            }, 250);
        };

        searchInputs.forEach((input) => {
            input.addEventListener('input', () => {
                scheduleSubmit(input);
            });

            input.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                submitForm(form, input);
            });
        });

        selects.forEach((select) => {
            select.addEventListener('change', () => {
                if (!shouldAutoSubmit()) {
                    return;
                }

                submitForm(form, select);
            });
        });

        form.dataset.standardToolbarBound = '1';
    });
}

function initSearchFieldTreatment() {
    const searchInputs = Array.from(document.querySelectorAll(
        'input[type="search"], ' +
        'input[id*="Search"], ' +
        'input[id*="search"], ' +
        'input[name*="search"], ' +
        'input[name*="Search"]'
    )).filter((input) => input instanceof HTMLInputElement);

    const normalizePlaceholder = (value) => {
        const original = String(value || '').trim();
        if (!original) return '';

        const stripped = original
            .replace(/^\s*search\b[\s:.-]*/i, '')
            .replace(/^\s*[,:.-]+\s*/, '')
            .trim();

        if (!stripped) {
            return '';
        }

        return stripped.charAt(0).toUpperCase() + stripped.slice(1);
    };

    const isManagedSearchInput = (input) => {
        if (!(input instanceof HTMLInputElement)) {
            return false;
        }

        if (
            input.type === 'hidden' ||
            input.classList.contains('select2-search__field') ||
            input.classList.contains('select2-hidden-accessible') ||
            input.closest('.select2-container') ||
            String(input.getAttribute('aria-controls') || '').startsWith('select2-')
        ) {
            return false;
        }

        return true;
    };

    searchInputs.forEach((input) => {
        if (!isManagedSearchInput(input)) {
            return;
        }

        if (input.placeholder) {
            input.placeholder = normalizePlaceholder(input.placeholder);
        }

        if (input.closest('.offboarding-search-input, .doc-search-input-wrap, .input-group')) {
            return;
        }

        input.classList.add('hrms-search-field');
    });
}

function initThemeToggles() {
    const storageKey = 'hrms-theme';
    const compactKey = 'hrms-compact';
    const densityKey = 'hrms-density';
    const reducedMotionKey = 'hrms-reduced-motion';
    const body = document.body;
    const navbar = document.querySelector('.app-header, .header');
    const themeModeSelect = document.getElementById('themeModeSelect');
    const compactSwitch = document.getElementById('sidebarCompactSwitch');
    const densitySelect = document.getElementById('interfaceDensitySelect');
    const reduceMotionSwitch = document.getElementById('reduceMotionSwitch');

    const meta = document.querySelector('meta[name="hrms-user-id"]');
    const userId = meta ? meta.getAttribute('content') : '';
    const scopedThemeKey = userId ? `${storageKey}-${userId}` : storageKey;
    const scopedDensityKey = userId ? `${densityKey}-${userId}` : densityKey;
    const scopedReducedMotionKey = userId ? `${reducedMotionKey}-${userId}` : reducedMotionKey;
    const systemThemeMedia = window.matchMedia
        ? window.matchMedia('(prefers-color-scheme: dark)')
        : null;

    function readStoredThemeMode() {
        const scopedTheme = localStorage.getItem(scopedThemeKey);
        if (scopedTheme === 'light' || scopedTheme === 'dark' || scopedTheme === 'system') {
            return scopedTheme;
        }

        const legacyTheme = localStorage.getItem(storageKey);
        if (legacyTheme === 'light' || legacyTheme === 'dark' || legacyTheme === 'system') {
            if (scopedThemeKey !== storageKey) {
                localStorage.setItem(scopedThemeKey, legacyTheme);
            }
            return legacyTheme;
        }

        return 'system';
    }

    function resolveThemeMode(themeMode) {
        if (themeMode === 'dark') {
            return true;
        }

        if (themeMode === 'system') {
            return Boolean(systemThemeMedia && systemThemeMedia.matches);
        }

        return false;
    }

    function applyTheme(themeMode) {
        const normalizedThemeMode = ['light', 'dark', 'system'].includes(themeMode)
            ? themeMode
            : 'system';
        const isDark = resolveThemeMode(normalizedThemeMode);
        document.documentElement.classList.toggle('dark-mode', isDark);
        body.classList.toggle('dark-mode', isDark);
        if (navbar) {
            navbar.classList.toggle('navbar-dark', isDark);
            navbar.classList.toggle('navbar-light', !isDark);
            navbar.classList.toggle('navbar-white', !isDark);
        }
        if (themeModeSelect) {
            themeModeSelect.value = normalizedThemeMode;
        }
    }

    const defaultThemeMode = readStoredThemeMode();
    applyTheme(defaultThemeMode);

    if (themeModeSelect) {
        themeModeSelect.addEventListener('change', function () {
            const themeMode = this.value === 'light' || this.value === 'dark' || this.value === 'system'
                ? this.value
                : 'system';
            applyTheme(themeMode);
            localStorage.setItem(scopedThemeKey, themeMode);
            localStorage.setItem(storageKey, themeMode);
        });
    }

    if (systemThemeMedia && typeof systemThemeMedia.addEventListener === 'function') {
        systemThemeMedia.addEventListener('change', function () {
            const activeTheme = readStoredThemeMode();
            if (activeTheme === 'system') {
                applyTheme('system');
            }
        });
    }

    function applyCompact(isCompact) {
        document.documentElement.classList.toggle('nav-compact', isCompact);
        body.classList.toggle('nav-compact', isCompact);
        document.querySelectorAll('.sidebar-nav').forEach((el) => el.classList.toggle('nav-compact', isCompact));
        if (compactSwitch) compactSwitch.checked = isCompact;
    }

    const storedCompact = localStorage.getItem(compactKey);
    const defaultCompact = storedCompact === null ? true : storedCompact === 'on';
    applyCompact(defaultCompact);
    if (storedCompact === null) localStorage.setItem(compactKey, 'on');

    if (compactSwitch) {
        compactSwitch.addEventListener('change', function () {
            const isCompact = this.checked;
            applyCompact(isCompact);
            localStorage.setItem(compactKey, isCompact ? 'on' : 'off');
        });
    }

    function applyDensity(densityMode) {
        const isCompactDensity = densityMode === 'compact';
        document.documentElement.classList.toggle('hrms-density-compact', isCompactDensity);
        body.classList.toggle('hrms-density-compact', isCompactDensity);
        if (densitySelect) {
            densitySelect.value = isCompactDensity ? 'compact' : 'comfortable';
        }
    }

    const storedDensity = localStorage.getItem(scopedDensityKey);
    applyDensity(storedDensity === 'compact' ? 'compact' : 'comfortable');

    if (densitySelect) {
        densitySelect.addEventListener('change', function () {
            const densityMode = this.value === 'compact' ? 'compact' : 'comfortable';
            applyDensity(densityMode);
            localStorage.setItem(scopedDensityKey, densityMode);
        });
    }

    function applyReducedMotion(isReducedMotion) {
        document.documentElement.classList.toggle('hrms-reduce-motion', isReducedMotion);
        body.classList.toggle('hrms-reduce-motion', isReducedMotion);
        if (reduceMotionSwitch) {
            reduceMotionSwitch.checked = isReducedMotion;
        }
    }

    const storedReducedMotion = localStorage.getItem(scopedReducedMotionKey) === 'on';
    applyReducedMotion(storedReducedMotion);

    if (reduceMotionSwitch) {
        reduceMotionSwitch.addEventListener('change', function () {
            const isReducedMotion = this.checked;
            applyReducedMotion(isReducedMotion);
            localStorage.setItem(scopedReducedMotionKey, isReducedMotion ? 'on' : 'off');
        });
    }
}

function initSidebarCollapsePersistence() {
    const key = 'hrms-sidebar-collapsed';
    const meta = document.querySelector('meta[name="hrms-user-id"]');
    const userId = meta ? meta.getAttribute('content') : '';
    const scopedKey = userId ? `${key}-${userId}` : key;
    const body = document.body;

    const stored = localStorage.getItem(scopedKey) === '1';
    body.classList.toggle('sidebar-collapse', stored);
    if (stored) body.classList.remove('sidebar-open');

    document.addEventListener('hrms:sidebar-toggled', () => {
        localStorage.setItem(scopedKey, body.classList.contains('sidebar-collapse') ? '1' : '0');
    });
}

function initCollapsedSidebarTooltips() {
    if (!$ || !$.fn.tooltip) return;

    function navLabel(link) {
        const label = link.querySelector('.nav-label');
        if (!label) return '';
        const clone = label.cloneNode(true);
        clone.querySelectorAll('.badge').forEach((el) => el.remove());
        return (clone.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function syncTooltips() {
        const isCollapsed = document.body.classList.contains('sidebar-collapse')
            && !document.body.classList.contains('sidebar-open');
        const $links = $('.app-sidebar .sidebar-nav .nav-link, .sidebar .sidebar-nav .nav-link');

        $links.each(function () {
            const label = navLabel(this);
            if (!label) return;
            const $link = $(this);

            if ($link.data('bs.tooltip')) {
                $link.tooltip('dispose');
            }

            if (isCollapsed) {
                $link.attr('data-sidebar-tooltip', label)
                    .attr('data-toggle', 'tooltip')
                    .attr('data-bs-toggle', 'tooltip')
                    .attr('data-placement', 'right')
                    .attr('data-bs-placement', 'right')
                    .attr('data-container', 'body');
                $link.tooltip({
                    title: label,
                    trigger: 'hover focus',
                    container: 'body',
                    boundary: 'window',
                    placement: 'right',
                });
            } else {
                $link.removeAttr('data-sidebar-tooltip data-toggle data-bs-toggle data-placement data-bs-placement data-container data-original-title title');
            }
        });
    }

    syncTooltips();
    document.addEventListener('hrms:sidebar-toggled', syncTooltips);
}

function initMobileBottomNav() {
    const nav = document.querySelector('.mobile-bottom-nav');
    if (!nav) return;

    if (nav.parentElement !== document.body) {
        document.body.appendChild(nav);
    }

    const moreWrap = nav.querySelector('.mobile-bottom-nav__more');
    const toggle = nav.querySelector('[data-mobile-bottom-nav-toggle="1"]');
    const menu = nav.querySelector('.mobile-bottom-nav__menu');
    const links = Array.from(nav.querySelectorAll('a[data-mobile-bottom-nav-link="1"]'));
    const modalTriggers = Array.from(nav.querySelectorAll('[data-coreui-toggle="modal"], [data-bs-toggle="modal"]'));
    if (!(moreWrap instanceof HTMLElement) || !(toggle instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) {
        return;
    }

    let lastNavActivationAt = 0;

    const navigateTo = (href) => {
        if (!href || href === '#') return;
        closeMenu();
        window.location.href = href;
    };

    const setOpen = (open) => {
        moreWrap.classList.toggle('show', open);
        menu.classList.toggle('show', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const closeMenu = () => setOpen(false);

    const handleNavActivation = (event) => {
        const link = event.currentTarget;
        if (!(link instanceof HTMLAnchorElement)) return;

        const href = link.getAttribute('href');
        if (!href || href === '#') return;

        event.preventDefault();
        event.stopPropagation();
        lastNavActivationAt = Date.now();
        navigateTo(href);
    };

    if (document.documentElement.dataset.mobileBottomNavBound !== '1') {
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const clickedToggle = target?.closest('[data-mobile-bottom-nav-toggle="1"]');
            if (clickedToggle === toggle) {
                event.preventDefault();
                event.stopPropagation();
                const isOpen = menu.classList.contains('show');
                setOpen(!isOpen);
                return;
            }

            const navLink = target?.closest('.mobile-bottom-nav a[data-mobile-bottom-nav-link="1"]');
            if (navLink instanceof HTMLAnchorElement) {
                closeMenu();
                return;
            }

            const clickedMenuItem = target?.closest('.mobile-bottom-nav__menu .dropdown-item');
            if (clickedMenuItem) {
                closeMenu();
                return;
            }

            if (!target?.closest('.mobile-bottom-nav__more')) {
                closeMenu();
            }
        }, true);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        ['show.bs.modal', 'show.coreui.modal'].forEach((eventName) => {
            document.addEventListener(eventName, closeMenu, true);
        });

        document.documentElement.dataset.mobileBottomNavBound = '1';
    }

    links.forEach((link) => {
        const activationEvent = window.PointerEvent ? 'pointerup' : 'touchend';

        link.removeEventListener(activationEvent, handleNavActivation);
        link.removeEventListener('click', handleNavActivation);

        link.addEventListener(activationEvent, handleNavActivation, { passive: false });
        link.addEventListener('click', (event) => {
            if (Date.now() - lastNavActivationAt < 800) {
                event.preventDefault();
                return;
            }
            handleNavActivation(event);
        });
    });

    modalTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            closeMenu();
        });
    });

    setOpen(false);
}

function initSidebarShell() {
    const body = document.body;
    const sidebar = document.querySelector('.app-sidebar, .sidebar');
    if (!body || !sidebar) return;

    const syncSidebarBodyState = function () {
        if (window.innerWidth >= 992) {
            body.classList.remove('sidebar-open');
        }
        emitSidebarStateChange();
    };

    const toggleSidebar = function () {
        if (window.innerWidth < 992) {
            body.classList.toggle('sidebar-open');
        } else {
            body.classList.toggle('sidebar-collapse');
            body.classList.remove('sidebar-open');
        }
        syncSidebarBodyState();
    };

    const closeSidebar = function () {
        body.classList.remove('sidebar-open');
        syncSidebarBodyState();
    };

    document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            toggleSidebar();
        });
    });

    document.querySelectorAll('[data-sidebar-close]').forEach((toggle) => {
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            closeSidebar();
        });
    });

    document.querySelectorAll('.app-sidebar .nav-group-toggle, .sidebar .nav-group-toggle').forEach((link) => {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const item = link.closest('.nav-group');
            const treeRoot = item?.parentElement;
            const willOpen = !item.classList.contains('show');

            if (treeRoot && (treeRoot.classList.contains('sidebar-nav') || treeRoot.classList.contains('nav-group-items'))) {
                Array.from(treeRoot.children).forEach((sibling) => {
                    if (sibling !== item) sibling.classList.remove('show');
                });
            }

            item.classList.toggle('show', willOpen);
            emitSidebarStateChange();
        });
    });

    document.addEventListener('click', function (event) {
        if (window.innerWidth >= 992) return;
        if (!body.classList.contains('sidebar-open')) return;
        if (sidebar.contains(event.target)) return;
        if (event.target.closest('[data-sidebar-toggle]')) return;

        closeSidebar();
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });

    syncSidebarBodyState();
}

function initTransactionConfirmations() {
    const modalEl = document.getElementById('transactionConfirmModal');
    if (!modalEl) {
        window.hrmsConfirmAction = async ({ message = 'Proceed with this action?' } = {}) => window.confirm(message);
        return;
    }

    const titleEl = document.getElementById('transactionConfirmTitle');
    const messageEl = document.getElementById('transactionConfirmMessage');
    const proceedBtn = document.getElementById('transactionConfirmProceed');
    const cancelBtn = document.getElementById('transactionConfirmCancel');
    const dismissButtons = Array.from(modalEl.querySelectorAll('[data-dismiss="modal"], [data-bs-dismiss="modal"], [data-coreui-dismiss="modal"]'));
    let resolver = null;
    let manualBackdrop = null;

    const showModalFallback = () => {
        if (!(modalEl instanceof HTMLElement)) {
            return;
        }

        if (!(manualBackdrop instanceof HTMLElement)) {
            manualBackdrop = document.createElement('div');
            manualBackdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(manualBackdrop);
        } else {
            manualBackdrop.classList.add('show');
            manualBackdrop.style.display = 'block';
        }

        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.classList.add('show');
        document.body.classList.add('modal-open');
    };

    const hideModalFallback = () => {
        if (!(modalEl instanceof HTMLElement)) {
            return;
        }

        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');

        if (manualBackdrop instanceof HTMLElement) {
            manualBackdrop.classList.remove('show');
            manualBackdrop.style.display = 'none';
            manualBackdrop.remove();
            manualBackdrop = null;
        }

        document.body.classList.remove('modal-open');
    };

    const getBootstrapModalInstance = (options = {}) => {
        const BootstrapModal = window.bootstrap?.Modal;
        if (!BootstrapModal) {
            return null;
        }

        if (typeof BootstrapModal.getOrCreateInstance === 'function') {
            return BootstrapModal.getOrCreateInstance(modalEl, options);
        }

        if (typeof BootstrapModal.getInstance === 'function') {
            return BootstrapModal.getInstance(modalEl) || new BootstrapModal(modalEl, options);
        }

        if (typeof BootstrapModal === 'function') {
            return new BootstrapModal(modalEl, options);
        }

        return null;
    };

    const showModal = () => {
        if (typeof window.hrmsShowModal === 'function') {
            window.hrmsShowModal(modalEl, {
                backdrop: 'static',
                keyboard: false,
            });
            return;
        }

        const bootstrapModal = getBootstrapModalInstance({
            backdrop: 'static',
            keyboard: false,
        });
        if (bootstrapModal && typeof bootstrapModal.show === 'function') {
            bootstrapModal.show();
            return;
        }

        if ($?.fn?.modal) {
            $(modalEl).modal({
                backdrop: 'static',
                keyboard: false,
                show: true,
            });
            return;
        }

        showModalFallback();
    };

    const hideModal = () => {
        if (typeof window.hrmsHideModal === 'function') {
            window.hrmsHideModal(modalEl);
            return;
        }

        const bootstrapModal = getBootstrapModalInstance();
        if (bootstrapModal && typeof bootstrapModal.hide === 'function') {
            bootstrapModal.hide();
            return;
        }

        if ($?.fn?.modal) {
            $(modalEl).modal('hide');
            return;
        }

        hideModalFallback();
    };

    const resolveModal = (result) => {
        if (!resolver) return;
        const currentResolver = resolver;
        resolver = null;
        currentResolver(result);
    };

    proceedBtn?.addEventListener('click', () => {
        resolveModal(true);
        hideModal();
    });

    cancelBtn?.addEventListener('click', () => {
        resolveModal(false);
        hideModal();
    });

    dismissButtons.forEach((button) => {
        if (!(button instanceof HTMLElement)) {
            return;
        }

        button.addEventListener('click', () => {
            resolveModal(false);
            hideModal();
        });
    });

    if ($) {
        $(modalEl).on('hidden.bs.modal hidden.coreui.modal', function () {
            resolveModal(false);
        });
    } else {
        modalEl.addEventListener('hidden.bs.modal', () => resolveModal(false));
        modalEl.addEventListener('hidden.coreui.modal', () => resolveModal(false));
    }

    window.hrmsConfirmAction = function ({
        title = 'Confirm Action',
        message = 'Proceed with this action?',
        confirmLabel = 'Proceed',
        variant = 'primary',
    } = {}) {
        if (resolver) {
            resolveModal(false);
        }

        if (titleEl) titleEl.textContent = title;
        if (messageEl) messageEl.textContent = message;
        if (proceedBtn) {
            proceedBtn.textContent = confirmLabel;
            proceedBtn.classList.remove('btn-primary', 'btn-danger', 'btn-warning', 'btn-success', 'btn-info', 'btn-secondary');
            proceedBtn.classList.add(
                variant === 'danger'
                    ? 'btn-danger'
                    : variant === 'warning'
                        ? 'btn-warning'
                        : variant === 'success'
                            ? 'btn-success'
                            : variant === 'info'
                                ? 'btn-info'
                                : variant === 'secondary'
                                    ? 'btn-secondary'
                                    : 'btn-primary'
            );
        }

        modalEl.classList.toggle('hrms-confirm-modal--danger', variant === 'danger');

        return new Promise((resolve) => {
            resolver = resolve;
            showModal();
        });
    };

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.confirmBypass === '1') {
            if (form instanceof HTMLFormElement) {
                form.dataset.confirmBypass = '';
            }
            return;
        }

        let message = form.dataset.confirmMessage || '';
        let title = form.dataset.confirmTitle || 'Confirm Action';
        let confirmLabel = form.dataset.confirmLabel || 'Proceed';
        let variant = form.dataset.confirmVariant || 'primary';

        if (!message && form.classList.contains('spms-confirm-form')) {
            message = form.dataset.spmsConfirm || 'Proceed with this SPMS action?';
            title = 'Confirm SPMS Action';
        }

        if (!message && form.classList.contains('confirm-approve-form')) {
            message = 'Approve this leave request?';
            title = 'Confirm Approval';
            confirmLabel = 'Approve';
        }

        if (!message) {
            return;
        }

        event.preventDefault();
        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;

        window.hrmsConfirmAction({ title, message, confirmLabel, variant }).then((confirmed) => {
            if (!confirmed) return;
            form.dataset.confirmBypass = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter || undefined);
            } else {
                form.submit();
            }
        });
    }, true);

    document.addEventListener('click', function (event) {
        const button = event.target instanceof Element
            ? event.target.closest(
                '[data-confirm-submit], [data-confirm-text], [data-confirm-message], ' +
                'form[data-confirm-message] button[type="submit"], ' +
                'form[data-confirm-message] input[type="submit"], ' +
                'form.spms-confirm-form button[type="submit"], ' +
                'form.confirm-approve-form button[type="submit"]'
            )
            : null;

        if (!(button instanceof HTMLElement) || button.dataset.confirmBypass === '1') {
            if (button instanceof HTMLElement) {
                button.dataset.confirmBypass = '';
            }
            return;
        }

        if (
            button.matches('[data-coreui-dismiss="modal"], [data-bs-dismiss="modal"], [data-dismiss="modal"]')
            || button.closest('.filepond--root')
        ) {
            return;
        }

        if (button.hasAttribute('data-confirm-submit')) {
            const selector = button.getAttribute('data-confirm-submit') || '';
            const form = selector ? document.querySelector(selector) : null;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            event.preventDefault();
            window.hrmsConfirmAction({
                title: button.dataset.confirmTitle || 'Confirm Action',
                message: button.dataset.confirmMessage || 'Proceed with this action?',
                confirmLabel: button.dataset.confirmLabel || 'Proceed',
                variant: button.dataset.confirmVariant || 'primary',
            }).then((confirmed) => {
                if (!confirmed) return;
                form.dataset.confirmBypass = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
            return;
        }

        const parentForm = button.form instanceof HTMLFormElement ? button.form : null;
        let message = button.dataset.confirmText || button.dataset.confirmMessage || '';
        let title = button.dataset.confirmTitle || 'Confirm Action';
        let confirmLabel = button.dataset.confirmLabel || 'Proceed';
        let variant = button.dataset.confirmVariant || 'primary';

        if (!message && parentForm instanceof HTMLFormElement) {
            message = parentForm.dataset.confirmMessage || '';
            title = parentForm.dataset.confirmTitle || title;
            confirmLabel = parentForm.dataset.confirmLabel || confirmLabel;
            variant = parentForm.dataset.confirmVariant || variant;

            if (!message && parentForm.classList.contains('spms-confirm-form')) {
                message = parentForm.dataset.spmsConfirm || 'Proceed with this SPMS action?';
                title = 'Confirm SPMS Action';
            }

            if (!message && parentForm.classList.contains('confirm-approve-form')) {
                message = 'Approve this leave request?';
                title = 'Confirm Approval';
                confirmLabel = 'Approve';
            }
        }

        if (!message) {
            return;
        }

        event.preventDefault();

        window.hrmsConfirmAction({
            title,
            message,
            confirmLabel,
            variant,
        }).then((confirmed) => {
            if (!confirmed) return;

            if (parentForm instanceof HTMLFormElement) {
                parentForm.dataset.confirmBypass = '1';
                button.dataset.confirmBypass = '1';
                if (typeof parentForm.requestSubmit === 'function') {
                    parentForm.requestSubmit(button);
                } else {
                    button.click();
                }
            }
        });
    }, true);
}

function runUiInitializer(name, initializer) {
    try {
        initializer();
    } catch (error) {
        console.error(`[hrms-ui] ${name} failed`, error);
    }
}

function initToasts() {
    if (window.__HRMS_TOASTS_INITIALIZED) return;
    window.__HRMS_TOASTS_INITIALIZED = true;

    const container = document.getElementById('toast-container');
    if (!container) return;

    window.showToast = function (type, message) {
        if (type === 'danger') type = 'error';

        const toast = document.createElement('div');
        const bgClass = type === 'error' ? 'bg-danger' : (type === 'success' ? 'bg-success' : (type === 'warning' ? 'bg-warning' : 'bg-info'));
        toast.className = `toast ${bgClass} text-white fade`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.setAttribute('data-delay', '5000');

        toast.innerHTML = `
            <div class="toast-header bg-white">
                <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                <button type="button" class="btn-close ms-2 mb-1" data-dismiss="toast" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body fw-bold">${message}</div>
        `;

        container.appendChild(toast);

        if ($ && $.fn.toast) {
            $(toast).toast({ delay: 5000 });
            $(toast).toast('show');
            $(toast).on('hidden.bs.toast', function () { $(this).remove(); });
        }
    };
}

function initFirstLoginPasswordNotice() {
    const body = document.body;
    if (!body || body.dataset.showPasswordChangeNotice !== '1') return;

    const noticeModalEl = document.getElementById('firstLoginPasswordNoticeModal');
    const profileModalEl = document.getElementById('profileEditModal');
    const openBtn = document.getElementById('openProfileEditFromNotice');
    const currentPasswordInput = document.getElementById('profile-current-password');
    if (!noticeModalEl || !openBtn) return;

    const showModal = (modalEl) => {
        if (!modalEl) return;
        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }
        if ($) $(modalEl).modal('show');
    };

    const hideModal = (modalEl) => {
        if (!modalEl) return;
        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            return;
        }
        if ($) $(modalEl).modal('hide');
    };

    showModal(noticeModalEl);
    openBtn.addEventListener('click', function () {
        hideModal(noticeModalEl);
        showModal(profileModalEl);
        if (currentPasswordInput) currentPasswordInput.focus();
    });
}

function initLazyIframePreviewModals() {
    const modalSelector = '#documentPreviewModal, #filePreviewModal';
    const triggerSelector = [
        '[data-target="#documentPreviewModal"][data-file]',
        '[data-coreui-target="#documentPreviewModal"][data-file]',
        '[data-target="#filePreviewModal"][data-file]',
        '[data-coreui-target="#filePreviewModal"][data-file]',
    ].join(', ');

    const pendingTriggers = new WeakMap();

    const getTitleTarget = (modalEl) => modalEl.querySelector('.modal-title');

    const resetPreview = (modalEl) => {
        if (!(modalEl instanceof HTMLElement)) return;
        const iframe = modalEl.querySelector('iframe');
        if (!(iframe instanceof HTMLIFrameElement)) return;
        iframe.setAttribute('src', 'about:blank');
    };

    const populatePreview = (modalEl, triggerEl) => {
        if (!(modalEl instanceof HTMLElement) || !(triggerEl instanceof HTMLElement)) {
            return;
        }

        const iframe = modalEl.querySelector('iframe');
        if (!(iframe instanceof HTMLIFrameElement)) return;

        const fileUrl = String(triggerEl.getAttribute('data-file') || '').trim();
        const title = String(triggerEl.getAttribute('data-title') || iframe.getAttribute('title') || 'Document Preview').trim();
        const titleTarget = getTitleTarget(modalEl);

        if (titleTarget instanceof HTMLElement && title !== '') {
            titleTarget.textContent = title;
        }

        iframe.setAttribute('loading', 'lazy');
        iframe.setAttribute('src', fileUrl || 'about:blank');
    };

    if ($) {
        $(document)
            .off('click.hrmsLazyPreview', triggerSelector)
            .on('click.hrmsLazyPreview', triggerSelector, function () {
                const targetSelector = this.getAttribute('data-coreui-target')
                    || this.getAttribute('data-target')
                    || '';
                const modalEl = targetSelector ? document.querySelector(targetSelector) : null;
                if (!(modalEl instanceof HTMLElement)) return;
                pendingTriggers.set(modalEl, this);
                populatePreview(modalEl, this);
            });

        $(modalSelector).each(function () {
            const $modal = $(this);
            if ($modal.data('lazy-preview-bound') === 1) {
                return;
            }

            resetPreview(this);

            $modal.on('show.bs.modal show.coreui.modal', function (event) {
                const triggerEl = event.relatedTarget instanceof HTMLElement
                    ? event.relatedTarget
                    : pendingTriggers.get(this);
                populatePreview(this, triggerEl);
            });

            $modal.on('hidden.bs.modal hidden.coreui.modal', function () {
                resetPreview(this);
            });

            $modal.data('lazy-preview-bound', 1);
        });

        return;
    }

    document.querySelectorAll(modalSelector).forEach((modalEl) => {
        resetPreview(modalEl);
    });

    document.addEventListener('click', function (event) {
        const triggerEl = event.target instanceof Element
            ? event.target.closest(triggerSelector)
            : null;
        if (!(triggerEl instanceof HTMLElement)) return;

        const targetSelector = triggerEl.getAttribute('data-coreui-target')
            || triggerEl.getAttribute('data-target')
            || '';
        const modalEl = targetSelector ? document.querySelector(targetSelector) : null;
        if (!(modalEl instanceof HTMLElement)) return;

        pendingTriggers.set(modalEl, triggerEl);
        populatePreview(modalEl, triggerEl);
    });

    ['show.bs.modal', 'show.coreui.modal'].forEach((eventName) => {
        document.addEventListener(eventName, function (event) {
            const modalEl = event.target;
            if (!(modalEl instanceof HTMLElement) || !modalEl.matches(modalSelector)) return;
            const triggerEl = event.relatedTarget instanceof HTMLElement
                ? event.relatedTarget
                : pendingTriggers.get(modalEl);
            populatePreview(modalEl, triggerEl);
        });
    });

    ['hidden.bs.modal', 'hidden.coreui.modal'].forEach((eventName) => {
        document.addEventListener(eventName, function (event) {
            const modalEl = event.target;
            if (!(modalEl instanceof HTMLElement) || !modalEl.matches(modalSelector)) return;
            resetPreview(modalEl);
        });
    });
}

function bindEligibilityContentRefresh() {
    document.addEventListener('hrms:eligibility-content-updated', function () {
        runUiInitializer('toolbar-select2', initToolbarSelect2);
        runUiInitializer('toolbar-autocomplete', initToolbarAutocomplete);
        runUiInitializer('employee-selects', initEmployeeSelects);
    });
}

function initLegacyModalBridge() {
    const getModalInstance = (modalEl, options = {}) => {
        const modalConstructors = [
            window.coreui?.Modal,
            window.bootstrap?.Modal,
        ].filter(Boolean);

        for (const ModalConstructor of modalConstructors) {
            if (typeof ModalConstructor.getOrCreateInstance === 'function') {
                return ModalConstructor.getOrCreateInstance(modalEl, options);
            }
            if (typeof ModalConstructor.getInstance === 'function') {
                return ModalConstructor.getInstance(modalEl) || new ModalConstructor(modalEl, options);
            }
            if (typeof ModalConstructor === 'function') {
                return new ModalConstructor(modalEl, options);
            }
        }

        return null;
    };

    const cleanupModalArtifacts = () => {
        const openModals = document.querySelectorAll('.modal.show');
        if (openModals.length > 0) {
            return;
        }

        document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
        document.documentElement.style.removeProperty('overflow');
    };

    window.hrmsShowModal = function (modalEl, options = {}) {
        if (!modalEl) return;

        const instance = getModalInstance(modalEl, options);
        if (instance && typeof instance.show === 'function') {
            instance.show();
            return;
        }

        if ($?.fn?.modal) {
            $(modalEl).modal({ show: true, ...options });
            return;
        }

        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        document.body.classList.add('modal-open');
    };

    window.hrmsHideModal = function (modalEl) {
        if (!modalEl) return;

        const instance = getModalInstance(modalEl);
        if (instance && typeof instance.hide === 'function') {
            instance.hide();
            return;
        }

        if ($?.fn?.modal) {
            $(modalEl).modal('hide');
            return;
        }

        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        cleanupModalArtifacts();
    };

    ['hidden.bs.modal', 'hidden.coreui.modal'].forEach((eventName) => {
        document.addEventListener(eventName, function () {
            window.setTimeout(cleanupModalArtifacts, 0);
        });
    });

    document.addEventListener('click', function (event) {
        const opener = event.target instanceof Element
            ? event.target.closest('[data-coreui-toggle="modal"][data-coreui-target], [data-toggle="modal"][data-target]')
            : null;

        if (opener instanceof HTMLElement) {
            const targetSelector = opener.getAttribute('data-coreui-target')
                || opener.getAttribute('data-target')
                || '';
            const modalEl = targetSelector ? document.querySelector(targetSelector) : null;
            if (modalEl instanceof HTMLElement) {
                event.preventDefault();
                window.hrmsShowModal(modalEl);
            }
            return;
        }

        const dismiss = event.target instanceof Element
            ? event.target.closest('[data-coreui-dismiss="modal"], [data-dismiss="modal"]')
            : null;

        if (dismiss instanceof HTMLElement) {
            const modalEl = dismiss.closest('.modal');
            if (modalEl instanceof HTMLElement) {
                event.preventDefault();
                window.hrmsHideModal(modalEl);
            }
        }
    });
}

function bindSelect2Refresh() {
    document.addEventListener('hrms:select2-ready', function () {
        runUiInitializer('select2', initSelect2);
        runUiInitializer('toolbar-select2', initToolbarSelect2);
        runUiInitializer('employee-selects', initEmployeeSelects);
    });
}

function bindOffcanvasSelect2Refresh() {
    const refreshOffcanvasSelects = (offcanvasEl) => {
        if (!(offcanvasEl instanceof HTMLElement) || !$ || !$.fn?.select2) {
            return;
        }

        offcanvasEl.querySelectorAll('.select2-hidden-accessible').forEach((selectEl) => {
            const $select = $(selectEl);
            if ($select.data('toolbar-select2-initialized') === 1) {
                $select.removeData('toolbar-select2-initialized');
            }
            if ($select.data('employee-select-initialized') === 1) {
                $select.removeData('employee-select-initialized');
            }
            $select.select2('destroy');
        });

        runUiInitializer('select2', initSelect2);
        runUiInitializer('toolbar-select2', initToolbarSelect2);
        runUiInitializer('employee-selects', initEmployeeSelects);
    };

    ['shown.bs.offcanvas', 'shown.coreui.offcanvas'].forEach((eventName) => {
        document.addEventListener(eventName, function (event) {
            refreshOffcanvasSelects(event.target);
        });
    });
}

function initModalActionSpinners() {
    const modalActionSelector = '.modal .btn';

    const isDismissAction = (button) => button.matches(
        '[data-coreui-dismiss="modal"], [data-bs-dismiss="modal"], [data-dismiss="modal"]'
    );

    const isSubmitAction = (button) => (
        button instanceof HTMLButtonElement &&
        button.type === 'submit' &&
        button.form instanceof HTMLFormElement
    );

    const setAnchorDisabledState = (button, disabled) => {
        if (!(button instanceof HTMLAnchorElement)) {
            return;
        }

        button.classList.toggle('disabled', disabled);
        button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        if (disabled) {
            button.dataset.modalActionTabindex = button.getAttribute('tabindex') || '';
            button.setAttribute('tabindex', '-1');
        } else if (button.dataset.modalActionTabindex !== undefined) {
            const originalTabindex = button.dataset.modalActionTabindex;
            if (originalTabindex === '') {
                button.removeAttribute('tabindex');
            } else {
                button.setAttribute('tabindex', originalTabindex);
            }
            delete button.dataset.modalActionTabindex;
        }
    };

    const setModalActionLoading = (button, loading) => {
        if (!(button instanceof HTMLElement)) {
            return;
        }

        if (loading) {
            if (button.dataset.modalActionLoading === '1') {
                return;
            }

            button.dataset.modalActionLoading = '1';
            button.classList.add('hrms-modal-action-loading');

            if (!button.dataset.modalActionOriginalWidth) {
                button.dataset.modalActionOriginalWidth = button.style.width || '';
            }

            if (!button.dataset.modalActionWidthLocked) {
                button.dataset.modalActionWidthLocked = `${button.getBoundingClientRect().width}px`;
            }

            button.style.width = button.dataset.modalActionWidthLocked;

            if (button instanceof HTMLButtonElement) {
                if (!button.dataset.originalHtml) {
                    button.dataset.originalHtml = button.innerHTML;
                }

                button.innerHTML = '<span class="spinner-border spinner-border-sm hrms-btn__spinner me-2" role="status" aria-hidden="true"></span><span class="hrms-btn__label">Loading</span>';
                button.disabled = true;
                return;
            }

            if (button instanceof HTMLAnchorElement) {
                if (!button.dataset.originalHtml) {
                    button.dataset.originalHtml = button.innerHTML;
                }

                button.innerHTML = '<span class="spinner-border spinner-border-sm hrms-btn__spinner me-2" role="status" aria-hidden="true"></span><span class="hrms-btn__label">Loading</span>';
                setAnchorDisabledState(button, true);
            }

            return;
        }

        button.classList.remove('hrms-modal-action-loading');
        delete button.dataset.modalActionLoading;

        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
        }

        if (button instanceof HTMLButtonElement) {
            button.disabled = false;
        } else if (button instanceof HTMLAnchorElement) {
            setAnchorDisabledState(button, false);
        }

        if (button.dataset.modalActionOriginalWidth !== undefined) {
            const originalWidth = button.dataset.modalActionOriginalWidth;
            if (originalWidth === '') {
                button.style.removeProperty('width');
            } else {
                button.style.width = originalWidth;
            }
        }

        delete button.dataset.modalActionOriginalWidth;
        delete button.dataset.modalActionWidthLocked;
    };

    const resetModalActionButtons = (modalEl) => {
        if (!(modalEl instanceof HTMLElement)) {
            return;
        }

        modalEl.querySelectorAll(modalActionSelector).forEach((button) => {
            setModalActionLoading(button, false);
        });
    };

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element
            ? event.target.closest(modalActionSelector)
            : null;

        if (!(button instanceof HTMLElement)) {
            return;
        }

        if (button.classList.contains('btn-close')) {
            return;
        }

        if (button.dataset.modalActionLoading === '1') {
            event.preventDefault();
            event.stopPropagation();
            return;
        }

        if (isSubmitAction(button)) {
            return;
        }

        setModalActionLoading(button, true);

        if (!isDismissAction(button)) {
            window.setTimeout(() => {
                if (!document.body.contains(button)) {
                    return;
                }

                const modalEl = button.closest('.modal');
                if (modalEl instanceof HTMLElement && modalEl.classList.contains('show')) {
                    setModalActionLoading(button, false);
                }
            }, 1200);
        }
    }, true);

    ['hidden.bs.modal', 'hidden.coreui.modal', 'shown.bs.modal', 'shown.coreui.modal'].forEach((eventName) => {
        document.addEventListener(eventName, (event) => {
            resetModalActionButtons(event.target);
        });
    });
}

onReady(function () {
    runUiInitializer('transaction-confirmations', initTransactionConfirmations);
    runUiInitializer('sidebar-shell', initSidebarShell);
    runUiInitializer('select2', initSelect2);
    runUiInitializer('toolbar-select2', initToolbarSelect2);
    runUiInitializer('employee-selects', initEmployeeSelects);
    runUiInitializer('toolbar-autocomplete', initToolbarAutocomplete);
    runUiInitializer('theme-toggles', initThemeToggles);
    runUiInitializer('sidebar-collapse-persistence', initSidebarCollapsePersistence);
    runUiInitializer('collapsed-sidebar-tooltips', initCollapsedSidebarTooltips);
    runUiInitializer('mobile-bottom-nav', initMobileBottomNav);
    runUiInitializer('toasts', initToasts);
    runUiInitializer('first-login-password-notice', initFirstLoginPasswordNotice);
    runUiInitializer('lazy-iframe-preview-modals', initLazyIframePreviewModals);
    runUiInitializer('legacy-modal-bridge', initLegacyModalBridge);
    runUiInitializer('eligibility-content-refresh', bindEligibilityContentRefresh);
    runUiInitializer('select2-refresh', bindSelect2Refresh);
    runUiInitializer('offcanvas-select2-refresh', bindOffcanvasSelect2Refresh);
    runUiInitializer('modal-action-spinners', initModalActionSpinners);
});


