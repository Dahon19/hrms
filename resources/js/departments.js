import { $, onReady, whenDataTableReady } from './utils';

function getEditPayload($button) {
    if (!$button || !$button.length) return {};
    const payload = $button.data('edit');
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

function initDepartmentsIndexPage() {
    if (!$) return;
    const $page = $('#departmentsIndexPage');
    if (!$page.length) return;

    const hasCreateError = String($page.data('createError')) === '1';
    const hasEditError = String($page.data('editError')) === '1';
    const hasLogoError = String($page.data('logoError')) === '1';
    const hasTypeCreateError = String($page.data('typeCreateError')) === '1';
    const hasTypeEditError = String($page.data('typeEditError')) === '1';

    if (hasCreateError) {
        $('#departmentCreateModal').modal('show');
    }

    if (hasEditError) {
        const updateUrl = $('#department_edit_update_url').val();
        if (updateUrl) {
            $('#departmentEditForm').attr('action', updateUrl);
        }
        $('#departmentEditModal').modal('show');
    }

    if (hasLogoError) {
        const updateUrl = $('#department_logo_update_url').val();
        if (updateUrl) {
            $('#departmentLogoForm').attr('action', updateUrl);
        }
        $('#departmentLogoModal').modal('show');
    }

    if (hasTypeCreateError) {
        $('#departmentTypeManageModal').modal('show');
    }

    if (hasTypeEditError) {
        const updateUrl = $('#department_type_update_url').val();
        if (updateUrl) {
            $('#departmentTypeEditForm').attr('action', updateUrl);
        }
        $('#departmentTypeEditModal').modal('show');
    }

    function populateDepartmentEditForm($button) {
        if (!$button.length) return;
        const payload = getEditPayload($button);
        const updateUrl = payload.update_url || $button.data('update-url') || '#';
        const name = payload.name || $button.data('name') || '';
        const type = payload.type || $button.data('type') || '';
        $('#departmentEditForm').attr('action', updateUrl);
        $('#department_edit_name').val(name);
        $('#department_edit_type').val(type).trigger('change');
        $('#department_edit_update_url').val(updateUrl);
    }

    function populateDepartmentLogoForm($button) {
        const updateUrl = $button && $button.length ? ($button.data('update-url') || '#') : ($('#department_logo_update_url').val() || '#');
        const currentLogo = $button && $button.length ? ($button.data('current-logo') || '') : '';

        $('#departmentLogoForm').attr('action', updateUrl);
        $('#department_logo_update_url').val(updateUrl);

        const $preview = $('#department_logo_preview');
        const $emptyState = $('#department_logo_empty');
        if (currentLogo) {
            $preview.attr('src', currentLogo).removeClass('d-none');
            $emptyState.addClass('d-none');
        } else {
            $preview.attr('src', '').addClass('d-none');
            $emptyState.removeClass('d-none');
        }
        $('#department_logo_file').val('');
    }

    function populateDepartmentTypeEditForm($button) {
        if (!$button.length) return;
        const payload = getEditPayload($button);
        const updateUrl = payload.update_url || $button.data('update-url') || '#';
        const name = payload.name || $button.data('name') || '';
        $('#departmentTypeEditForm').attr('action', updateUrl);
        $('#department_type_edit_name').val(name);
        $('#department_type_update_url').val(updateUrl);
    }

    $(document).on('click', '[data-edit][data-target="#departmentEditModal"], [data-edit][data-coreui-target="#departmentEditModal"]', function () {
        populateDepartmentEditForm($(this));
    });

    $(document).on('click', '[data-target="#departmentLogoModal"], [data-coreui-target="#departmentLogoModal"]', function () {
        populateDepartmentLogoForm($(this));
    });

    $(document).on('click', '[data-edit][data-target="#departmentTypeEditModal"], [data-edit][data-coreui-target="#departmentTypeEditModal"]', function () {
        populateDepartmentTypeEditForm($(this));
    });

    $('#departmentEditModal').on('show.bs.modal', function (event) {
        populateDepartmentEditForm($(event.relatedTarget));
    });

    $('#departmentEditModal').on('show.coreui.modal', function (event) {
        populateDepartmentEditForm($(event.relatedTarget || event.targetTrigger || null));
    });

    $('#departmentLogoModal').on('show.bs.modal', function (event) {
        populateDepartmentLogoForm($(event.relatedTarget));
    });

    $('#departmentLogoModal').on('show.coreui.modal', function (event) {
        populateDepartmentLogoForm($(event.relatedTarget || event.targetTrigger || null));
    });

    $('#departmentTypeEditModal').on('show.bs.modal', function (event) {
        populateDepartmentTypeEditForm($(event.relatedTarget));
    });

    $('#departmentTypeEditModal').on('show.coreui.modal', function (event) {
        populateDepartmentTypeEditForm($(event.relatedTarget || event.targetTrigger || null));
    });

    $('#department_logo_file').on('change', function () {
        const file = this.files && this.files[0];
        const $preview = $('#department_logo_preview');
        const $emptyState = $('#department_logo_empty');
        if (!file) {
            $preview.attr('src', '').addClass('d-none');
            $emptyState.removeClass('d-none');
            return;
        }
        const reader = new FileReader();
        reader.onload = function (event) {
            $preview.attr('src', event.target.result).removeClass('d-none');
            $emptyState.addClass('d-none');
        };
        reader.readAsDataURL(file);
    });

    const dropzone = document.getElementById('departmentLogoDropzone');
    const logoInput = document.getElementById('department_logo_file');
    if (dropzone && logoInput) {
        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', function (event) {
            const files = event.dataTransfer && event.dataTransfer.files;
            if (!files || !files.length) return;
            logoInput.files = files;
            $(logoInput).trigger('change');
        });
    }

    $('.view-dept').on('click', function (event) {
        event.preventDefault();
        const $button = $(this);
        const $row = $button.closest('.department-row');
        if (!$row.length) return;
        const deptId = $button.data('dept-id') || $row.data('dept-id');
        const deptName = $button.data('dept-name') || $row.data('dept-name') || '';
        const $modal = $('#departmentPositionsModal');
        if (!deptId || !$modal.length) return;

        const baseUrl = $modal.data('base-url') || '/departments';
        $modal.data('dept-id', deptId);
        $('#departmentPositionsTitle').html('<i class="cil-layers mr-2"></i>' + deptName + ' Positions');
        $('#dept-positions-body').html(
            '<tr><td colspan="3" class="text-center py-4">' +
            '<div class="dept-positions-loader justify-content-center">' +
            '<span class="spinner-border spinner-border-sm text-success me-2" role="status" aria-hidden="true"></span>' +
            '<span>Loading positions...</span>' +
            '</div>' +
            '</td></tr>'
        );
        $('#dept-availability').text('');

        $.getJSON(baseUrl + '/' + deptId + '/positions')
            .done(function (data) {
                const positions = data.positions || [];
                const meta = data.meta || {};
                if (!positions.length) {
                    $('#dept-positions-body').html(
                        '<tr><td colspan="3" class="text-center text-muted py-4">No positions found.</td></tr>'
                    );
                    return;
                }

                const rows = positions.map(function (pos) {
                    const rawName = pos.name || '';
                    const displayName = rawName
                        ? rawName.charAt(0).toUpperCase() + rawName.slice(1)
                        : rawName;
                    const available = (pos.limit !== null && pos.limit !== undefined)
                        ? Math.max(pos.limit - pos.count, 0)
                        : null;
                    const allocation = available === null ? '-' : (pos.count + '/' + pos.limit);
                    const statusText = pos.is_occupied ? 'Full' : 'Available';
                    const statusClass = pos.is_occupied ? 'position-status-pill is-full' : 'position-status-pill is-available';

                    return (
                        '<tr class="position-row" data-position-id="' + pos.id + '" data-position-name="' + rawName + '">' +
                        '<td class="pl-4 text-primary font-weight-bold">' +
                        '<i class="cil-chevron-right position-caret mr-2"></i>' + displayName + '</td>' +
                        '<td>' + allocation + '</td>' +
                        '<td class="text-center"><span class="' + statusClass + '">' + statusText + '</span></td>' +
                        '</tr>' +
                        '<tr class="position-employees-row d-none" data-position-id="' + pos.id + '">' +
                        '<td colspan="3" class="p-0">' +
                        '<div class="position-employees-panel">' +
                        '<div class="table-responsive">' +
                        '<table class="table table-sm mb-0">' +
                        '<thead class="bg-white">' +
                        '<tr class="small text-muted text-uppercase">' +
                        '<th class="pl-4">Employee ID</th>' +
                        '<th>First Name</th>' +
                        '<th>Last Name</th>' +
                        '</tr>' +
                        '</thead>' +
                        '<tbody class="position-employees-body"></tbody>' +
                        '</table>' +
                        '</div>' +
                        '</div>' +
                        '</td>' +
                        '</tr>'
                    );
                }).join('');

                $('#dept-positions-body').html(rows);
                if (meta.total_limit !== null && meta.total_limit !== undefined) {
                    $('#dept-availability').text('Available slots: ' + meta.total_available + ' of ' + meta.total_limit);
                }
            })
            .fail(function () {
                $('#dept-positions-body').html(
                    '<tr><td colspan="3" class="text-center text-danger py-4">Failed to load positions.</td></tr>'
                );
            });

        $modal.modal('show');
    });

    $('#dept-positions-body').on('click', '.position-row', function () {
        const $row = $(this);
        const positionId = $row.data('position-id');
        const $deptModal = $('#departmentPositionsModal');
        const deptId = $deptModal.data('dept-id');
        const baseUrl = $deptModal.data('base-url') || '/departments';
        if (!positionId || !deptId) return;

        const $detailsRow = $row.next('.position-employees-row');
        if (!$detailsRow.length) return;

        if ($detailsRow.hasClass('d-none')) {
            $detailsRow.removeClass('d-none');
            $row.addClass('is-open');
        } else {
            $detailsRow.addClass('d-none');
            $row.removeClass('is-open');
            return;
        }

        const $body = $detailsRow.find('.position-employees-body');
        $body.html('');

        $.getJSON(baseUrl + '/' + deptId + '/positions/' + positionId + '/employees')
            .done(function (data) {
                const employees = data.employees || [];
                if (!employees.length) {
                    $body.html(
                        '<tr><td colspan="3" class="text-center text-muted py-3">No employees assigned.</td></tr>'
                    );
                    return;
                }
                const rows = employees.map(function (emp) {
                    return (
                        '<tr>' +
                        '<td class="pl-4">' + emp.employee_id + '</td>' +
                        '<td>' + emp.first_name + '</td>' +
                        '<td>' + emp.last_name + '</td>' +
                        '</tr>'
                    );
                }).join('');
                $body.html(rows);
            })
            .fail(function () {
                $body.html(
                    '<tr><td colspan="3" class="text-center text-danger py-3">Failed to load employees.</td></tr>'
                );
            });
    });

    const bindDepartmentsToolbar = () => {
        const table = document.getElementById('departmentsTable');
        if (!(table instanceof HTMLTableElement)) {
            return;
        }

        whenDataTableReady(table, (dataTable) => {
            if (table.dataset.departmentToolbarInitialized === '1') {
                return;
            }

            $(table).closest('.dataTables_wrapper').find('.dataTables_filter').addClass('d-none');
            table.dataset.departmentToolbarInitialized = '1';
        });
    };

    bindDepartmentsToolbar();
}

onReady(function () {
    initDepartmentsIndexPage();
});


