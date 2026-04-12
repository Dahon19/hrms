import { $, bindToolbarInteractions, onReady, whenDataTableReady } from './utils';

function getEditPayload(button) {
    if (!button || !button.length) return {};
    const payload = button.data('edit');
    if (payload && typeof payload === 'object') return payload;
    if (typeof payload === 'string') {
        try {
            const parsed = JSON.parse(payload);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }
    return {};
}

function readButtonData(button, name) {
    if (!button || !button.length) return '';
    const directValue = button.attr(`data-${name}`);
    if (directValue !== undefined && directValue !== null && directValue !== '') {
        return directValue;
    }
    return button.data(name) ?? '';
}

function setGenderRestriction(targetSelector, value) {
    if (!$) return;
    const $target = $(targetSelector);
    if (!$target.length) return;
    const normalized = value === 'male' || value === 'female' ? String(value) : '';
    $target.val(normalized);
    const $wrap = $(`.doc-gender-checks[data-gender-target="${targetSelector}"]`);
    if (!$wrap.length) return;
    $wrap.find('input[type="checkbox"]').each(function () {
        const checked = String($(this).val()) === normalized;
        $(this).prop('checked', checked);
        $(this).closest('.doc-gender-check').toggleClass('is-selected', checked);
    });
}

const REQUIRED_DOC_NAMES = [
    'prc license professional regulation commission',
    'prc license',
    'drivers license',
    'nbi clearance',
    'police clearance',
    'barangay clearance',
    'healthmedical certificate',
    'health certificate',
    'medical certificate',
    'working permit aep for foreign employees',
    'working permit aep',
    'working permit',
    'aep',
    'security guard license',
    'first aid cpr certification red cross',
    'first aid cpr certification',
    'first aid certification',
    'cpr certification',
    'tesda national certificate nc',
    'tesda national certificate',
    'employment contract for cosjob order staff',
    'employment contract',
    'notarized affidavits',
    'notarized affidavit'
];

function normalizeDocName(name) {
    if (!name) return '';
    return name.toLowerCase().replace(/[^a-z0-9 ]/g, '').replace(/\s+/g, ' ').trim();
}

function toggleDateRequirements(docName, config) {
    const required = REQUIRED_DOC_NAMES.includes(normalizeDocName(docName));
    const issuedInput = $(config.issuedInput);
    const expiresInput = $(config.expiresInput);
    const issuedStar = $(config.issuedStar);
    const expiresStar = $(config.expiresStar);
    const issuedNote = $(config.issuedNote);
    const expiresNote = $(config.expiresNote);

    if (issuedInput.length) issuedInput.prop('required', required);
    if (expiresInput.length) expiresInput.prop('required', required);
    if (issuedStar.length) issuedStar.toggleClass('d-none', !required);
    if (expiresStar.length) expiresStar.toggleClass('d-none', !required);

    if (issuedNote.length) {
        issuedNote.text(required
            ? 'Required for specific 201 files to support expiry notifications.'
            : 'Optional. Leave blank if the document does not require issuance tracking.'
        );
    }
    if (expiresNote.length) {
        expiresNote.text(required
            ? 'Required for specific 201 files to support expiry notifications.'
            : 'Optional. Leave blank if the document does not expire.'
        );
    }
}

function initDocumentUploadModal() {
    if (!$) return;
    const $modal = $('#documentUploadModal');
    if (!$modal.length) return;

    const populateUploadModal = (button) => {
        const $button = $(button);
        let employeeId = '';
        let employeeName = '';
        let documentId = '';
        let documentName = '';

        if ($button && $button.length) {
            employeeId = $button.data('employee-id') || '';
            employeeName = $button.data('employee-name') || '';
            documentId = $button.data('document-id') || '';
            documentName = $button.data('document-name') || '';
        } else {
            employeeId = $('#upload_employee_id').val() || '';
            employeeName = $('#upload_employee_name_hidden').val() || '';
            documentId = $('#upload_document_id').val() || '';
            documentName = $('#upload_document_name').val() || '';
        }

        $('#upload_employee_id').val(employeeId);
        $('#upload_employee_name_hidden').val(employeeName);
        $('#upload_document_id').val(documentId);
        $('#upload_document_name').val(documentName);
        $('#upload_document_name_hidden').val(documentName);
        if (!$('#upload_issued_at').val()) $('#upload_issued_at').val('');
        if (!$('#upload_expires_at').val()) $('#upload_expires_at').val('');

        toggleDateRequirements(documentName, {
            issuedInput: '#upload_issued_at',
            expiresInput: '#upload_expires_at',
            issuedStar: '#upload_issued_required',
            expiresStar: '#upload_expires_required',
            issuedNote: '#upload_issued_note',
            expiresNote: '#upload_expires_note'
        });
    };

    $(document).on('click', '[data-target="#documentUploadModal"], [data-coreui-target="#documentUploadModal"]', function () {
        populateUploadModal(this);
    });

    $modal.on('show.bs.modal show.coreui.modal', function (event) {
        if (event.relatedTarget) {
            populateUploadModal(event.relatedTarget);
        } else {
            populateUploadModal(null);
        }
    });
}

function initCatalogEditModal() {
    if (!$) return;
    const $modal = $('#catalogEditModal');
    if (!$modal.length) return;

    const populateCatalogEditModal = (trigger) => {
        const button = $(trigger);
        const payload = getEditPayload(button);
        const updateUrl = button && button.length ? (payload.update_url || readButtonData(button, 'update-url') || '#') : ($('#catalog_edit_url').val() || '#');
        const name = button && button.length ? (payload.name || readButtonData(button, 'name') || '') : ($('#catalog_edit_name').val() || '');
        const gender = button && button.length ? (payload.gender || readButtonData(button, 'gender') || '') : ($('#catalog_edit_gender').val() || '');
        const categoryId = button && button.length ? (payload.category_id || readButtonData(button, 'category-id') || '') : ($('#catalog_edit_category').val() || '');
        const subcategoryId = button && button.length ? (payload.subcategory_id || readButtonData(button, 'subcategory-id') || '') : ($('#catalog_edit_subcategory').val() || '');

        $('#catalogEditForm').attr('action', updateUrl);
        $('#catalog_edit_url').val(updateUrl);
        $('#catalog_edit_name').val(name);
        setGenderRestriction('#catalog_edit_gender', String(gender));
        $('#catalog_edit_category').val(String(categoryId)).trigger('change');
        syncSubcategoryOptions($('#catalog_edit_category'), $('#catalog_edit_subcategory'));
        $('#catalog_edit_subcategory').val(String(subcategoryId)).trigger('change');
    };

    $(document).on('click', '[data-target="#catalogEditModal"], [data-coreui-target="#catalogEditModal"]', function () {
        populateCatalogEditModal(this);
    });

    $modal.on('show.bs.modal show.coreui.modal', function (event) {
        populateCatalogEditModal(event.relatedTarget || event.targetTrigger || null);
    });
}

function syncSubcategoryOptions($categorySelect, $subcategorySelect) {
    if (!$categorySelect.length || !$subcategorySelect.length) return;
    const categoryId = $categorySelect.val();

    $subcategorySelect.find('option').each(function () {
        const $option = $(this);
        const optionCategory = $option.data('category');
        if (!optionCategory) {
            $option.prop('hidden', false);
            return;
        }
        if (!categoryId) {
            $option.prop('hidden', true);
        } else {
            $option.prop('hidden', String(optionCategory) !== String(categoryId));
        }
    });

    const selected = $subcategorySelect.val();
    if (selected) {
        const $selectedOption = $subcategorySelect.find(`option[value="${selected}"]`);
        if ($selectedOption.length && $selectedOption.prop('hidden')) {
            $subcategorySelect.val('');
        }
    }
}

function initCategoryFiltering() {
    if (!$) return;

    $('.document-category-select').each(function () {
        const $categorySelect = $(this);
        const target = $categorySelect.data('target');
        if (target) {
            syncSubcategoryOptions($categorySelect, $(target));
        }
    });

    $(document).on('change', '.document-category-select', function () {
        const $categorySelect = $(this);
        const target = $categorySelect.data('target');
        if (target) {
            syncSubcategoryOptions($categorySelect, $(target));
        }
    });
}

function initCategoryEditModal() {
    if (!$) return;
    const $modal = $('#categoryEditModal');
    if (!$modal.length) return;

    const populateCategoryEditModal = (trigger) => {
        const button = $(trigger);
        const payload = getEditPayload(button);
        const updateUrl = button && button.length ? (payload.update_url || button.data('update-url') || '#') : ($('#category_edit_url').val() || '#');
        const name = button && button.length ? (payload.name || button.data('name') || '') : ($('#category_edit_name').val() || '');

        $('#categoryEditForm').attr('action', updateUrl);
        $('#category_edit_url').val(updateUrl);
        $('#category_edit_name').val(name);
    };

    $(document).on('click', '[data-target="#categoryEditModal"], [data-coreui-target="#categoryEditModal"]', function () {
        populateCategoryEditModal(this);
    });

    $modal.on('show.bs.modal show.coreui.modal', function (event) {
        populateCategoryEditModal(event.relatedTarget || event.targetTrigger || null);
    });
}

function initGenderRestrictionChecks() {
    if (!$) return;

    $('.doc-gender-checks').each(function () {
        const target = $(this).data('gender-target');
        if (target) {
            setGenderRestriction(target, $(target).val() || '');
        }
    });

    $(document).on('change', '.doc-gender-checks input[type="checkbox"]', function () {
        const $input = $(this);
        const $wrap = $input.closest('.doc-gender-checks');
        const target = $wrap.data('gender-target');
        if (!target) return;

        if ($input.is(':checked')) {
            $wrap.find('input[type="checkbox"]').not($input).prop('checked', false);
            setGenderRestriction(target, String($input.val()));
        } else {
            setGenderRestriction(target, '');
        }
    });
}

function initSubcategoryEditModal() {
    if (!$) return;
    const $modal = $('#subcategoryEditModal');
    if (!$modal.length) return;

    const populateSubcategoryEditModal = (trigger) => {
        const button = $(trigger);
        const payload = getEditPayload(button);
        const updateUrl = button && button.length ? (payload.update_url || button.data('update-url') || '#') : ($('#subcategory_edit_url').val() || '#');
        const name = button && button.length ? (payload.name || button.data('name') || '') : ($('#subcategory_edit_name').val() || '');
        const categoryId = button && button.length ? (payload.category_id || button.data('category-id') || '') : ($('#subcategory_edit_category').val() || '');

        $('#subcategoryEditForm').attr('action', updateUrl);
        $('#subcategory_edit_url').val(updateUrl);
        $('#subcategory_edit_name').val(name);
        $('#subcategory_edit_category').val(categoryId);
    };

    $(document).on('click', '[data-target="#subcategoryEditModal"], [data-coreui-target="#subcategoryEditModal"]', function () {
        populateSubcategoryEditModal(this);
    });

    $modal.on('show.bs.modal show.coreui.modal', function (event) {
        populateSubcategoryEditModal(event.relatedTarget || event.targetTrigger || null);
    });
}

function initEmployeeDocumentEditModal() {
    if (!$) return;
    const $modal = $('#employeeDocumentEditModal');
    if (!$modal.length) return;

    const populateEmployeeDocumentEditModal = (button) => {
        const $button = $(button);
        let updateUrl = '#';
        let docName = 'Document';
        let fileUrl = '';
        let issuedAt = '';
        let expiresAt = '';
        let reviewNotes = '';

        if ($button && $button.length) {
            const payload = getEditPayload($button);
            updateUrl = payload.update_url || $button.data('update-url') || '#';
            docName = payload.document_name || $button.data('document-name') || 'Document';
            fileUrl = payload.file_url || $button.data('file-url') || '';
            issuedAt = payload.issued || $button.data('issued') || '';
            expiresAt = payload.expires || $button.data('expires') || '';
            reviewNotes = payload.review_notes || $button.data('review-notes') || '';
        } else {
            updateUrl = $('#employee_doc_edit_url').val() || '#';
            docName = $('#employee_doc_edit_document_name').val() || $('#employee-doc-edit-name').text() || 'Document';
            issuedAt = $('#employee_doc_edit_issued_at').val() || '';
            expiresAt = $('#employee_doc_edit_expires_at').val() || '';
        }

        $('#employeeDocumentEditForm').attr('action', updateUrl);
        $('#employee_doc_edit_url').val(updateUrl);
        $('#employee-doc-edit-name').text(docName);
        $('#employee_doc_edit_document_name').val(docName);
        if (!$('#employee_doc_edit_issued_at').val()) $('#employee_doc_edit_issued_at').val(issuedAt);
        if (!$('#employee_doc_edit_expires_at').val()) $('#employee_doc_edit_expires_at').val(expiresAt);

        const $preview = $('#employee-doc-edit-preview');
        $preview.attr('data-file', fileUrl);
        $preview.attr('data-title', docName);

        const $notesWrap = $('#employee-doc-edit-review-notes-wrap');
        const $notes = $('#employee-doc-edit-review-notes');
        if ($notesWrap.length && $notes.length) {
            if (reviewNotes) {
                $notes.text(reviewNotes);
                $notesWrap.removeClass('d-none');
            } else {
                $notes.text('');
                $notesWrap.addClass('d-none');
            }
        }

        toggleDateRequirements(docName, {
            issuedInput: '#employee_doc_edit_issued_at',
            expiresInput: '#employee_doc_edit_expires_at',
            issuedStar: '#employee_doc_edit_issued_required',
            expiresStar: '#employee_doc_edit_expires_required',
            issuedNote: '#employee_doc_edit_issued_note',
            expiresNote: '#employee_doc_edit_expires_note'
        });
    };

    $(document).on('click', '[data-target="#employeeDocumentEditModal"], [data-coreui-target="#employeeDocumentEditModal"]', function () {
        populateEmployeeDocumentEditModal(this);
    });

    $modal.on('show.bs.modal show.coreui.modal', function (event) {
        if (event.relatedTarget) {
            populateEmployeeDocumentEditModal(event.relatedTarget);
        } else {
            populateEmployeeDocumentEditModal(null);
        }
    });
}

function initDocumentReuploadModal() {
    if (!$) return;
    const $modal = $('#documentReuploadModal');
    if (!$modal.length) return;

    const populateDocumentReuploadModal = (button) => {
        const $button = $(button);
        const action = $button && $button.length ? ($button.data('action') || '#') : '#';
        const documentName = $button && $button.length ? ($button.data('document') || 'Document') : 'Document';
        const currentNotes = $button && $button.length ? ($button.data('current-notes') || '') : '';

        $('#documentReuploadForm').attr('action', action);
        $('#document_reupload_action_url').val(action);
        $('#documentReuploadName').text(documentName);
        $('#document_reupload_notes').val(currentNotes);
    };

    $(document).on('click', '[data-target="#documentReuploadModal"], [data-coreui-target="#documentReuploadModal"]', function () {
        populateDocumentReuploadModal(this);
    });

    $modal.on('show.bs.modal show.coreui.modal', function (event) {
        if (event.relatedTarget) {
            populateDocumentReuploadModal(event.relatedTarget);
        } else {
            populateDocumentReuploadModal(null);
        }
    });

    $modal.on('hidden.bs.modal hidden.coreui.modal', function () {
        $('#documentReuploadForm').attr('action', '#');
        $('#document_reupload_action_url').val('');
        $('#documentReuploadName').text('Document');
        $('#document_reupload_notes').val('');
    });
}

function initDocumentsViewToggle() {
    if (!$) return;
    const $employeeSection = $('#employeeDocumentsSection');
    const $catalogSection = $('#documentCatalogSection');
    if (!$employeeSection.length || !$catalogSection.length) return;
    const isAdmin = $('body').data('page') === 'documents.index' && $('#documentCatalogSection').length;
    const $page = $('#documentsIndexPage');
    const hasEmployee = String($page.data('hasEmployee')) === '1';
    const defaultView = String($page.data('defaultView') || '');

    function showEmployeeDocs() {
        $catalogSection.addClass('d-none');
        $employeeSection.removeClass('d-none');
        setToggleState('employee');
        window.location.hash = 'employee-documents';
    }

    function showCatalog() {
        $employeeSection.addClass('d-none');
        $catalogSection.removeClass('d-none');
        setToggleState('catalog');
        window.location.hash = 'document-catalog';
    }

    function setToggleState(activeView) {
        const showCatalog = activeView === 'catalog';
        $('.document-catalog-dropdown-wrap').toggleClass('is-hidden', !showCatalog);
        $('.document-view-toggle .toggle-documents-view').each(function () {
            const $btn = $(this);
            const view = $btn.data('doc-view');
            const isActive = view === activeView;
            $btn.toggleClass('is-active', isActive);
            $btn.attr('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    $(document).on('click', '.toggle-documents-view', function () {
        const view = $(this).data('doc-view');
        if (view === 'catalog') {
            showCatalog();
        } else {
            showEmployeeDocs();
        }
    });

    // Read the ?tab= URL param to restore sub-tab after pagination / search reload
    const urlParams = new URLSearchParams(window.location.search);
    const urlTab = urlParams.get('tab');
    const catalogSubTabs = ['doc-documents', 'doc-categories', 'doc-subcategories'];
    const urlHash = (window.location.hash || '').replace('#', '');
    const targetTabId = catalogSubTabs.includes(urlTab) ? urlTab
        : catalogSubTabs.includes(urlHash) ? urlHash
            : null;

    if (targetTabId) {
        // Show the catalog section
        showCatalog();

        // Directly manipulate the DOM — more reliable than .tab('show') on dropdown items
        setTimeout(function () {
            // 1. Deactivate all tab-panes
            $('#documentCatalogTabsContent .tab-pane').removeClass('show active');
            // 2. Activate the target pane
            $('#' + targetTabId).addClass('show active');
            // 3. Update dropdown item active classes
            $('.dropdown-menu [data-toggle="tab"]').removeClass('active');
            const $targetLink = $('#' + targetTabId + '-tab');
            $targetLink.addClass('active');
            // 4. Update the dropdown button label to match the active tab
            const iconClass = $targetLink.find('i').first().attr('class') || 'cil-library-building';
            const labelText = $targetLink.text().trim();
            $('#documentCatalogDropdown').html(`<i class="${iconClass} mr-2"></i><span>${labelText}</span>`);
        }, 0);

    } else if (window.location.hash === '#employee-documents') {
        showEmployeeDocs();
    } else if (window.location.hash === '#document-catalog') {
        showCatalog();
    } else if (defaultView === 'employee' || hasEmployee) {
        showEmployeeDocs();
    } else if (defaultView === 'catalog') {
        showCatalog();
    } else if (isAdmin) {
        showCatalog();
    } else {
        showEmployeeDocs();
    }
}

function initDocumentsErrorModals() {
    if (!$) return;
    const $page = $('#documentsIndexPage');
    if (!$page.length) return;

    const hasErrors = String($page.data('hasErrors')) === '1';
    const formContext = $page.data('formContext') || '';
    const documentError = String($page.data('documentError')) === '1';

    if (!hasErrors && !documentError) return;

    if (formContext === 'catalog_create') {
        $('#doc-documents-tab').tab('show');
        $('#catalogCreateModal').modal('show');
    } else if (formContext === 'catalog_edit') {
        $('#doc-documents-tab').tab('show');
        const updateUrl = document.getElementById('catalog_edit_url')?.value;
        if (updateUrl) {
            const form = document.getElementById('catalogEditForm');
            if (form) form.setAttribute('action', updateUrl);
        }
        $('#catalogEditModal').modal('show');
    } else if (formContext === 'category_create') {
        $('#doc-categories-tab').tab('show');
        $('#categoryCreateModal').modal('show');
    } else if (formContext === 'category_edit') {
        $('#doc-categories-tab').tab('show');
        const updateUrl = document.getElementById('category_edit_url')?.value;
        if (updateUrl) {
            const form = document.getElementById('categoryEditForm');
            if (form) form.setAttribute('action', updateUrl);
        }
        $('#categoryEditModal').modal('show');
    } else if (formContext === 'subcategory_create') {
        $('#doc-subcategories-tab').tab('show');
        $('#subcategoryCreateModal').modal('show');
    } else if (formContext === 'subcategory_edit') {
        $('#doc-subcategories-tab').tab('show');
        const updateUrl = document.getElementById('subcategory_edit_url')?.value;
        if (updateUrl) {
            const form = document.getElementById('subcategoryEditForm');
            if (form) form.setAttribute('action', updateUrl);
        }
        $('#subcategoryEditModal').modal('show');
    } else if (formContext === 'employee_doc_edit') {
        const updateUrl = document.getElementById('employee_doc_edit_url')?.value;
        if (updateUrl) {
            const form = document.getElementById('employeeDocumentEditForm');
            if (form) form.setAttribute('action', updateUrl);
        }
        $('#employeeDocumentEditModal').modal('show');
    } else if (formContext === 'document_reupload') {
        const actionUrl = document.getElementById('document_reupload_action_url')?.value;
        if (actionUrl) {
            const form = document.getElementById('documentReuploadForm');
            if (form) form.setAttribute('action', actionUrl);
        }
        $('#documentReuploadModal').modal('show');
    } else if (formContext === 'employee_doc_upload') {
        $('#documentUploadModal').modal('show');
    }

    if (documentError) {
        $('#documentUploadModal').modal('show');
    }
}

function initDocumentCatalogDropdown() {
    if (!$) return;
    const $dropdownBtn = $('#documentCatalogDropdown');
    if (!$dropdownBtn.length) return;
    const $createBtn = $('#documentCatalogCreateAction');
    const $dropdownWrap = $dropdownBtn.closest('.document-catalog-dropdown');
    const $menu = $dropdownWrap.find('.dropdown-menu').first();
    const $tabContent = $('#documentCatalogTabsContent');
    const tabCreateConfig = {
        'doc-categories-tab': {
            target: '#categoryCreateModal',
        },
        'doc-subcategories-tab': {
            target: '#subcategoryCreateModal',
        },
        'doc-documents-tab': {
            target: '#catalogCreateModal',
        },
    };

    function updateCreateAction($tabLink) {
        if (!$createBtn.length || !$tabLink || !$tabLink.length) return;
        const config = tabCreateConfig[$tabLink.attr('id')] || tabCreateConfig['doc-categories-tab'];
        $createBtn.attr('data-target', config.target);
        $createBtn.attr('data-coreui-target', config.target);
        $createBtn.attr('title', 'Create');
        $createBtn.attr('aria-label', 'Create');
        $createBtn.find('span').text('Create');
    }

    function activateTab($tabLink) {
        if (!$tabLink || !$tabLink.length) return;
        const target = $tabLink.attr('href');
        if (!target) return;

        $menu.find('.dropdown-item').removeClass('active').attr('aria-selected', 'false');
        $tabLink.addClass('active').attr('aria-selected', 'true');

        $tabContent.find('.tab-pane').removeClass('show active');
        $(target).addClass('show active');

        updateLabel($tabLink);
        updateCreateAction($tabLink);
    }

    function setMenuState(isOpen) {
        $dropdownWrap.toggleClass('show', isOpen);
        $dropdownBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
        $menu.toggleClass('show', isOpen);
    }

    function updateLabel($tabLink) {
        if (!$tabLink || !$tabLink.length) return;
        const iconClass = $tabLink.find('i').first().attr('class') || 'cil-library-building';
        const labelText = $tabLink.text().trim();
        $dropdownBtn.html(
            `<i class="${iconClass} mr-2"></i><span>${labelText}</span>`
        );
    }

    const $activeTab = $('.dropdown-menu [data-toggle="tab"].active').first();
    const $initialTab = $activeTab.length ? $activeTab : $('#doc-categories-tab');
    updateLabel($initialTab);
    updateCreateAction($initialTab);

    $dropdownBtn.on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        setMenuState(!$menu.hasClass('show'));
    });

    $(document).on('click', '.document-catalog-dropdown .dropdown-menu [data-toggle="tab"]', function (event) {
        event.preventDefault();
        activateTab($(this));
        setMenuState(false);
    });

    $(document).on('click', function (event) {
        if (!$(event.target).closest('.document-catalog-dropdown').length) {
            setMenuState(false);
        }
    });

    $(document).on('shown.bs.tab', '.dropdown-menu [data-toggle="tab"]', function () {
        activateTab($(this));
    });
}

function initDocumentTooltips() {
    if (!$ || !$.fn.tooltip) return;
    $('#documentsIndexPage').find('.action-btn[title]').tooltip({
        container: 'body',
        trigger: 'hover',
        placement: 'top',
    });
}

function initDocumentsCatalogToolbar() {
    if (!$) return;

    const table = document.getElementById('documentsTable');
    const toolbar = document.getElementById('documentsCatalogToolbar');
    if (!(table instanceof HTMLTableElement) || !toolbar) return;

    whenDataTableReady(table, (dataTable) => {
        bindToolbarInteractions({
            namespace: 'documentsCatalogToolbar',
            applySelector: '#documentsCatalogToolbar .ui-toolbar__submit',
            searchSelector: '#toolbar-search-documents_catalog_search',
            onApply: () => {
                const searchValue = $('#toolbar-search-documents_catalog_search').val() || '';
                dataTable.search(String(searchValue).trim()).draw();
            },
        });
    });
}

onReady(function () {
    initDocumentUploadModal();
    initCatalogEditModal();
    initEmployeeDocumentEditModal();
    initDocumentReuploadModal();
    initDocumentsViewToggle();
    initCategoryFiltering();
    initGenderRestrictionChecks();
    initCategoryEditModal();
    initSubcategoryEditModal();
    initDocumentsErrorModals();
    initDocumentCatalogDropdown();
    initDocumentTooltips();
    initDocumentsCatalogToolbar();
});


