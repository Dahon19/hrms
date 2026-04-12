import { onReady } from './utils';

const resolveTemplateUrl = (template, id) => {
    if (!template) return '';
    const value = String(id || '').trim();
    if (template.includes('__HOLIDAY__')) {
        return template.replace('__HOLIDAY__', value);
    }
    if (/\/0$/.test(template)) {
        return template.replace(/\/0$/, `/${value}`);
    }
    return template;
};

function initAttendanceHolidayCalendar() {
    const calendarEl = document.getElementById('attendanceHolidayCalendar');
    if (!calendarEl || !window.FullCalendar || !window.FullCalendar.Calendar) return;

    let events = [];
    let dateDetails = {};

    try {
        events = JSON.parse(calendarEl.dataset.events || '[]');
    } catch (error) {
        events = [];
    }

    try {
        dateDetails = JSON.parse(calendarEl.dataset.dateDetails || '{}');
    } catch (error) {
        dateDetails = {};
    }

    const selectedDateLabel = document.getElementById('attendanceCalendarSelectedDateLabel');
    const holidayState = document.getElementById('attendanceCalendarHolidayState');
    const onLeaveList = document.getElementById('attendanceCalendarOnLeaveList');
    const pendingLeaveList = document.getElementById('attendanceCalendarPendingLeaveList');
    const holidayActionBtn = document.getElementById('attendanceHolidayActionBtn');
    const holidayDeleteBtn = document.getElementById('attendanceHolidayDeleteBtn');
    const canManage = calendarEl.dataset.canManage === '1';
    const feedUrl = calendarEl.dataset.feedUrl || '';
    const storeUrl = calendarEl.dataset.storeUrl || '';
    const updateTemplate = calendarEl.dataset.updateTemplate || '';
    const deleteTemplate = calendarEl.dataset.deleteTemplate || '';
    let selectedDate = calendarEl.dataset.selectedDate || new Date().toISOString().slice(0, 10);
    let selectedHoliday = null;
    let isLoadingRange = false;

    const holidayForm = document.getElementById('holidayForm');
    const holidayFormMethod = document.getElementById('holidayFormMethod');
    const holidayModalTitle = document.getElementById('holidayModalTitle');
    const holidayModalSubmitBtn = document.getElementById('holidayModalSubmitBtn');
    const holidayDateInput = document.getElementById('holiday_date');
    const holidayNameInput = document.getElementById('holiday_name');
    const holidayTypeInput = document.getElementById('holiday_type');
    const holidayRemarksInput = document.getElementById('holiday_remarks');
    const holidayDeleteForm = document.getElementById('holidayDeleteForm');
    const holidayModalEl = document.getElementById('holidayModal');

    const formatDate = (value) => {
        if (!value) return '';
        const date = new Date(value + 'T00:00:00');
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    };

    const renderLeaveItems = (items, container, emptyLabel) => {
        if (!container) return;
        if (!Array.isArray(items) || items.length === 0) {
            container.innerHTML = `<div class="attendance-calendar-list__empty">${emptyLabel}</div>`;
            return;
        }

        container.innerHTML = items.map((item) => `
            <div class="attendance-calendar-list__item">
                <div class="attendance-calendar-list__name">${item.employee}</div>
                <div class="attendance-calendar-list__meta">${item.type} - ${item.department}</div>
                <div class="attendance-calendar-list__meta">${item.start} to ${item.end}</div>
            </div>
        `).join('');
    };

    const renderHoliday = (holiday) => {
        if (!holidayState) return;
        if (!holiday) {
            holidayState.innerHTML = '<div class="attendance-calendar-holiday-empty">No holiday configured for this date.</div>';
            return;
        }

        const type = holiday.type ? `<div class="attendance-calendar-holiday-card__meta">${holiday.type}</div>` : '';
        const remarks = holiday.remarks ? `<div class="attendance-calendar-holiday-card__remarks mt-2">${holiday.remarks}</div>` : '';
        holidayState.innerHTML = `<div class="attendance-calendar-holiday-card"><div class="attendance-calendar-holiday-card__name">${holiday.name}</div>${type}${remarks}</div>`;
    };

    const updateSummaryCards = (payload) => {
        const statValues = document.querySelectorAll('.attendance-calendar-stat__value');
        const statMeta = document.querySelectorAll('.attendance-calendar-stat__meta');
        if (statValues.length >= 3) {
            statValues[1].textContent = String(payload.onLeaveCount ?? 0);
            statValues[2].textContent = String(payload.pendingLeaveCount ?? 0);
        }
        if (statMeta.length >= 3) {
            const holidayCount = Number(payload.holidayCount ?? 0);
            statMeta[0].textContent = `${holidayCount} holiday setup${holidayCount === 1 ? '' : 's'}`;
        }
    };

    const loadRange = async (start, end, preferredDate) => {
        if (!feedUrl || isLoadingRange) return;
        isLoadingRange = true;

        const url = new URL(feedUrl, window.location.origin);
        url.searchParams.set('start', start);
        url.searchParams.set('end', end);

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Failed to load calendar range: ${response.status}`);
            }

            const payload = await response.json();
            dateDetails = payload.dateDetails || {};

            calendar.removeAllEvents();
            calendar.addEventSource(payload.events || []);
            updateSummaryCards(payload);

            const nextSelectedDate = preferredDate && dateDetails[preferredDate]
                ? preferredDate
                : (preferredDate || payload.selectedDate || start);

            syncPanels(nextSelectedDate);
        } catch (error) {
            console.error(error);
        } finally {
            isLoadingRange = false;
        }
    };

    const showModal = (modalEl) => {
        if (!modalEl) return false;
        if (typeof window.hrmsShowModal === 'function') {
            window.hrmsShowModal(modalEl);
            return true;
        }
        if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
            window.jQuery(modalEl).modal('show');
            return true;
        }
        if (window.coreui?.Modal?.getOrCreateInstance) {
            window.coreui.Modal.getOrCreateInstance(modalEl).show();
            return true;
        }
        if (window.bootstrap?.Modal?.getOrCreateInstance) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return true;
        }

        return false;
    };

    const populateHolidayForm = (mode) => {
        const details = dateDetails[selectedDate] || {};
        const holiday = details.holiday || null;

        if (mode === 'edit' && !holiday) {
            return false;
        }

        if (holidayModalTitle) holidayModalTitle.textContent = mode === 'edit' ? 'Edit Holiday' : 'Set Holiday';
        if (holidayModalSubmitBtn) holidayModalSubmitBtn.textContent = mode === 'edit' ? 'Update Holiday' : 'Save Holiday';
        if (holidayFormMethod) holidayFormMethod.value = mode === 'edit' ? 'PUT' : 'POST';
        if (holidayDateInput) holidayDateInput.value = selectedDate;
        if (holidayNameInput) holidayNameInput.value = holiday?.name || '';
        if (holidayTypeInput) holidayTypeInput.value = holiday?.type || '';
        if (holidayRemarksInput) holidayRemarksInput.value = holiday?.remarks || '';

        holidayForm.setAttribute('action', mode === 'edit' && holiday
            ? resolveTemplateUrl(updateTemplate, holiday.id)
            : storeUrl);

        return true;
    };

    const openHolidayModal = (mode) => {
        if (!canManage || !holidayForm || !holidayModalEl) return;
        if (!populateHolidayForm(mode)) return;

        showModal(holidayModalEl);
    };

    const syncPanels = (dateKey) => {
        selectedDate = dateKey;
        calendarEl.dataset.selectedDate = selectedDate;
        const details = dateDetails[dateKey] || {};
        selectedHoliday = details.holiday || null;

        if (selectedDateLabel) selectedDateLabel.textContent = formatDate(dateKey);
        if (holidayDateInput) holidayDateInput.value = selectedDate;
        renderHoliday(selectedHoliday);
        renderLeaveItems(details.on_leave || [], onLeaveList, 'No employees are marked on leave for this date.');
        renderLeaveItems(details.pending_leave || [], pendingLeaveList, 'No pending leave requests overlap this date.');

        if (holidayActionBtn) holidayActionBtn.textContent = selectedHoliday ? 'Edit Holiday' : 'Set Holiday';
        if (holidayDeleteBtn) holidayDeleteBtn.classList.toggle('d-none', !selectedHoliday);
    };

    if (holidayActionBtn) {
        holidayActionBtn.addEventListener('click', (event) => {
            event.preventDefault();
            openHolidayModal(selectedHoliday ? 'edit' : 'create');
        });
    }

    ['show.bs.modal', 'show.coreui.modal'].forEach((eventName) => {
        holidayModalEl?.addEventListener(eventName, () => {
            populateHolidayForm(selectedHoliday ? 'edit' : 'create');
        });
    });

    if (holidayDeleteBtn && holidayDeleteForm) {
        holidayDeleteBtn.addEventListener('click', () => {
            if (!selectedHoliday) return;
            holidayDeleteForm.setAttribute('action', resolveTemplateUrl(deleteTemplate, selectedHoliday.id));
            holidayDeleteForm.submit();
        });
    }

    const calendar = new window.FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        initialDate: selectedDate,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek',
        },
        events,
        dateClick(info) {
            syncPanels(info.dateStr);
        },
        eventClick(info) {
            syncPanels(info.event.startStr.slice(0, 10));
        },
        eventDidMount(info) {
            const status = info.event.extendedProps?.statusLabel || '';
            const employee = info.event.extendedProps?.employee || '';
            const holidayName = info.event.extendedProps?.holidayName || '';
            const title = holidayName || employee || info.event.title;
            if (title) {
                info.el.setAttribute('title', status ? `${title} - ${status}` : title);
            }
        },
        datesSet(info) {
            const rangeStart = info.startStr.slice(0, 10);
            const rangeEnd = info.endStr.slice(0, 10);
            loadRange(rangeStart, rangeEnd, selectedDate);
        },
    });

    calendar.render();
    syncPanels(selectedDate);
}

onReady(function () {
    initAttendanceHolidayCalendar();
});
