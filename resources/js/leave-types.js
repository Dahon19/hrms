import { $, onReady } from './utils';

function getEditPayload(button) {
    if (!button || !button.length) return {};
    const payload = button.data('edit');
    return payload && typeof payload === 'object' ? payload : {};
}

function initLeaveTypeEditModal() {
    if (!$) return;
    const $modal = $('#leaveTypeEditModal');
    if (!$modal.length) return;

    function populateLeaveTypeEditModal(button) {
        if (!button || !button.length) return;
        const payload = getEditPayload(button);
        const updateUrl = payload.update_url || button.data('update-url') || '#';
        const name = payload.name || button.data('name') || '';
        const color = payload.color || button.data('color') || '#198754';
        const rawRequiresAttachment = payload.requires_attachment ?? button.data('requires-attachment');
        const requiresAttachment = rawRequiresAttachment === 1 || rawRequiresAttachment === '1' || rawRequiresAttachment === true;
        const gender = payload.gender || button.data('gender') || '';

        $('#leaveTypeEditForm').attr('action', updateUrl);
        $('#leave_type_edit_name').val(name);
        $('#leave_type_edit_color').val(color);
        $('#leave_type_edit_requires_attachment').prop('checked', !!requiresAttachment);
        $('#leave_type_edit_gender').val(gender).trigger('change');
        $('#leave_type_edit_update_url').val(updateUrl);
    }

    $(document).on('click', '[data-edit][data-target="#leaveTypeEditModal"], [data-edit][data-coreui-target="#leaveTypeEditModal"]', function () {
        populateLeaveTypeEditModal($(this));
    });

    $modal.on('show.bs.modal', function (event) {
        populateLeaveTypeEditModal($(event.relatedTarget));
    });

    $modal.on('show.coreui.modal', function (event) {
        populateLeaveTypeEditModal($(event.relatedTarget || event.targetTrigger || null));
    });
}

function initLeaveTypeErrorModals() {
    if (!$) return;
    const $page = $('#leaveTypesPage');
    if (!$page.length) return;

    const hasErrors = String($page.data('hasErrors')) === '1';
    if (!hasErrors) return;

    const formContext = $page.data('formContext') || '';
    if (formContext === 'leave_type_create') {
        $('#leaveTypeCreateModal').modal('show');
    } else if (formContext === 'leave_type_edit') {
        const updateUrl = document.getElementById('leave_type_edit_update_url')?.value;
        if (updateUrl) {
            const form = document.getElementById('leaveTypeEditForm');
            if (form) form.setAttribute('action', updateUrl);
        }
        $('#leaveTypeEditModal').modal('show');
    }
}

onReady(function () {
    initLeaveTypeEditModal();
    initLeaveTypeErrorModals();
});



