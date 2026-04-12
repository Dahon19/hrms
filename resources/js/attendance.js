import importedJQuery from 'jquery';
import { onReady } from './utils';

const getAttendanceJq = () => window.jQuery || window.$ || importedJQuery;

function initAttendanceKiosk() {
    const kioskContainer = document.getElementById('attendanceKioskCard');
    if (!kioskContainer) return;

    const fullscreenTarget = document.getElementById('attendanceFullscreenTarget');
    const enterButton = document.getElementById('attendanceEnterFullscreen');
    const exitButton = document.getElementById('attendanceExitFullscreen');
    const confirmOverlay = document.getElementById('attendanceFullscreenConfirm');
    const confirmMessage = document.getElementById('attendanceConfirmMessage');
    const confirmProceed = document.getElementById('attendanceConfirmProceed');
    const confirmCancel = document.getElementById('attendanceConfirmCancel');
    const statusMessage = document.getElementById('kioskStatusMessage');
    const body = document.body;
    const html = document.documentElement;
    const toastRegion = document.getElementById('hrms-toast-region');
    const toastOriginalParent = toastRegion ? toastRegion.parentNode : null;
    const toastOriginalNextSibling = toastRegion ? toastRegion.nextSibling : null;
    let confirmOpen = false;

    const clockEl = document.getElementById('realtime-clock');
    let serverTimeMs = null;
    let isToggling = false;

    if (clockEl) {
        const storedTs = parseInt(clockEl.getAttribute('data-timestamp') || '', 10);
        if (!isNaN(storedTs)) {
            serverTimeMs = storedTs;
        } else {
            serverTimeMs = Date.now();
        }

        function updateClock() {
            serverTimeMs += 1000;
            const now = new Date(serverTimeMs);
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            clockEl.textContent = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        }

        setInterval(updateClock, 1000);
    }

    if (!fullscreenTarget || !enterButton || !exitButton) return;

    const applyFullscreenState = (active) => {
        body.classList.toggle('kiosk-fullscreen-active', active);
        html.classList.toggle('kiosk-fullscreen-active', active);
        fullscreenTarget.classList.toggle('is-fullscreen', active);
        enterButton.classList.toggle('d-none', active);
        exitButton.classList.add('d-none');
        exitButton.classList.remove('is-visible');
        enterButton.setAttribute('aria-hidden', active ? 'true' : 'false');
        exitButton.setAttribute('aria-hidden', 'true');

        if (toastRegion) {
            if (active) {
                fullscreenTarget.appendChild(toastRegion);
            } else if (toastOriginalParent) {
                if (toastOriginalNextSibling && toastOriginalNextSibling.parentNode === toastOriginalParent) {
                    toastOriginalParent.insertBefore(toastRegion, toastOriginalNextSibling);
                } else {
                    toastOriginalParent.appendChild(toastRegion);
                }
            }
        }
    };

    const isTargetInFullscreen = () =>
        document.fullscreenElement === fullscreenTarget ||
        document.webkitFullscreenElement === fullscreenTarget ||
        document.msFullscreenElement === fullscreenTarget;

    const animateTransition = () => {
        fullscreenTarget.classList.add('is-fs-animating');
        window.setTimeout(() => {
            fullscreenTarget.classList.remove('is-fs-animating');
        }, 320);
    };

    const requestTargetFullscreen = async () => {
        if (fullscreenTarget.requestFullscreen) {
            await fullscreenTarget.requestFullscreen();
            return;
        }
        if (fullscreenTarget.webkitRequestFullscreen) {
            fullscreenTarget.webkitRequestFullscreen();
            return;
        }
        if (fullscreenTarget.msRequestFullscreen) {
            fullscreenTarget.msRequestFullscreen();
        }
    };

    const exitTargetFullscreen = async () => {
        if (document.exitFullscreen) {
            await document.exitFullscreen();
            return;
        }
        if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
            return;
        }
        if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    };

    const enterFullscreen = async () => {
        if (isToggling || isTargetInFullscreen()) return;
        isToggling = true;
        animateTransition();
        try {
            await requestTargetFullscreen();
            applyFullscreenState(true);
        } catch (error) {
            applyFullscreenState(false);
            if (statusMessage) {
                statusMessage.textContent = 'Fullscreen is blocked by this browser or context.';
            }
            console.error('Fullscreen request failed', error);
        } finally {
            window.setTimeout(() => {
                isToggling = false;
            }, 320);
        }
    };

    const exitFullscreen = async () => {
        if (isToggling) return;
        isToggling = true;
        animateTransition();
        try {
            if (isTargetInFullscreen()) {
                await exitTargetFullscreen();
            }
            applyFullscreenState(false);
        } catch (error) {
            console.error('Fullscreen exit failed', error);
            applyFullscreenState(false);
        } finally {
            window.setTimeout(() => {
                isToggling = false;
            }, 320);
        }
    };

    const onFullscreenChanged = () => {
        const active = isTargetInFullscreen();
        applyFullscreenState(active);
        if (!active) {
            fullscreenTarget.classList.remove('is-fs-animating');
        }
    };

    const askFullscreenConfirm = (message, proceedLabel) => new Promise((resolve) => {
        if (!confirmOverlay || !confirmMessage || !confirmProceed || !confirmCancel) {
            resolve(window.confirm(message));
            return;
        }

        confirmOpen = true;
        let done = false;
        const finish = (result) => {
            if (done) return;
            done = true;
            confirmOpen = false;
            confirmOverlay.classList.remove('is-open');
            confirmOverlay.setAttribute('aria-hidden', 'true');
            confirmProceed.removeEventListener('click', onProceed);
            confirmCancel.removeEventListener('click', onCancel);
            confirmOverlay.removeEventListener('click', onBackdrop);
            document.removeEventListener('keydown', onEsc);
            resolve(result);
        };
        const onProceed = () => finish(true);
        const onCancel = () => finish(false);
        const onBackdrop = (event) => {
            if (event.target === confirmOverlay) finish(false);
        };
        const onEsc = (event) => {
            if (event.key === 'Escape') finish(false);
        };

        confirmMessage.textContent = message;
        confirmProceed.textContent = proceedLabel;
        confirmOverlay.setAttribute('aria-hidden', 'false');
        confirmOverlay.classList.add('is-open');

        confirmProceed.addEventListener('click', onProceed);
        confirmCancel.addEventListener('click', onCancel);
        confirmOverlay.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onEsc);
    });

    enterButton.addEventListener('click', async (e) => {
        e.preventDefault();
        const confirmed = await askFullscreenConfirm(
            'Enter fullscreen mode for attendance kiosk?',
            'Enter Fullscreen',
        );
        if (!confirmed) return;
        enterFullscreen();
    });

    exitButton.addEventListener('click', async (e) => {
        e.preventDefault();
        const confirmed = await askFullscreenConfirm(
            'Exit fullscreen mode and return to normal HR view?',
            'Exit Fullscreen',
        );
        if (!confirmed) return;
        exitFullscreen();
    });

    document.addEventListener('fullscreenchange', onFullscreenChanged);
    document.addEventListener('webkitfullscreenchange', onFullscreenChanged);
    document.addEventListener('msfullscreenchange', onFullscreenChanged);
    applyFullscreenState(false);
}

function initAttendanceNfcKiosk() {
    const kioskCard = document.getElementById('attendanceKioskCard');
    if (!kioskCard || kioskCard.dataset.kioskEnabled !== '1') return;

    const profileCard = document.getElementById('kioskProfileCard');
    const avatarEl = document.getElementById('kioskAvatar');
    const nameEl = document.getElementById('kioskName');
    const deptEl = document.getElementById('kioskDepartment');
    const statusEl = document.getElementById('kioskStatusMessage');
    if (!profileCard || !avatarEl || !nameEl || !deptEl || !statusEl) return;

    const csrfToken = kioskCard.dataset.csrf || '';
    const nfcLatestUrl = kioskCard.dataset.nfcUrl || '/api/nfc/latest';
    const attendanceUrl = kioskCard.dataset.attendanceUrl || '';
    const feedUrl = kioskCard.dataset.feedUrl || '';
    const todayDate = kioskCard.dataset.today || '';
    const attendanceTableEl = document.getElementById('attendanceTable');
    const jq = getAttendanceJq();
    const attendanceTableJq = jq && attendanceTableEl ? jq(attendanceTableEl) : null;

    let hideTimer = null;
    let cooldownUntil = 0;
    let lastUid = null;
    let pollTimer = null;
    let pollDelayMs = 1000;
    const optimisticRows = new Map();
    const minPollDelayMs = 1000;
    const maxIdlePollDelayMs = 5000;
    const maxErrorPollDelayMs = 8000;
    const ATTENDANCE_CELL_LABELS = [
        'Employee',
        'Morning In',
        'Morning Out',
        'Afternoon In',
        'Afternoon Out',
        'Status',
        'Last Tap',
    ];

    const fallbackSvg = (letter) => {
        const safe = (letter || 'U').substring(0, 1).toUpperCase();
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="100%" height="100%" fill="#e9ecef"/><text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="96" fill="#007bff">${safe}</text></svg>`;
        return 'data:image/svg+xml;base64,' + btoa(svg);
    };

    const showProfile = (employee, message, statusType = 'success') => {
        const name = employee?.name || 'Unknown';
        const dept = employee?.department || '';
        nameEl.textContent = name;
        deptEl.textContent = dept;
        avatarEl.src = employee?.avatar_url || fallbackSvg(name[0]);
        profileCard.classList.remove('is-hidden');
        profileCard.classList.remove('is-visible');
        void profileCard.offsetWidth;
        profileCard.classList.add('is-visible');
        setTimeout(() => profileCard.classList.remove('is-visible'), 220);
        setStatus(statusType, message || '');
        if (hideTimer) clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            profileCard.classList.add('is-hidden');
            setStatus('', 'Waiting for scan...');
        }, 5000);
    };

    const setStatus = (type, message) => {
        statusEl.classList.remove('status-success', 'status-warning', 'status-error');
        statusEl.textContent = message || '';
        if (type === 'success') statusEl.classList.add('status-success');
        if (type === 'warning') statusEl.classList.add('status-warning');
        if (type === 'error') statusEl.classList.add('status-error');
    };

    const formatTime = (value) => {
        if (!value) return '--:--';
        const parts = String(value).split(':');
        if (parts.length < 2) return value;
        let hour = parseInt(parts[0], 10);
        const minutes = parts[1].padStart(2, '0');
        const suffix = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12 || 12;
        return `${hour}:${minutes} ${suffix}`;
    };

    const buildStatusBadge = (status) => {
        const s = (status || 'absent').toString().toLowerCase();
        const badgeClass = s === 'present'
            ? 'badge-success'
            : (s === 'late' ? 'badge-warning' : (s === 'official_business' ? 'badge-info' : (s === 'holiday' ? 'badge-danger' : 'badge-secondary')));
        const label = s === 'official_business'
            ? 'Official Business'
            : (s === 'holiday'
                ? 'Holiday'
                : `${s.charAt(0).toUpperCase()}${s.slice(1)}`);
        return `<span class="badge ${badgeClass} attendance-status-pill">${label}</span>`;
    };

    const buildTimeCell = (value, positiveClass) => {
        const hasValue = Boolean(value);
        const className = hasValue ? positiveClass : 'text-muted';
        return `<span class="${className} font-weight-bold">${formatTime(value)}</span>`;
    };

    const getLatestTapSort = (att) => {
        if (!att) return 0;
        const dateOnly = att.date ? String(att.date).split('T')[0].split(' ')[0] : todayDate;
        const times = [att.morning_time_in, att.morning_time_out, att.afternoon_time_in, att.afternoon_time_out].filter(Boolean);
        const ts = times.map((t) => { const p = Date.parse(`${dateOnly}T${String(t)}`); return isNaN(p) ? 0 : p; });
        return ts.length ? Math.max(...ts) : 0;
    };

    const getAttendanceTable = () => {
        const jq = window.jQuery;
        if (!attendanceTableEl || !jq || !jq.fn || !jq.fn.DataTable) return null;
        return jq.fn.dataTable.isDataTable(attendanceTableEl) ? jq(attendanceTableEl).DataTable() : null;
    };

    const destroyAttendanceDataTable = () => {
        const jq = window.jQuery;
        if (!attendanceTableEl || !jq || !jq.fn || !jq.fn.DataTable) return;
        if (!jq.fn.dataTable.isDataTable(attendanceTableEl)) return;

        const dt = jq(attendanceTableEl).DataTable();
        dt.destroy();

        const wrapper = attendanceTableEl.closest('.dataTables_wrapper');
        if (wrapper && wrapper.parentNode) {
            wrapper.parentNode.insertBefore(attendanceTableEl, wrapper);
            wrapper.remove();
        }

        attendanceTableEl.classList.remove('dataTable', 'no-footer');
        attendanceTableEl.removeAttribute('aria-describedby');
        attendanceTableEl.style.width = '';
        jq(attendanceTableEl).find('thead th, tbody td').removeAttr('style');
    };

    const buildEmployeeName = (att) => {
        if (att?.employee?.name) return String(att.employee.name).trim();
        const first = att?.employee?.first_name || '';
        const last = att?.employee?.last_name || '';
        const full = `${first} ${last}`.trim();
        return full || 'Unknown';
    };

    const normalizeAttendanceRows = (rows) => {
        if (!Array.isArray(rows)) return [];
        return rows
            .filter((att) => {
                const dateKey = att?.date ? String(att.date).split('T')[0].split(' ')[0] : todayDate;
                return !todayDate || dateKey === todayDate;
            })
            .sort((left, right) => getLatestTapSort(right) - getLatestTapSort(left));
    };

    const rowKey = (att) => {
        if (!att?.employee_id) return null;
        const dateKey = att?.date ? String(att.date).split('T')[0].split(' ')[0] : todayDate;
        return `${att.employee_id}:${dateKey}`;
    };

    const mergeOptimisticRows = (rows) => {
        const nowMs = Date.now();
        const merged = Array.isArray(rows) ? [...rows] : [];
        const seen = new Set(merged.map((row) => rowKey(row)).filter(Boolean));

        optimisticRows.forEach((entry, key) => {
            if (!entry?.row) {
                optimisticRows.delete(key);
                return;
            }

            if (nowMs - entry.timestamp > 30000) {
                optimisticRows.delete(key);
                return;
            }

            if (!seen.has(key)) {
                merged.push(entry.row);
            }
        });

        return merged;
    };

    const buildAttendanceRowData = (att) => {
        return [
            `<strong>${buildEmployeeName(att)}</strong>`,
            buildTimeCell(att?.morning_time_in, 'text-success'),
            buildTimeCell(att?.morning_time_out, 'text-danger'),
            buildTimeCell(att?.afternoon_time_in, 'text-success'),
            buildTimeCell(att?.afternoon_time_out, 'text-danger'),
            buildStatusBadge(att?.status),
            getLatestTapSort(att),
        ];
    };

    const buildAttendanceRowCells = (att) => `
        <td class="text-left align-middle employee-col" data-label="Employee"><strong>${buildEmployeeName(att)}</strong></td>
        <td class="align-middle text-center" data-label="Morning In">${buildTimeCell(att?.morning_time_in, 'text-success')}</td>
        <td class="align-middle text-center" data-label="Morning Out">${buildTimeCell(att?.morning_time_out, 'text-danger')}</td>
        <td class="align-middle text-center" data-label="Afternoon In">${buildTimeCell(att?.afternoon_time_in, 'text-success')}</td>
        <td class="align-middle text-center" data-label="Afternoon Out">${buildTimeCell(att?.afternoon_time_out, 'text-danger')}</td>
        <td class="align-middle text-center" data-label="Status">${buildStatusBadge(att?.status)}</td>
        <td class="d-none">${getLatestTapSort(att)}</td>
    `;

    const applyClasses = (rowNode, att) => {
        if (!rowNode) return;
        rowNode.className = 'text-center';
        if (att?.employee_id) rowNode.dataset.employeeId = String(att.employee_id);
        const dateKey = att?.date ? String(att.date).split('T')[0].split(' ')[0] : todayDate;
        if (dateKey) rowNode.dataset.date = dateKey;

        const cells = rowNode.querySelectorAll('td');
        if (cells.length < 7) return;
        [
            'text-left align-middle employee-col',
            'align-middle text-center',
            'align-middle text-center',
            'align-middle text-center',
            'align-middle text-center',
            'align-middle text-center',
            'd-none',
        ].forEach((cls, i) => {
            cells[i].className = cls;
            if (i < ATTENDANCE_CELL_LABELS.length - 1) {
                cells[i].setAttribute('data-label', ATTENDANCE_CELL_LABELS[i]);
            }
        });
    };

    const renderAttendanceRows = (rows) => {
        if (!attendanceTableEl) return;
        const normalizedRows = normalizeAttendanceRows(mergeOptimisticRows(rows));
        const dt = getAttendanceTable();

        if (dt) {
            dt.clear();
            normalizedRows.forEach((att) => {
                dt.row.add(buildAttendanceRowData(att));
            });
            dt.order([6, 'desc']).draw(false);
            Array.from(dt.rows({ order: 'applied' }).nodes()).forEach((node, index) => {
                applyClasses(node, normalizedRows[index]);
            });
            return;
        }

        if (!attendanceTableJq || !attendanceTableJq.length) return;
        const tbody = attendanceTableJq.find('tbody');
        if (!tbody.length) return;

        if (!normalizedRows.length) {
            if (tbody.find('tr').not('.attendance-empty-row').length) {
                return;
            }
            tbody.html('<tr class="text-center text-muted attendance-empty-row"><td class="py-5" colspan="7">No attendance logs found today.</td></tr>');
            return;
        }

        const markup = normalizedRows.map((att) => {
            const dateKey = att?.date ? String(att.date).split('T')[0].split(' ')[0] : todayDate;
            return `
                <tr class="text-center" data-employee-id="${att.employee_id ?? ''}" data-date="${dateKey}">
                    ${buildAttendanceRowCells(att)}
                </tr>
            `;
        }).join('');

        tbody.html(markup);
    };

    const buildAttendanceRowMarkup = (att) => {
        return buildAttendanceRowCells(att);
    };

    const updateAttendanceRow = (att, employee) => {
        if (!att || !attendanceTableEl) return;
        const dateKey = att?.date ? String(att.date).split('T')[0].split(' ')[0] : todayDate;
        if (todayDate && dateKey !== todayDate) return;

        const nextRow = {
            ...att,
            employee: {
                ...(att.employee || {}),
                ...(employee || {}),
                name: employee?.name || buildEmployeeName(att),
            },
        };
        const optimisticKey = rowKey(nextRow);
        if (optimisticKey) {
            optimisticRows.set(optimisticKey, {
                row: nextRow,
                timestamp: Date.now(),
            });
        }
        if (!attendanceTableJq || !attendanceTableJq.length) return;
        const tbody = attendanceTableJq.find('tbody');
        if (!tbody.length) return;

        tbody.find('.attendance-empty-row').remove();

        const employeeId = String(nextRow.employee_id || '');
        const existingRow = employeeId
            ? tbody.find(`tr[data-employee-id="${employeeId}"][data-date="${dateKey}"]`).first()
            : null;

        if (existingRow && existingRow.length) {
            existingRow.html(buildAttendanceRowMarkup(nextRow));
            applyClasses(existingRow.get(0), nextRow);
            tbody.prepend(existingRow);
            return;
        }

        if (!jq) return;
        const row = jq(`<tr class="text-center" data-employee-id="${employeeId}" data-date="${dateKey}"></tr>`);
        row.html(buildAttendanceRowMarkup(nextRow));
        applyClasses(row.get(0), nextRow);
        tbody.prepend(row);
    };

    const pushToast = (type, message, options = {}) => {
        if (typeof window.showToast === 'function') {
            window.showToast(type, message, options);
        }
    };

    const clearLatestNfc = () => fetch(`${nfcLatestUrl}?clear=1`).catch(() => { });

    const schedulePoll = (delay) => { if (pollTimer) clearTimeout(pollTimer); pollTimer = setTimeout(pollNfc, delay); };

    let syncTimer = null;
    let syncInFlight = false;

    const refreshTableFromPage = async () => {
        if (!jq || !attendanceTableJq || !attendanceTableJq.length || syncInFlight) return;
        syncInFlight = true;

        try {
            const separator = window.location.href.includes('?') ? '&' : '?';
            const response = await fetch(`${window.location.href}${separator}_=${Date.now()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            if (!response.ok) return;

            const html = await response.text();
            const nextDoc = jq.parseHTML(html);
            const nextTableBody = jq(nextDoc).find('#attendanceTable tbody').first();
            if (!nextTableBody.length) return;

            const tbody = attendanceTableJq.find('tbody');
            if (!tbody.length) return;

            tbody.replaceWith(nextTableBody);
        } catch (error) {
            console.error('Daily attendance table refresh failed.', error);
        } finally {
            syncInFlight = false;
        }
    };

    const syncRows = async () => {
        if (!feedUrl || syncInFlight) return;
        syncInFlight = true;

        try {
            const separator = feedUrl.includes('?') ? '&' : '?';
            const response = await fetch(`${feedUrl}${separator}_=${Date.now()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            if (!response.ok) return;

            const rows = await response.json();
            normalizeAttendanceRows(rows).forEach((row) => {
                const key = rowKey(row);
                if (key) {
                    optimisticRows.delete(key);
                }
            });
            renderAttendanceRows(rows);
        } catch (error) {
            console.error('Daily attendance feed refresh failed.', error);
        } finally {
            syncInFlight = false;
        }
    };

    const queueSync = (delay) => {
        if (syncTimer) clearTimeout(syncTimer);
        syncTimer = setTimeout(async () => {
            await refreshTableFromPage();
            queueSync(document.hidden ? 5000 : 1500);
        }, delay);
    };

    const recordAttendance = async (nfcUid) => {
        try {
            const res = await fetch(attendanceUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ nfc_uid: nfcUid }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                const employee = data.employee || null;
                const employeeName = employee?.name || 'Employee';
                showProfile(employee, '', '');
                pushToast('success', `${employeeName} attendance recorded successfully.`);
                const rowPayload = data.row || data.data || null;
                if (rowPayload) {
                    updateAttendanceRow(rowPayload, employee);
                    window.dispatchEvent(new CustomEvent('attendance:recorded', {
                        detail: { row: rowPayload, employee },
                    }));
                }
                window.setTimeout(() => refreshTableFromPage(), 150);
            } else {
                const statusType = res.status === 429 ? 'warning' : 'error';
                const errName = data?.employee?.name || 'Scan failed';
                if (res.status === 404) {
                    profileCard.classList.add('is-hidden');
                    pushToast('warning', 'Unregistered ID card');
                    setStatus('', 'Waiting for scan...');
                } else {
                    showProfile(data.employee || { name: errName, department: '' }, data.message || 'Unable to record attendance.', statusType);
                    pushToast(statusType, data.message || 'Unable to record attendance.');
                    profileCard.classList.add('is-hidden');
                }
            }
        } catch {
            setStatus('error', 'Unable to reach server.');
            profileCard.classList.add('is-hidden');
        } finally {
            clearLatestNfc();
        }
    };

    const pollNfc = async () => {
        if (document.hidden) { schedulePoll(maxIdlePollDelayMs); return; }
        if (Date.now() < cooldownUntil) { schedulePoll(500); return; }
        try {
            const res = await fetch(nfcLatestUrl);
            const data = await res.json();
            if (data?.nfc_uid) {
                const uid = data.nfc_uid;
                if (uid !== lastUid) {
                    lastUid = uid;
                    pollDelayMs = minPollDelayMs;
                    cooldownUntil = Date.now() + 1500;
                    if (data.exists) {
                        await recordAttendance(uid);
                    } else {
                        pushToast('warning', 'Unregistered ID card');
                        setStatus('', 'Waiting for scan...');
                        profileCard.classList.add('is-hidden');
                        clearLatestNfc();
                    }
                }
            } else {
                lastUid = null;
                pollDelayMs = Math.min(pollDelayMs + 500, maxIdlePollDelayMs);
            }
        } catch {
            setStatus('', 'Waiting for scan...');
            pollDelayMs = Math.min(Math.floor(pollDelayMs * 1.5), maxErrorPollDelayMs);
        } finally {
            schedulePoll(pollDelayMs);
        }
    };

    profileCard.classList.add('is-hidden');
    setStatus('', 'Waiting for scan...');
    destroyAttendanceDataTable();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pollDelayMs = minPollDelayMs;
            schedulePoll(100);
            queueSync(100);
        }
    });

    if (window.Echo) {
        window.Echo.channel('attendance.daily')
            .listen('.AttendanceRecorded', (event) => {
                if (event?.row) {
                    updateAttendanceRow(event.row, event.row.employee || null);
                    queueSync(100);
                }
            });
    }

    window.hrmsSyncAttendanceRows = refreshTableFromPage;
    window.hrmsForceAttendanceRefresh = () => queueSync(25);

    queueSync(250);
    pollNfc();
}

onReady(function () {
    initAttendanceKiosk();
    initAttendanceNfcKiosk();
});




