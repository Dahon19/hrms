import { $, onReady, whenDataTableReady } from './utils';

function getEditPayload($button) {
    if (!$button || !$button.length) return {};
    const payload = $button.data('edit');
    if (payload && typeof payload === 'object') {
        return payload;
    }

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

function renderPositionMembers($button) {
    if (!$button || !$button.length) return;

    const positionName = $button.data('position-name') || 'Position';
    const membersUrl = $button.data('members-url') || null;
    const $title = $('#positionMembersModalTitle');
    const $list = $('#positionMembersList');

    $title.text(positionName + ' Members');
    $list.html('<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading members...</div>');

    if (!membersUrl) {
        $list.html('<div class="text-center text-danger py-4">Unable to load members for this position.</div>');
        return;
    }

    $.getJSON(membersUrl)
        .done((response) => {
            const members = response?.members || [];
            if (!Array.isArray(members) || members.length === 0) {
                $list.html('<div class="text-center text-muted py-4">No employees assigned to this position.</div>');
                return;
            }

            const rows = members.map((member) => {
                const name = member.name || 'Unnamed Employee';
                const department = member.department || 'No department';
                const initial = member.initial || 'U';
                const avatarHtml = member.avatar_url
                    ? `<img src="${member.avatar_url}" alt="${name}" class="position-member-avatar-img">`
                    : `<span class="position-member-avatar-fallback">${initial}</span>`;

                return `
                    <div class="position-member-item">
                        <div class="position-member-avatar">${avatarHtml}</div>
                        <div class="position-member-meta">
                            <div class="position-member-name">${name}</div>
                            <div class="position-member-department">${department}</div>
                        </div>
                    </div>
                `;
            }).join('');

            $list.html(rows);
        })
        .fail(() => {
            $list.html('<div class="text-center text-danger py-4">Unable to load members. Please try again.</div>');
        });
}

function initPositionsIndexPage() {
    if (!$) return;
    const $page = $('#positionsIndexPage');
    if (!$page.length) return;

    const hasCreateError = String($page.data('createError')) === '1';
    const hasEditError = String($page.data('editError')) === '1';

    if (hasCreateError) {
        $('#positionCreateModal').modal('show');
    }

    if (hasEditError) {
        const updateUrl = $('#position_edit_update_url').val();
        if (updateUrl) {
            $('#positionEditForm').attr('action', updateUrl);
        }
        $('#positionEditModal').modal('show');
    }

    function populatePositionEditForm($button) {
        if (!$button || !$button.length) return;
        const payload = getEditPayload($button);
        const updateUrl = payload.update_url || $button.data('update-url') || '#';
        const name = payload.name || $button.data('name') || '';
        const employeeLimit = payload.employee_limit ?? $button.data('employee-limit') ?? '';
        const directDepartmentIds = $button.attr('data-department-ids');
        const directDepartmentIdsCsv = ($button.attr('data-department-ids-csv') || '').trim();
        let parsedDirectDepartmentIds = [];
        if (directDepartmentIds) {
            try {
                const parsed = JSON.parse(directDepartmentIds);
                if (Array.isArray(parsed)) {
                    parsedDirectDepartmentIds = parsed.map((value) => String(value));
                }
            } catch (error) {
                parsedDirectDepartmentIds = [];
            }
        }

        if (!parsedDirectDepartmentIds.length && directDepartmentIdsCsv) {
            parsedDirectDepartmentIds = directDepartmentIdsCsv
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean);
        }

        const departmentIds = Array.isArray(payload.department_ids)
            ? payload.department_ids.map((value) => String(value))
            : parsedDirectDepartmentIds.length
                ? parsedDirectDepartmentIds
            : (payload.department_id || $button.data('department-id')
                ? [String(payload.department_id || $button.data('department-id'))]
                : []);
        $('#positionEditForm').attr('action', updateUrl);
        $('#position_edit_name').val(name);
        $('#position_edit_limit').val(employeeLimit !== null && employeeLimit !== undefined ? String(employeeLimit) : '');
        const $departmentSelect = $('#position_edit_department');
        $departmentSelect.find('option').prop('selected', false);
        departmentIds.forEach((departmentId) => {
            $departmentSelect.find(`option[value="${departmentId}"]`).prop('selected', true);
        });
        $departmentSelect.val(departmentIds).trigger('change');
        $('#position_edit_update_url').val(updateUrl);
    }

    $(document).on('click', '[data-edit][data-target="#positionEditModal"], [data-edit][data-coreui-target="#positionEditModal"]', function () {
        populatePositionEditForm($(this));
    });

    $('#positionEditModal').on('show.bs.modal', function (event) {
        populatePositionEditForm($(event.relatedTarget));
    });

    $('#positionEditModal').on('show.coreui.modal', function (event) {
        populatePositionEditForm($(event.relatedTarget || event.targetTrigger || null));
    });

    $(document).on('click', '.position-members-trigger', function () {
        const $button = $(this);
        renderPositionMembers($button);

        const modalEl = document.getElementById('positionMembersModal');
        if (!modalEl) {
            return;
        }

        if (window.hrmsShowModal) {
            window.hrmsShowModal(modalEl);
            return;
        }

        if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
            window.jQuery(modalEl).modal('show');
        }
    });

    const bindPositionsToolbar = () => {
        const table = document.getElementById('positionsTable');
        if (!(table instanceof HTMLTableElement)) {
            return;
        }

        whenDataTableReady(table, (dataTable) => {
            if (table.dataset.positionsToolbarInitialized === '1') {
                return;
            }

            $(table).closest('.dataTables_wrapper').find('.dataTables_filter').addClass('d-none');

            table.dataset.positionsToolbarInitialized = '1';
        });
    };

    bindPositionsToolbar();
}

onReady(function () {
    initPositionsIndexPage();
});


