import { $, onReady } from './utils';

function getEditPayload($button) {
    if (!$button || !$button.length) return {};
    const payload = $button.data('edit');
    return payload && typeof payload === 'object' ? payload : {};
}

function initLeaveHints() {
    const hints = document.getElementById('leave-hints');
    if (!hints) return;

    let remainingByTypeYear = null;
    const raw = hints.dataset.remaining;
    if (raw) {
        try {
            remainingByTypeYear = JSON.parse(raw);
        } catch (error) {
            remainingByTypeYear = null;
        }
    }

    const leaveType = document.getElementById('leave_type_id');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const daysEl = document.getElementById('leave-days');
    const remainingEl = document.getElementById('leave-remaining');

    if (!leaveType || !startDate || !endDate || !daysEl || !remainingEl) return;

    function parseDate(value) {
        if (!value) return null;
        const parts = value.split('-').map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function computeDays(start, end) {
        const diff = end - start;
        if (Number.isNaN(diff)) return null;
        return Math.floor(diff / 86400000) + 1;
    }

    function updateHints() {
        const typeId = leaveType.value;
        const start = parseDate(startDate.value);
        const end = parseDate(endDate.value);
        const year = start ? start.getFullYear() : null;

        let remaining = null;
        if (remainingByTypeYear && typeId && year && remainingByTypeYear[year] && remainingByTypeYear[year][typeId] !== undefined) {
            remaining = remainingByTypeYear[year][typeId];
        }

        if (remaining !== null) {
            remainingEl.textContent = 'Remaining balance: ' + remaining + ' day(s)';
        } else {
            remainingEl.textContent = 'Remaining balance: -';
        }

        if (start && end) {
            const days = computeDays(start, end);
            if (days && days > 0) {
                daysEl.textContent = 'Days requested: ' + days;
                return;
            }
        }

        daysEl.textContent = 'Days requested: -';
    }

    [leaveType, startDate, endDate].forEach((el) => {
        el.addEventListener('change', updateHints);
        el.addEventListener('input', updateHints);
    });

    updateHints();
}

function updateLeaveHints(remainingByTypeYear, leaveType, startDate, endDate, daysEl, remainingEl) {
    function parseDate(value) {
        if (!value) return null;
        const parts = value.split('-').map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function computeDays(start, end) {
        const diff = end - start;
        if (Number.isNaN(diff)) return null;
        return Math.floor(diff / 86400000) + 1;
    }

    const typeId = leaveType.value;
    const start = parseDate(startDate.value);
    const end = parseDate(endDate.value);
    const year = start ? start.getFullYear() : null;

    let remaining = null;
    if (remainingByTypeYear && typeId && year && remainingByTypeYear[year] && remainingByTypeYear[year][typeId] !== undefined) {
        remaining = remainingByTypeYear[year][typeId];
    }

    if (remaining !== null) {
        remainingEl.textContent = 'Remaining balance: ' + remaining + ' day(s)';
    } else {
        remainingEl.textContent = 'Remaining balance: -';
    }

    if (start && end) {
        const days = computeDays(start, end);
        if (days && days > 0) {
            daysEl.textContent = 'Days requested: ' + days;
            return;
        }
    }

    daysEl.textContent = 'Days requested: -';
}

function initLeaveEditModal() {
    if (!$) return;
    const $modal = $('#leaveEditModal');
    const $buttons = $('.edit-leave');
    if (!$modal.length || !$buttons.length) return;

    let remainingByTypeYear = null;
    const hints = document.getElementById('edit-leave-hints');
    if (hints && hints.dataset.remaining) {
        try {
            remainingByTypeYear = JSON.parse(hints.dataset.remaining);
        } catch (error) {
            remainingByTypeYear = null;
        }
    }

    const leaveType = document.getElementById('edit_leave_type_id');
    const startDate = document.getElementById('edit_start_date');
    const endDate = document.getElementById('edit_end_date');
    const daysEl = document.getElementById('edit-leave-days');
    const remainingEl = document.getElementById('edit-leave-remaining');
    const notesEl = document.getElementById('edit-leave-notes');
    const form = document.getElementById('leaveEditForm');

    if (!leaveType || !startDate || !endDate || !daysEl || !remainingEl || !form) return;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatReviewerNotes(raw) {
        if (!raw || !String(raw).trim()) return '';
        const text = String(raw).trim();
        const parts = text.split('|').map((part) => part.trim()).filter(Boolean);
        let suggested = '';
        let notes = '';
        let source = '';

        parts.forEach((part) => {
            if (part.toLowerCase().startsWith('from ')) {
                source = part.replace(/^from\s+/i, '');
            } else if (part.toLowerCase().startsWith('suggested dates:')) {
                suggested = part.replace(/^suggested dates:\s*/i, '');
            } else {
                notes = notes ? (notes + ' ' + part) : part;
            }
        });

        if (!suggested && parts.length === 1) {
            notes = notes || parts[0];
        }

        let header = 'Feedback';
        if (source) {
            header = 'Feedback from ' + escapeHtml(source);
        }
        let html = '<div class="font-weight-bold mb-2 d-flex align-items-center justify-content-between">' +
            '<span>' + header + '</span>' +
            '<i class="cil-comment-bubble text-info"></i>' +
            '</div>';
        if (notes) {
            html += '<div><strong>Notes:</strong> ' + escapeHtml(notes) + '</div>';
        }
        if (suggested) {
            html += '<div class="mt-2"><strong>Suggested Dates:</strong> ' + escapeHtml(suggested) + '</div>';
        }
        return html;
    }

    function refreshHints() {
        updateLeaveHints(remainingByTypeYear, leaveType, startDate, endDate, daysEl, remainingEl);
    }

    [leaveType, startDate, endDate].forEach((el) => {
        el.addEventListener('change', refreshHints);
        el.addEventListener('input', refreshHints);
    });

    function populateLeaveEditModal($btn) {
        if (!$btn || !$btn.length) return;
        const payload = getEditPayload($btn);
        const updateUrl = payload.update_url || $btn.data('update-url') || '#';
        const leaveId = payload.leave_id || $btn.data('leave-id') || '';
        const typeId = payload.leave_type_id || $btn.data('leave-type-id') || '';
        const start = payload.start_date || $btn.data('start-date') || '';
        const end = payload.end_date || $btn.data('end-date') || '';
        const reason = payload.reason || $btn.data('reason') || '';
        const notes = payload.notes || $btn.data('notes') || '';

        form.setAttribute('action', updateUrl);
        const leaveIdInput = document.getElementById('edit_leave_id');
        if (leaveIdInput) leaveIdInput.value = leaveId;
        $('#edit_leave_type_id').val(typeId).trigger('change');
        $('#edit_start_date').val(start);
        $('#edit_end_date').val(end);
        $('#edit_reason').val(reason);

        if (notesEl) {
            const html = formatReviewerNotes(notes);
            if (html) {
                notesEl.innerHTML = html;
                notesEl.classList.remove('d-none');
            } else {
                notesEl.textContent = '';
                notesEl.classList.add('d-none');
            }
        }

        refreshHints();
    }

    $buttons.on('click', function () {
        populateLeaveEditModal($(this));
    });

    $modal.on('show.bs.modal', function (event) {
        populateLeaveEditModal($(event.relatedTarget));
    });

    $modal.on('show.coreui.modal', function (event) {
        populateLeaveEditModal($(event.relatedTarget || event.targetTrigger || null));
    });
}

function initLeaveIndexModal() {
    if (!$) return;
    const $modal = $('#leaveDetailsModal');
    const $viewButtons = $('.view-leave');
    if (!$modal.length || !$viewButtons.length) return;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderReviewerNotes(raw) {
        if (!raw || !String(raw).trim()) return '';
        const text = String(raw).trim();
        const parts = text.split('|').map((part) => part.trim()).filter(Boolean);
        let suggested = '';
        let notes = '';

        parts.forEach((part) => {
            if (part.toLowerCase().startsWith('from ')) {
                return;
            }
            if (part.toLowerCase().startsWith('suggested dates:')) {
                suggested = part.replace(/^suggested dates:\s*/i, '');
            } else {
                notes = notes ? (notes + ' ' + part) : part;
            }
        });

        if (!suggested && parts.length === 1) {
            notes = notes || parts[0];
        }

        let html = '<div class="font-weight-bold mb-2">Review Notes</div>';
        if (suggested) {
            html += '<div><strong>Suggested Dates:</strong> ' + escapeHtml(suggested) + '</div>';
        }
        if (notes) {
            html += '<div class="mt-1"><strong>Notes:</strong> ' + escapeHtml(notes) + '</div>';
        }
        return html;
    }

    $viewButtons.on('click', function () {
        const $row = $(this).closest('tr');
        $('#leave-employee').text($row.data('employee'));
        $('#leave-type').text($row.data('type'));
        $('#leave-dates').text($row.data('dates'));
        $('#leave-reason').text($row.data('reason'));
        const rawStatus = $row.data('status');
        let displayStatus = rawStatus;
        if (rawStatus === 'Declined' || rawStatus === 'HR Declined') {
            displayStatus = 'Needs Revision';
        } else if (rawStatus === 'Approved') {
            displayStatus = 'Head Approved';
        }
        $('#leave-status-badge').text(displayStatus);
        const rawStage = $row.data('stage');
        const displayStage = (rawStage === 'Declined' || rawStage === 'HR Declined')
            ? 'Needs Revision'
            : rawStage;
        if (displayStage === 'Needs Revision') {
            $('#leave-stage-text').text('');
        } else {
            $('#leave-stage-text').html('<strong>Current Stage:</strong> ' + displayStage);
        }

        const notesRaw = $row.data('notes');
        const notesHtml = renderReviewerNotes(notesRaw);
        if (notesHtml) {
            $('#leave-notes').html(notesHtml);
            $('#leave-notes-section').removeClass('d-none');
        } else {
            $('#leave-notes').empty();
            $('#leave-notes-section').addClass('d-none');
        }

        const attachment = $row.data('attachment');
        if (attachment) {
            $('#leave-attachment').html(
                '<div class="embed-responsive embed-responsive-4by3">' +
                '<iframe class="embed-responsive-item" src="' + attachment + '" title="Leave Attachment"></iframe>' +
                '</div>'
            );
        } else {
            $('#leave-attachment').html('<span class="text-muted small italic">No attachments provided.</span>');
        }

        $modal.modal('show');
    });
}

function initLeaveHeadModal() {
    if (!$) return;
    const hasApprovalRows = $('.approval-row').length > 0;
    if (!hasApprovalRows && $('.view-leave').length) return;

    const $rows = hasApprovalRows ? $('.approval-row') : $('.leave-row');
    const $modal = $('#leaveApprovalModal').length ? $('#leaveApprovalModal') : $('#leaveDetailsModal');
    const $statusBadge = $('#leave-status-badge-approval').length ? $('#leave-status-badge-approval') : $('#leave-status-badge');
    if (!$rows.length || !$modal.length || !$statusBadge.length) return;

    const useApprovalIds = $modal.attr('id') === 'leaveApprovalModal';

    const fields = {
        employee: useApprovalIds ? $('#leave-employee-approval') : $('#leave-employee'),
        department: useApprovalIds ? $('#leave-department-approval') : $('#leave-department'),
        type: useApprovalIds ? $('#leave-type-approval') : $('#leave-type'),
        dates: useApprovalIds ? $('#leave-dates-approval') : $('#leave-dates'),
        status: $statusBadge,
        reason: useApprovalIds ? $('#leave-reason-approval') : $('#leave-reason'),
        attachment: useApprovalIds ? $('#leave-attachment-approval') : $('#leave-attachment'),
    };

    $rows.on('click', function (event) {
        const $trigger = $(event.target).closest('.view-leave');
        if (hasApprovalRows && !$trigger.length) return;
        if (!hasApprovalRows && $(event.target).closest('form, button, a').length) return;
        const $row = $trigger.length ? $trigger : $(this);

        fields.employee.text($row.data('employee'));
        fields.department.text($row.data('department'));
        fields.type.text($row.data('type'));
        fields.dates.text($row.data('dates'));

        const rawStatus = $row.data('status');
        const displayStatus = (rawStatus === 'Declined' || rawStatus === 'HR Declined')
            ? 'Needs Revision'
            : rawStatus;
        fields.status.text(displayStatus).removeClass().addClass('badge px-3 py-2');
        if (rawStatus === 'On Process') fields.status.addClass('badge-success');
        else if (rawStatus === 'HR Approved' || rawStatus === 'Approved') fields.status.addClass('badge-info');
        else if (rawStatus === 'Declined' || rawStatus === 'HR Declined') fields.status.addClass('badge-warning');
        else fields.status.addClass('badge-warning');

        fields.reason.text('"' + $row.data('reason') + '"');

        const attachment = $row.data('attachment');
        if (attachment) {
            fields.attachment.html(
                '<div class="embed-responsive embed-responsive-4by3">' +
                '<iframe class="embed-responsive-item" src="' + attachment + '" title="Leave Attachment"></iframe>' +
                '</div>'
            );
        } else {
            fields.attachment.html('<span class="text-muted small italic">No attachment</span>');
        }

        $modal.modal('show');
    });
}

function initLeaveApprovalConfirm() {
    const forms = document.querySelectorAll('.confirm-approve-form');
    forms.forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!form.dataset.confirmMessage) {
            form.dataset.confirmMessage = 'Approve this leave request?';
        }

        if (!form.dataset.confirmTitle) {
            form.dataset.confirmTitle = 'Confirm Approval';
        }

        if (!form.dataset.confirmLabel) {
            form.dataset.confirmLabel = 'Approve';
        }

        if (!form.dataset.confirmVariant) {
            form.dataset.confirmVariant = 'primary';
        }
    });
}

function initLeavePresidentModal() {
    if (!$) return;
    if ($('.view-leave').length) return;
    const $rows = $('.leave-row');
    const $modal = $('#leaveDetailsModal');
    const $status = $('#leave-status');
    if (!$rows.length || !$modal.length || !$status.length) return;

    const fields = {
        employee: $('#leave-employee'),
        department: $('#leave-department'),
        type: $('#leave-type'),
        dates: $('#leave-dates'),
        status: $status,
        reason: $('#leave-reason'),
        attachment: $('#leave-attachment'),
    };

    $rows.on('click', function (event) {
        if ($(event.target).closest('form, button, a').length) return;
        const $row = $(this);

        fields.employee.text($row.data('employee'));
        fields.department.text($row.data('department'));
        fields.type.text($row.data('type'));
        fields.dates.text($row.data('dates'));
        fields.status.text($row.data('status'));
        fields.reason.text($row.data('reason'));

        const attachment = $row.data('attachment');
        if (attachment) {
            fields.attachment.html(
                '<div class="embed-responsive embed-responsive-4by3">' +
                '<iframe class="embed-responsive-item" src="' + attachment + '" title="Leave Attachment"></iframe>' +
                '</div>'
            );
        } else {
            fields.attachment.text('-');
        }

        $modal.modal('show');
    });
}

function initLeaveCalendar() {
    const calendarEl = document.getElementById('hrLeaveCalendar');
    if (!calendarEl) return;
    let events = [];
    if (calendarEl.dataset.events) {
        try {
            events = JSON.parse(calendarEl.dataset.events);
        } catch (err) {
            events = [];
        }
    }

    let attempts = 0;
    function tryInit() {
        if (!window.FullCalendar || !window.FullCalendar.Calendar) {
            attempts += 1;
            if (attempts < 10) {
                setTimeout(tryInit, 200);
            }
            return;
        }

        if (calendarEl.dataset.fcInitialized === 'true') {
            return;
        }

        const calendar = new window.FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
            },
            events: events,
            eventDidMount: function (info) {
                const status = info.event.extendedProps && info.event.extendedProps.status;
                const dept = info.event.extendedProps && info.event.extendedProps.department;
                if (status) {
                    info.el.setAttribute('title', status + (dept ? ' - ' + dept : ''));
                }
            },
        });

        calendar.render();
        calendarEl.dataset.fcInitialized = 'true';
    }

    const modalEl = document.getElementById('leaveCalendarModal');
    if (modalEl && window.jQuery) {
        $(modalEl).on('shown.bs.modal', function () {
            tryInit();
        });
        return;
    }

    tryInit();
}

function initLeaveCalendarModal() {
    if (!$) return;
    const $btn = $('#leaveCalendarBtn');
    const $modal = $('#leaveCalendarModal');
    if (!$btn.length || !$modal.length) return;
    $btn.on('click', function (event) {
        event.preventDefault();
        $modal.modal('show');
    });
}

function initHeadRevisionModal() {
    if (!$) return;
    const page = document.getElementById('leaveApprovalsPage');
    const $modal = $('#headRevisionModal');
    if (!$modal.length) return;

    const form = document.getElementById('headRevisionForm');
    const startInput = document.getElementById('suggest_start_date');
    const endInput = document.getElementById('suggest_end_date');
    const notesInput = document.getElementById('suggest_notes');
    const actionInput = document.getElementById('revision_action');

    const populateRevisionModal = (triggerSource) => {
        const button = $(triggerSource);
        if (!button || !button.length) return;

        const action = button.data('action') || '#';
        const start = button.data('start') || '';
        const end = button.data('end') || '';

        if (form) form.setAttribute('action', action);
        if (actionInput) actionInput.value = action;

        if (startInput) startInput.value = start;
        if (endInput) endInput.value = end;
        if (notesInput) notesInput.value = '';
    };

    $modal.on('show.bs.modal', function (event) {
        populateRevisionModal(event.relatedTarget);
    });

    $modal.on('show.coreui.modal', function (event) {
        populateRevisionModal(event.relatedTarget || event.targetTrigger || null);
    });

    if (page && page.dataset.hasRevisionErrors === '1') {
        const oldAction = page.dataset.revisionAction || '';
        const oldStart = page.dataset.revisionStart || '';
        const oldEnd = page.dataset.revisionEnd || '';
        const oldNotes = page.dataset.revisionNotes || '';

        if (form && oldAction) form.setAttribute('action', oldAction);
        if (actionInput) actionInput.value = oldAction;
        if (startInput) startInput.value = oldStart;
        if (endInput) endInput.value = oldEnd;
        if (notesInput) notesInput.value = oldNotes;

        $modal.modal('show');
    }
}

function initPresidentDecisionModal() {
    if (!$) return;
    const page = document.getElementById('leaveApprovalsPage');
    const $modal = $('#presidentDecisionModal');
    if (!$modal.length) return;

    const form = document.getElementById('presidentDecisionForm');
    const actionInput = document.getElementById('president_action');
    const statusInput = document.getElementById('president_status');
    const notesInput = document.getElementById('president_notes');

    const populatePresidentModal = (triggerSource) => {
        const button = $(triggerSource);
        if (!button || !button.length) return;

        const action = button.data('action') || '#';
        if (form) form.setAttribute('action', action);
        if (actionInput) actionInput.value = action;
        if (statusInput) statusInput.value = 'Declined';
        if (notesInput) notesInput.value = '';
    };

    $modal.on('show.bs.modal', function (event) {
        populatePresidentModal(event.relatedTarget);
    });

    $modal.on('show.coreui.modal', function (event) {
        populatePresidentModal(event.relatedTarget || event.targetTrigger || null);
    });

    if (page && page.dataset.hasPresidentErrors === '1') {
        const oldAction = page.dataset.presidentAction || '';
        const oldStatus = page.dataset.presidentStatus || 'Declined';
        const oldNotes = page.dataset.presidentNotes || '';

        if (form && oldAction) form.setAttribute('action', oldAction);
        if (actionInput) actionInput.value = oldAction;
        if (statusInput) statusInput.value = oldStatus;
        if (notesInput) notesInput.value = oldNotes;

        $modal.modal('show');
    }
}

function initLeaveErrorModals() {
    if (!$) return;
    const $page = $('#leavesIndexPage');
    if (!$page.length) return;

    const hasErrors = String($page.data('hasErrors')) === '1';
    if (!hasErrors) return;

    const requestError = String($page.data('requestError')) === '1';
    const editError = String($page.data('editError')) === '1';

    if (requestError) {
        $('#leaveRequestModal').modal('show');
    }

    if (editError) {
        const form = document.getElementById('leaveEditForm');
        const leaveId = document.getElementById('edit_leave_id')?.value;
        const template = form ? form.getAttribute('data-update-template') : null;
        if (form && template && leaveId) {
            form.setAttribute('action', template.replace(/\/0$/, '/' + leaveId));
        }
        $('#leaveEditModal').modal('show');
    }
}

function initLeaveBalancesToolbar() {
    return;
}

function initLeaveHistoryToolbar() {
    const table = document.getElementById('leavesRequestsTable');
    const searchInput = document.querySelector('.leave-history-toolbar input[name="leave_history_search"]');
    const statusInput = document.getElementById('leaveHistoryStatusFilter');
    const applyButton = document.getElementById('leaveHistoryApplyButton');
    const countChip = document.getElementById('leaveHistoryCountChip');

    if (!table || !searchInput || !statusInput || !applyButton || !countChip) {
        return;
    }

    const rows = Array.from(table.querySelectorAll('tbody .leave-row'));
    if (!rows.length) {
        return;
    }

    const normalize = (value) => String(value || '').trim().toLowerCase();

    const renderCount = (visibleCount) => {
        countChip.textContent = visibleCount + ' shown';
    };

    const applyFilter = () => {
        const search = normalize(searchInput.value);
        const status = normalize(statusInput.value);
        let visible = 0;

        rows.forEach((row) => {
            const haystack = [
                row.dataset.type,
                row.dataset.dates,
                row.dataset.status,
                row.dataset.stage,
                row.dataset.reason,
            ].map(normalize).join(' ');

            const matched = (!search || haystack.includes(search))
                && (!status || normalize(row.dataset.status) === status);

            row.classList.toggle('d-none', !matched);
            if (matched) visible += 1;
        });

        renderCount(visible);
    };

    applyButton.addEventListener('click', applyFilter);
    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyFilter();
        }
    });
    statusInput.addEventListener('change', applyFilter);

    renderCount(rows.length);
}

onReady(function () {
    initLeaveHints();
    initLeaveIndexModal();
    initLeaveHeadModal();
    initLeaveApprovalConfirm();
    initLeavePresidentModal();
    initLeaveCalendar();
    initLeaveCalendarModal();
    initLeaveEditModal();
    initHeadRevisionModal();
    initPresidentDecisionModal();
    initLeaveErrorModals();
    initLeaveBalancesToolbar();
    initLeaveHistoryToolbar();
});


