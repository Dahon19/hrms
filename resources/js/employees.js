import { $, onReady } from './utils';

function initEmployeeSelect2Control($el) {
    if (!$ || !$.fn || !$.fn.select2 || !$el || !$el.length) return;

    const isMultiple = $el.prop('multiple');
    const placeholder = String($el.data('placeholder') || (isMultiple ? 'Select position' : 'Select department'));
    const $modal = $el.closest('.modal');
    const $dropdownParent = $modal.find('.modal-content').first();
    const isPositionMulti = isMultiple && $el.hasClass('employee-positions');

    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }

    $el.select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder,
        dropdownParent: $dropdownParent.length ? $dropdownParent : ($modal.length ? $modal : $(document.body)),
        closeOnSelect: !isMultiple,
        minimumResultsForSearch: isPositionMulti ? Infinity : 0,
        selectionCssClass: isPositionMulti ? 'employee-positions-select2-selection' : '',
        dropdownCssClass: isPositionMulti ? 'employee-positions-select2-dropdown' : '',
    });
}

function initEmployeeModalSelect2($scope) {
    if (!$ || !$.fn || !$.fn.select2 || !$scope || !$scope.length) return;

    $scope.find('.employee-positions').each(function () {
        const $el = $(this);
        initEmployeeSelect2Control($el);
    });
}

function getEmployeeAvailabilityMessage(meta, positions) {
    if (meta && meta.total_limit !== null && meta.total_limit !== undefined) {
        const available = Number(meta.total_available ?? 0);
        const limit = Number(meta.total_limit ?? 0);
        return `${available} of ${limit} employee slots available in this department.`;
    }

    const count = Array.isArray(positions) ? positions.length : 0;
    if (count === 0) {
        return 'No positions configured for this department yet.';
    }

    return `${count} position${count === 1 ? '' : 's'} available in this department.`;
}

function setEmployeeAvailabilityMessage($positions, message) {
    const $availability = $positions.closest('.form-group').find('.employee-availability');
    if (!$availability.length) return;
    $availability.text(message || '');
}

function renderEmployeePositionOptions($positions, positions, selectedIds = []) {
    const normalizedSelectedIds = Array.isArray(selectedIds)
        ? selectedIds.map((value) => String(value))
        : (selectedIds ? [String(selectedIds)] : []);

    $positions.empty();

    positions.forEach((position) => {
        const option = new Option(String(position.name || ''), String(position.id || ''), false, normalizedSelectedIds.includes(String(position.id)));
        $positions.append(option);
    });

    $positions.val(normalizedSelectedIds.filter((value) => positions.some((position) => String(position.id) === value)));
    $positions.trigger('change');
}

function loadEmployeeDepartmentPositions($department, $positions, { selectedIds = [], employeeId = '' } = {}) {
    if (!$ || !$department?.length || !$positions?.length) {
        return Promise.resolve();
    }

    const departmentId = String($department.val() || '').trim();
    const baseUrl = String($positions.data('urlBase') || '').trim();

    if (!departmentId || !baseUrl) {
        $positions.prop('disabled', true);
        $positions.empty().trigger('change');
        setEmployeeAvailabilityMessage($positions, 'Select a department first to load positions.');
        return Promise.resolve();
    }

    const pendingRequest = $positions.data('positionsRequest');
    if (pendingRequest && typeof pendingRequest.abort === 'function') {
        pendingRequest.abort();
    }

    const requestUrl = new URL(`${baseUrl}/${encodeURIComponent(departmentId)}/positions`, window.location.origin);
    if (employeeId) {
        requestUrl.searchParams.set('employee_id', String(employeeId));
    }

    $positions.prop('disabled', true);
    setEmployeeAvailabilityMessage($positions, 'Loading department positions...');

    const request = $.getJSON(requestUrl.toString())
        .done((payload) => {
            const positions = Array.isArray(payload?.positions) ? payload.positions : [];
            renderEmployeePositionOptions($positions, positions, selectedIds);
            $positions.prop('disabled', positions.length === 0);
            setEmployeeAvailabilityMessage($positions, getEmployeeAvailabilityMessage(payload?.meta, positions));
        })
        .fail((xhr, statusText) => {
            if (statusText === 'abort') {
                return;
            }

            $positions.empty().trigger('change');
            $positions.prop('disabled', true);
            setEmployeeAvailabilityMessage($positions, 'Unable to load positions for the selected department.');
        })
        .always(() => {
            $positions.removeData('positionsRequest');
        });

    $positions.data('positionsRequest', request);
    return request.promise();
}

function initEmployeeIndexPage() {
    if (!$) return;
    const $page = $('#employeeIndexPage');
    if (!$page.length) return;

    function rememberModalTrigger($modal) {
        if (!$modal || !$modal.length) return;

        $modal.on('show.bs.modal show.coreui.modal', function (event) {
            const trigger = event.relatedTarget || event.targetTrigger || null;
            $(this).data('triggerEl', trigger);
        });

        $modal.on('hidden.bs.modal hidden.coreui.modal', function () {
            const trigger = $(this).data('triggerEl');
            if (trigger && typeof trigger.blur === 'function') {
                trigger.blur();
            }
            $(this).removeData('triggerEl');

            if (document.activeElement && typeof document.activeElement.blur === 'function') {
                document.activeElement.blur();
            }
        });
    }

    const hasCreateError = String($page.data('createError')) === '1';
    const hasEditError = String($page.data('editError')) === '1';
    const hasSuccess = String($page.data('hasSuccess')) === '1';

    if (hasCreateError) {
        $('#employeeCreateModal').modal('show');
    }

    if (hasEditError) {
        const updateUrl = $('#employee_edit_update_url').val();
        if (updateUrl) {
            $('#employeeEditForm').attr('action', updateUrl);
        }
        $('#employeeEditModal').modal('show');
    }

    if (hasSuccess) {
        const $createRfid = $('#employee_create_nfc_uid');
        const $editRfid = $('#employee_edit_nfc_uid');
        if ($createRfid.length) $createRfid.val('');
        if ($editRfid.length) $editRfid.val('');

        const $createModal = $('#employeeCreateModal');
        if ($createModal.length) {
            const form = $createModal.find('form.employee-form').get(0);
            if (form) form.reset();
            $createModal.modal('hide');

            const $department = $('#employee_create_department');
            const $positions = $('#employee_create_positions');
            if ($department.length) {
                $department.val('').trigger('change');
            }
            if ($positions.length) {
                $positions.val('').trigger('change');
            }
        }
    }

    const $createModal = $('#employeeCreateModal');
    const $editModal = $('#employeeEditModal');
    const $detailModal = $('#employeeDetailsModal');
    const $documentsModal = $('#employeeDocumentsModal');
    const $createDepartment = $('#employee_create_department');
    const $createPositions = $('#employee_create_positions');
    const $editDepartment = $('#employee_edit_department');
    const $editPositions = $('#employee_edit_positions');
    const nfcLatestUrl = '/api/nfc/latest';
    let activeRfidSession = null;
    let nfcPollTimer = null;
    let preserveCreateServerState = hasCreateError;

    function clearValidationState($form) {
        if (!$form || !$form.length) return;

        $form.removeClass('was-validated');
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
        $form.find('.invalid-feedback[data-generated-for]').each(function () {
            this.textContent = '';
            this.classList.remove('d-block');
        });
    }

    function resetCreateModalState() {
        if (!$createModal.length) return;

        const form = $createModal.find('form.employee-form').get(0);
        if (form instanceof HTMLFormElement) {
            form.reset();
        }

        clearValidationState($createModal.find('form.employee-form'));

        $('#employee_create_email').val('');
        $('#employee_create_first_name').val('');
        $('#employee_create_last_name').val('');
        $('#employee_create_gender').val('').trigger('change');
        $('#employee_create_address').val('');
        $('#employee_create_department').val('').trigger('change');
        $('#employee_create_nfc_uid').val('');

        if ($createPositions.length) {
            $createPositions.data('selected', '');
            $createPositions.data('employeeId', '');
            $createPositions.empty().trigger('change');
            $createPositions.prop('disabled', true);
        }

        setEmployeeAvailabilityMessage($createPositions, 'Select a department first to load positions.');
        setRfidFieldState('employee_create_nfc_uid', false);
        setRfidStatus('employee_create_rfid_status', 'Waiting for RFID scan...', 'muted');
    }

    rememberModalTrigger($detailModal);
    rememberModalTrigger($documentsModal);

    if ($createModal.length) {
        rememberModalTrigger($createModal);
        $createModal.on('shown.bs.modal shown.coreui.modal', function () {
            initEmployeeModalSelect2($createModal);
        });
    }

    if ($editModal.length) {
        rememberModalTrigger($editModal);
        $editModal.on('shown.bs.modal shown.coreui.modal', function () {
            initEmployeeModalSelect2($editModal);
        });
    }

    $createDepartment.off('.employeePositions').on('change.employeePositions', function () {
        loadEmployeeDepartmentPositions($createDepartment, $createPositions);
    });

    $editDepartment.off('.employeePositions').on('change.employeePositions', function () {
        loadEmployeeDepartmentPositions($editDepartment, $editPositions, {
            employeeId: $editPositions.data('employeeId') || '',
        });
    });

    function populateEmployeeEditForm($button) {
        if (!$button.length) return;

        let payload = $button.data('edit') || $button.attr('data-edit') || {};
        if (typeof payload === 'string') {
            try {
                payload = JSON.parse(payload);
            } catch (error) {
                payload = {};
            }
        }

        const updateUrl = payload.update_url || $button.data('update-url') || '#';
        const employeeId = payload.employee_id || $button.data('employee-row-id') || $button.data('employee-id') || '';
        const employeeIdValue = payload.employee_id_value || $button.data('employee-id-value') || '';
        const rfid = payload.rfid || $button.data('rfid') || '';
        const status = payload.status || $button.data('status') || 'active';
        const firstName = payload.first_name || $button.data('first-name') || '';
        const lastName = payload.last_name || $button.data('last-name') || '';
        const address = payload.address || $button.data('address') || '';
        const departmentId = payload.department_id || $button.data('department-id') || '';
        const gender = payload.gender || $button.data('gender') || '';
        const positionIdsRaw = payload.position_ids
            ?? $button.data('position-ids')
            ?? $button.attr('data-position-ids')
            ?? payload.position_id
            ?? $button.data('position-id')
            ?? '';
        const hireDate = payload.hire_date || $button.data('hire-date') || '';

        $('#employeeEditForm').attr('action', updateUrl);
        $('#employee_edit_update_url').val(updateUrl);
        $('#employee_edit_employee_id').val(employeeIdValue);
        $('#employee_edit_nfc_uid').val(rfid);
        $('#employee_edit_status').val(status).trigger('change');
        $('#employee_edit_first_name').val(firstName);
        $('#employee_edit_last_name').val(lastName);
        $('#employee_edit_gender').val(gender).trigger('change');
        $('#employee_edit_address').val(address);
        $('#employee_edit_hire_date').val(hireDate);

        let normalizedPositionIds = [];
        if (Array.isArray(positionIdsRaw)) {
            normalizedPositionIds = positionIdsRaw.map((value) => String(value)).filter(Boolean);
        } else if (typeof positionIdsRaw === 'string' && positionIdsRaw.trim().startsWith('[')) {
            try {
                const parsedPositionIds = JSON.parse(positionIdsRaw);
                normalizedPositionIds = Array.isArray(parsedPositionIds)
                    ? parsedPositionIds.map((value) => String(value)).filter(Boolean)
                    : [];
            } catch (error) {
                normalizedPositionIds = [];
            }
        } else if (positionIdsRaw) {
            normalizedPositionIds = [String(positionIdsRaw)];
        }

        $editPositions.data('selected', normalizedPositionIds);
        $editPositions.data('employeeId', employeeId);
        $('#employee_edit_department').val(departmentId).trigger('change');
        loadEmployeeDepartmentPositions($editDepartment, $editPositions, {
            selectedIds: normalizedPositionIds,
            employeeId,
        });
    }

    $(document).on('click', '.edit-employee-trigger, [data-edit][data-target="#employeeEditModal"], [data-edit][data-coreui-target="#employeeEditModal"]', function () {
        populateEmployeeEditForm($(this));
    });

    $('#employeeEditModal').on('show.bs.modal', function (event) {
        const $button = $(event.relatedTarget);
        populateEmployeeEditForm($button);
    });

    $('#employeeEditModal').on('show.coreui.modal', function (event) {
        const trigger = event.relatedTarget || event.targetTrigger || null;
        populateEmployeeEditForm($(trigger));
    });

    function stopNfcPoll() {
        if (nfcPollTimer) {
            clearTimeout(nfcPollTimer);
            nfcPollTimer = null;
        }
    }

    function setRfidFieldState(inputId, detected) {
        if (!inputId) return;
        const $input = $('#' + inputId);
        if (!$input.length) return;
        $input.toggleClass('is-rfid-detected', Boolean(detected));
    }

    function setRfidStatus(statusId, message, type = 'muted') {
        if (!statusId) return;
        const $status = $('#' + statusId);
        if (!$status.length) return;

        $status
            .text(message || '')
            .removeClass('text-muted text-success text-warning text-danger');

        if (type === 'success') $status.addClass('text-success');
        else if (type === 'warning') $status.addClass('text-warning');
        else if (type === 'danger') $status.addClass('text-danger');
        else $status.addClass('text-muted');
    }

    function applyCapturedRfid(session, data) {
        if (!session) return;
        const $input = $('#' + session.inputId);
        if (!$input.length) return;

        const uid = String(data?.nfc_uid || '').trim();
        if (!uid) return;

        $input.val(uid);
        setRfidFieldState(session.inputId, true);

        if (data.assigned || data.exists) {
            setRfidStatus(session.statusId, 'RFID detected. This card is already assigned and will be revalidated on save.', 'warning');
        } else {
            setRfidStatus(session.statusId, 'RFID detected and ready to save.', 'success');
        }
    }

    function pollNfc() {
        if (!activeRfidSession) return;

        $.getJSON(nfcLatestUrl, {
            after: activeRfidSession.startedAt,
            consumer: activeRfidSession.consumer,
        })
            .done(function (data) {
                if (!activeRfidSession) return;

                if (data && data.captured && data.nfc_uid) {
                    const scanId = String(data.scan_id || data.timestamp || data.nfc_uid);
                    if (scanId !== activeRfidSession.lastScanId) {
                        activeRfidSession.lastScanId = scanId;
                        applyCapturedRfid(activeRfidSession, data);
                    }
                } else if (!activeRfidSession.lastScanId) {
                    setRfidStatus(activeRfidSession.statusId, activeRfidSession.waitingMessage, 'muted');
                    setRfidFieldState(activeRfidSession.inputId, false);
                }
                nfcPollTimer = setTimeout(pollNfc, 2000);
            })
            .fail(function () {
                if (activeRfidSession) {
                    setRfidStatus(activeRfidSession.statusId, 'Unable to read RFID scanner right now.', 'danger');
                }
                nfcPollTimer = setTimeout(pollNfc, 3000);
            });
    }

    function startRfidPolling(config) {
        stopNfcPoll();

        activeRfidSession = {
            inputId: config.inputId,
            statusId: config.statusId,
            consumer: config.consumer,
            startedAt: new Date().toISOString(),
            lastScanId: null,
            waitingMessage: config.waitingMessage,
        };

        if (config.clearInputOnOpen) {
            $('#' + config.inputId).val('');
        }

        setRfidFieldState(config.inputId, false);
        setRfidStatus(config.statusId, config.waitingMessage, 'muted');
        pollNfc();
    }

    function stopRfidPolling(statusId, inputId, resetMessage, clearInput = false) {
        stopNfcPoll();
        activeRfidSession = null;
        if (clearInput) {
            $('#' + inputId).val('');
        }
        setRfidFieldState(inputId, false);
        setRfidStatus(statusId, resetMessage, 'muted');
    }

    if ($createModal.length) {
        $createModal.on('show.bs.modal show.coreui.modal', function () {
            if (preserveCreateServerState) {
                preserveCreateServerState = false;
            } else {
                resetCreateModalState();
            }

            initEmployeeModalSelect2($createModal);
            startRfidPolling({
                inputId: 'employee_create_nfc_uid',
                statusId: 'employee_create_rfid_status',
                consumer: 'employee-create-modal',
                waitingMessage: 'Waiting for RFID scan...',
                clearInputOnOpen: true,
            });
            loadEmployeeDepartmentPositions($createDepartment, $createPositions, {
                selectedIds: $createPositions.data('selected') || '',
            });
        });
        $createModal.on('hidden.bs.modal hidden.coreui.modal', function () {
            stopRfidPolling('employee_create_rfid_status', 'employee_create_nfc_uid', 'Waiting for RFID scan...', true);
            preserveCreateServerState = false;
            resetCreateModalState();
        });
    }

    if ($editModal.length) {
        $editModal.on('show.bs.modal show.coreui.modal', function () {
            initEmployeeModalSelect2($editModal);
            startRfidPolling({
                inputId: 'employee_edit_nfc_uid',
                statusId: 'employee_edit_rfid_status',
                consumer: 'employee-edit-modal',
                waitingMessage: 'Tap a card to capture or replace RFID.',
                clearInputOnOpen: false,
            });
        });
        $editModal.on('hidden.bs.modal hidden.coreui.modal', function () {
            stopRfidPolling('employee_edit_rfid_status', 'employee_edit_nfc_uid', 'Tap a card to capture or replace RFID.');
        });
    }

    if ($createDepartment.length && $createDepartment.val()) {
        loadEmployeeDepartmentPositions($createDepartment, $createPositions, {
            selectedIds: $createPositions.data('selected') || '',
        });
    } else {
        setEmployeeAvailabilityMessage($createPositions, 'Select a department first to load positions.');
    }

    if ($editDepartment.length && $editDepartment.val()) {
        loadEmployeeDepartmentPositions($editDepartment, $editPositions, {
            selectedIds: $editPositions.data('selected') || [],
            employeeId: $editPositions.data('employeeId') || '',
        });
    } else {
        setEmployeeAvailabilityMessage($editPositions, 'Select a department first to load positions.');
    }

}

function initEmployeeDetailsModal() {
    if (!$) return;
    const $modal = $('#employeeDetailsModal');
    if (!$modal.length) return;
    const $detailResetForm = $('#employeeDetailResetPasswordForm');
    const nfcLatestUrl = '/api/nfc/latest';
    let activationRfidTimer = null;
    let detailsRfidSession = null;

    function stopActivationRfidPoll() {
        if (activationRfidTimer) {
            clearTimeout(activationRfidTimer);
            activationRfidTimer = null;
        }
    }

    function setDetailsRfidStatus(statusId, message, type = 'muted') {
        const $status = $('#' + statusId);
        if (!$status.length) return;
        $status
            .text(message || '')
            .removeClass('text-muted text-success text-warning text-danger');

        if (type === 'success') $status.addClass('text-success');
        else if (type === 'warning') $status.addClass('text-warning');
        else if (type === 'danger') $status.addClass('text-danger');
        else $status.addClass('text-muted');
    }

    function setDetailsRfidDetected(inputId, detected) {
        $('#' + inputId).toggleClass('is-rfid-detected', Boolean(detected));
    }

    function pollDetailsRfid() {
        if (!detailsRfidSession) return;

        $.getJSON(nfcLatestUrl, {
            after: detailsRfidSession.startedAt,
            consumer: detailsRfidSession.consumer,
        }).done(function (data) {
            if (!detailsRfidSession) return;

            if (data && data.captured && data.nfc_uid) {
                const scanId = String(data.scan_id || data.timestamp || data.nfc_uid);
                if (scanId !== detailsRfidSession.lastScanId) {
                    detailsRfidSession.lastScanId = scanId;
                    $('#' + detailsRfidSession.inputId).val(String(data.nfc_uid || ''));
                    setDetailsRfidDetected(detailsRfidSession.inputId, true);
                    if (data.assigned || data.exists) {
                        setDetailsRfidStatus(detailsRfidSession.statusId, detailsRfidSession.assignedMessage, 'warning');
                    } else {
                        setDetailsRfidStatus(detailsRfidSession.statusId, detailsRfidSession.successMessage, 'success');
                    }
                }
            } else if (!detailsRfidSession.lastScanId) {
                setDetailsRfidDetected(detailsRfidSession.inputId, false);
                setDetailsRfidStatus(detailsRfidSession.statusId, detailsRfidSession.waitingMessage, 'muted');
            }

            activationRfidTimer = setTimeout(pollDetailsRfid, 2000);
        }).fail(function () {
            if (detailsRfidSession) {
                setDetailsRfidStatus(detailsRfidSession.statusId, 'Unable to read RFID scanner right now.', 'danger');
            }
            activationRfidTimer = setTimeout(pollDetailsRfid, 3000);
        });
    }

    function populateEmployeeDetails($btn) {
        if (!$btn || !$btn.length) return;

        $('#employee_detail_name').text($btn.data('name') || 'Employee');
        $('#employee_detail_id').text('#' + ($btn.data('employee-id') || '-'));
        $('#employee_detail_gender').text($btn.data('gender') || '-');
        const status = $btn.data('status') || '-';
        $('#employee_detail_status').text(
            typeof status === 'string' && status.length
                ? status.charAt(0).toUpperCase() + status.slice(1)
                : '-'
        );
        $('#employee_detail_department').text($btn.data('department') || '-');
        $('#employee_detail_position').text($btn.data('position') || '-');
        $('#employee_detail_address').text($btn.data('address') || '-');
        $('#employee_detail_hire_date').text($btn.data('hire-date') || '-');
        $('#employee_detail_email').text($btn.data('email') || '-');
        const isAdmin = String($btn.data('is-admin')) === '1';
        const isHrHead = String($btn.data('is-hr-head')) === '1';
        const isArchived = String($btn.data('archived')) === '1';
        const reactivateUrl = String($btn.data('reactivateUrl') || '').trim();
        const resetPasswordUrl = String($btn.data('resetPasswordUrl') || '').trim();
        const employeeRowId = $btn.data('employee-row-id') || '';
        const $archivedBadge = $('#employee_detail_archived_badge');
        const $reactivateButton = $('#employeeReactivateButton');
        const $reactivateForm = $('#employeeReactivateForm');
        const employeeName = String($btn.data('name') || 'this employee').trim();
        if ((isAdmin || isHrHead) && isArchived) {
            $archivedBadge.removeClass('d-none');
        } else {
            $archivedBadge.addClass('d-none');
        }

        if ((isAdmin || isHrHead) && isArchived && reactivateUrl !== '') {
            $reactivateForm.attr('action', reactivateUrl);
            $reactivateButton.removeClass('d-none');
        } else {
            $reactivateForm.attr('action', '');
            $reactivateButton.addClass('d-none');
        }
        const avatar = $btn.data('avatar') || '';
        if (avatar) {
            $('#employee_detail_avatar').attr('src', avatar);
        }

        if ($detailResetForm.length) {
            $detailResetForm.attr('action', resetPasswordUrl || '#');
            $detailResetForm.toggleClass('d-none', !(isAdmin && resetPasswordUrl));
            $detailResetForm.attr(
                'data-confirm-message',
                `Reset ${employeeName} password to the default system password?`,
            );
            $detailResetForm.find('.employee-detail-reset-btn').prop('disabled', !(isAdmin && resetPasswordUrl));
        }
    }

    $(document).on('click', '.view-employee', function () {
        populateEmployeeDetails($(this));
    });

    $modal.on('show.bs.modal show.coreui.modal', function (event) {
        const trigger = event.relatedTarget || event.targetTrigger || null;
        populateEmployeeDetails($(trigger));
    });

    $(document).on('click', '.open-employee-documents-modal', function () {
        const documentsUrl = String($(this).data('documentsUrl') || '').trim();
        $('#employee_documents_frame').attr('src', documentsUrl || 'about:blank');
    });

    $('#employeeDocumentsModal').on('show.bs.modal show.coreui.modal', function (event) {
        const $button = $(event.relatedTarget);
        if (!$button.length) return;

        const documentsUrl = String($button.data('documentsUrl') || '').trim();
        if (documentsUrl) {
            $('#employee_documents_frame').attr('src', documentsUrl);
        }
    });

    $('#employeeDocumentsModal').on('hidden.bs.modal hidden.coreui.modal', function () {
        $('#employee_documents_frame').attr('src', 'about:blank');
    });

    $modal.on('hidden.bs.modal', function () {
        $('#employee_detail_archived_badge').addClass('d-none');
        $('#employeeReactivateForm').attr('action', '');
        $('#employeeReactivateButton').addClass('d-none');
        if ($detailResetForm.length) {
            $detailResetForm.attr('action', '#');
            $detailResetForm.addClass('d-none');
            $detailResetForm.find('.employee-detail-reset-btn').prop('disabled', true);
        }
    });
}

function initEmployeeRfidModal() {
    if (!$) return;
    const $modal = $('#employeeRfidModal');
    if (!$modal.length) return;

    const nfcLatestUrl = '/api/nfc/latest';
    let rfidTimer = null;
    let rfidSession = null;

    function stopRfidModalPolling() {
        if (rfidTimer) {
            clearTimeout(rfidTimer);
            rfidTimer = null;
        }
    }

    function setRfidModalStatus(message, type = 'muted') {
        const $status = $('#employee_modal_rfid_status');
        if (!$status.length) return;

        $status
            .text(message || '')
            .removeClass('text-muted text-success text-warning text-danger');

        if (type === 'success') $status.addClass('text-success');
        else if (type === 'warning') $status.addClass('text-warning');
        else if (type === 'danger') $status.addClass('text-danger');
        else $status.addClass('text-muted');
    }

    function setRfidModalDetected(detected) {
        $('#employee_modal_nfc_uid').toggleClass('is-rfid-detected', Boolean(detected));
    }

    function populateEmployeeRfidModal($btn) {
        if (!$btn || !$btn.length) return;

        const employeeName = String($btn.data('name') || 'Employee').trim();
        const employeeCode = String($btn.data('employee-id') || '-').trim();
        const employeeRowId = String($btn.data('employee-row-id') || '').trim();
        const currentRfid = String($btn.data('rfid') || '').trim();

        $('#employee_rfid_name').text(employeeName || 'Employee');
        $('#employee_rfid_employee_id').text('#' + (employeeCode || '-'));
        $('#employee_rfid_employee_row_id').val(employeeRowId);
        $('#employee_modal_nfc_uid').val(currentRfid);
        setRfidModalDetected(Boolean(currentRfid));
        setRfidModalStatus(
            currentRfid
                ? 'Current RFID loaded. Tap a card to replace it.'
                : 'Tap a card to capture or replace RFID.',
            currentRfid ? 'warning' : 'muted'
        );
    }

    function pollRfidModal() {
        if (!rfidSession) return;

        $.getJSON(nfcLatestUrl, {
            after: rfidSession.startedAt,
            consumer: rfidSession.consumer,
        }).done(function (data) {
            if (!rfidSession) return;

            if (data && data.captured && data.nfc_uid) {
                const scanId = String(data.scan_id || data.timestamp || data.nfc_uid);
                if (scanId !== rfidSession.lastScanId) {
                    rfidSession.lastScanId = scanId;
                    $('#employee_modal_nfc_uid').val(String(data.nfc_uid || ''));
                    setRfidModalDetected(true);

                    if (data.assigned || data.exists) {
                        setRfidModalStatus('This RFID is already registered to another employee.', 'warning');
                    } else {
                        setRfidModalStatus('RFID detected. Save to register this card.', 'success');
                    }
                }
            } else if (!rfidSession.lastScanId) {
                setRfidModalDetected(Boolean($('#employee_modal_nfc_uid').val()));
            }

            rfidTimer = setTimeout(pollRfidModal, 2000);
        }).fail(function () {
            setRfidModalStatus('Unable to read RFID scanner right now.', 'danger');
            rfidTimer = setTimeout(pollRfidModal, 3000);
        });
    }

    $(document).on('click', '.open-employee-rfid-modal', function () {
        populateEmployeeRfidModal($(this));
    });

    $modal.on('show.bs.modal show.coreui.modal', function (event) {
        const $button = $(event.relatedTarget || event.targetTrigger || null);
        populateEmployeeRfidModal($button);

        stopRfidModalPolling();
        rfidSession = {
            startedAt: new Date().toISOString(),
            consumer: 'employee-rfid-modal',
            lastScanId: null,
        };
        pollRfidModal();
    });

    $modal.on('hidden.bs.modal hidden.coreui.modal', function () {
        stopRfidModalPolling();
        rfidSession = null;
        $('#employee_rfid_name').text('Employee');
        $('#employee_rfid_employee_id').text('#-');
        $('#employee_rfid_employee_row_id').val('');
        $('#employee_modal_nfc_uid').val('');
        setRfidModalDetected(false);
        setRfidModalStatus('Tap a card to capture or replace RFID.', 'muted');
    });
}

onReady(function () {
    initEmployeeIndexPage();
    initEmployeeDetailsModal();
    initEmployeeRfidModal();
});





