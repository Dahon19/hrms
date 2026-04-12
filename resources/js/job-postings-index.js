import { $, bindToolbarInteractions, whenDataTableReady } from './utils';

(function () {
    const page = document.getElementById('jobPostingsIndexPage');
    if (!page) return;

    const positionsEndpointTemplate = page.dataset.positionsEndpointTemplate || '';
    const editEndpointTemplate = page.dataset.editEndpointTemplate || '';
    const hasCreateError = String(page.dataset.createError) === '1';
    const hasEditError = String(page.dataset.editError) === '1';
    const oldCreatePositionId = page.dataset.oldCreatePositionId || '';
    const oldEditPositionId = page.dataset.oldEditPositionId || '';

    const createModal = document.getElementById('jobPostingCreateModal');
    const editModal = document.getElementById('jobPostingEditModal');
    const table = document.getElementById('jobPostingsTable');
    const createForm = document.getElementById('jobPostingCreateForm');
    const editForm = document.getElementById('jobPostingEditForm');

    const createDept = document.getElementById('job_create_dept_id');
    const createPos = document.getElementById('job_create_position_id');
    const editDept = document.getElementById('job_edit_dept_id');
    const editPos = document.getElementById('job_edit_position_id');

    const editDescription = document.getElementById('job_edit_description');
    const editRequirements = document.getElementById('job_edit_requirements');
    const editEmploymentType = document.getElementById('job_edit_employment_type');
    const editStatus = document.getElementById('job_edit_status');
    const editClosingDate = document.getElementById('job_edit_closing_date');
    const editRequiredHeadcount = document.getElementById('job_edit_required_headcount');
    const editRemainingSlotsBadge = document.getElementById('job_edit_remaining_slots_badge');
    const editUpdateUrlInput = document.getElementById('job_edit_update_url');
    const editPostingIdInput = document.getElementById('job_edit_posting_id');

    let currentEditId = null;

    const pendingRequests = new WeakMap();

    const bindJobPostingsToolbar = () => {
        if (!(table instanceof HTMLTableElement)) return;

        const compactToolbarMedia = window.matchMedia ? window.matchMedia('(max-width: 991.98px)') : null;

        whenDataTableReady(table, (dataTable) => {
            if (table.dataset.jobPostingsToolbarInitialized === '1') {
                return;
            }

            $(table).closest('.dataTables_wrapper').find('.dataTables_filter').addClass('d-none');

            const normalize = (value) => String(value || '')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase();

            const getToolbarState = () => ({
                search: normalize($('#toolbar-search-posting_search').val()),
                staffing: normalize($('#toolbar-filter-staffing').val()),
            });

            if (table.dataset.jobPostingsToolbarFilterBound !== '1') {
                $.fn.dataTable.ext.search.push(function (settings, data) {
                    if (settings.nTable !== table) {
                        return true;
                    }

                    const filterState = getToolbarState();
                    if (!filterState.staffing) {
                        return true;
                    }

                    const slotsCell = normalize(data?.[3]);

                    if (filterState.staffing === 'fully_staffed') {
                        return slotsCell.includes('fully staffed');
                    }

                    if (filterState.staffing === 'partially_filled') {
                        return slotsCell.includes('hiring in progress');
                    }

                    if (filterState.staffing === 'unfilled') {
                        return slotsCell.includes('open');
                    }

                    return true;
                });

                table.dataset.jobPostingsToolbarFilterBound = '1';
            }

            const applyJobPostingsToolbar = () => {
                const filterState = getToolbarState();
                dataTable.search(filterState.search || '').draw();
            };

            const shouldAutoApply = () => !compactToolbarMedia || !compactToolbarMedia.matches;

            bindToolbarInteractions({
                namespace: 'jobPostingsToolbar',
                applySelector: '#jobPostingsToolbarForm .ui-toolbar__submit',
                searchSelector: '#toolbar-search-posting_search',
                changeSelectors: ['#toolbar-filter-staffing'],
                onApply: () => {
                    applyJobPostingsToolbar();
                },
                shouldAutoApply,
            });

            table.dataset.jobPostingsToolbarInitialized = '1';
            applyJobPostingsToolbar();
        });
    };

    const onModalShow = (modalEl, handler) => {
        if (!modalEl || typeof handler !== 'function') return;
        if (window.jQuery) {
            window.jQuery(modalEl).on('show.bs.modal show.coreui.modal', handler);
        } else {
            modalEl.addEventListener('show.bs.modal', handler);
            modalEl.addEventListener('show.coreui.modal', handler);
        }
    };

    const showModal = (modalEl) => {
        if (!modalEl) return false;
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

    const showModalWhenReady = (modalEl, {
        attempts = 12,
        delayMs = 100,
    } = {}) => new Promise((resolve) => {
        if (!modalEl) {
            resolve(false);
            return;
        }

        let remainingAttempts = attempts;

        const tryShow = () => {
            if (showModal(modalEl)) {
                resolve(true);
                return;
            }

            remainingAttempts -= 1;
            if (remainingAttempts <= 0) {
                resolve(false);
                return;
            }

            window.setTimeout(tryShow, delayMs);
        };

        tryShow();
    });

    const setSelectDisabled = (selectEl, disabled) => {
        if (!selectEl) return;
        const isDisabled = !!disabled;
        selectEl.disabled = isDisabled;
        if ($ && selectEl.classList.contains('select2-hidden-accessible')) {
            $(selectEl).prop('disabled', isDisabled).trigger('change.select2');
        }
    };

    const setSelectOptions = (selectEl, optionsHtml, disabled) => {
        if (!selectEl) return;
        selectEl.innerHTML = optionsHtml;
        setSelectDisabled(selectEl, disabled);
        if ($ && selectEl.classList.contains('select2-hidden-accessible')) {
            $(selectEl).trigger('change');
        }
    };

    const setEditFormAction = (updateUrl) => {
        const action = String(updateUrl || '').trim();
        if (!action || !editForm) return;
        editForm.setAttribute('action', action);
        if (editUpdateUrlInput) {
            editUpdateUrlInput.value = action;
        }
    };

    const syncFormValidationState = (formEl) => {
        if (!(formEl instanceof HTMLFormElement)) return;
        formEl.dispatchEvent(new Event('input', { bubbles: true }));
        formEl.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const resetCreateFormToDefaults = () => {
        if (!(createForm instanceof HTMLFormElement)) return;

        createForm.reset();

        if ($) {
            createForm.querySelectorAll('select.select2-hidden-accessible').forEach((selectEl) => {
                $(selectEl).trigger('change');
            });
        }

        if (createPos) {
            setSelectOptions(createPos, '<option value="">-- Select Department First --</option>', true);
        }

        syncFormValidationState(createForm);
    };

    const setSelectValueLoose = (selectEl, rawValue, fallbackValue = '') => {
        if (!selectEl) return;

        const desired = String(rawValue ?? '').trim().toLowerCase();
        const fallback = String(fallbackValue ?? '').trim();
        const options = Array.from(selectEl.options || []);
        const matchedOption = options.find((option) => String(option.value ?? '').trim().toLowerCase() === desired);

        if (matchedOption) {
            selectEl.value = matchedOption.value;
            if ($ && selectEl.classList.contains('select2-hidden-accessible')) {
                $(selectEl).trigger('change');
            }
            return;
        }

        if (fallback !== '') {
            const injected = document.createElement('option');
            injected.value = fallback;
            injected.textContent = fallback;
            injected.selected = true;
            selectEl.appendChild(injected);
            selectEl.value = fallback;
            if ($ && selectEl.classList.contains('select2-hidden-accessible')) {
                $(selectEl).trigger('change');
            }
            return;
        }

        if (options.length) {
            selectEl.selectedIndex = 0;
            if ($ && selectEl.classList.contains('select2-hidden-accessible')) {
                $(selectEl).trigger('change');
            }
        }
    };

    const buildPositionsUrl = (departmentId, includePositionId) => {
        if (!positionsEndpointTemplate || !departmentId) return '';
        let url = positionsEndpointTemplate.replace('__DEPT__', encodeURIComponent(String(departmentId)));
        if (includePositionId) {
            const sep = url.includes('?') ? '&' : '?';
            url += `${sep}include_position_id=${encodeURIComponent(String(includePositionId))}`;
        }
        return url;
    };

    const fetchJson = async (url, signal) => {
        const response = await fetch(url, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            signal,
        });
        if (!response.ok) {
            throw new Error(`Request failed (${response.status})`);
        }
        return response.json();
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const loadPositions = async (departmentId, positionSelectEl, selectedPositionId = '', selectedPositionLabel = '') => {
        if (!positionSelectEl) return;

        if (!departmentId) {
            setSelectOptions(positionSelectEl, '<option value="">-- Select Department First --</option>', true);
            return;
        }

        const url = buildPositionsUrl(departmentId, selectedPositionId);
        if (!url) {
            setSelectOptions(positionSelectEl, '<option value="">Error loading positions</option>', true);
            return;
        }

        const previousController = pendingRequests.get(positionSelectEl);
        if (previousController) {
            previousController.abort();
        }
        const controller = new AbortController();
        pendingRequests.set(positionSelectEl, controller);

        setSelectOptions(positionSelectEl, '<option value="">Loading...</option>', true);

        try {
            const payload = await fetchJson(url, controller.signal);
            if (pendingRequests.get(positionSelectEl) !== controller) {
                return;
            }

            const positions = Array.isArray(payload?.positions) ? payload.positions : [];
            if (!positions.length) {
                if (selectedPositionId && selectedPositionLabel) {
                    setSelectOptions(
                        positionSelectEl,
                        `<option value="${escapeHtml(selectedPositionId)}" selected>${escapeHtml(selectedPositionLabel)}</option>`,
                        false
                    );
                    return;
                }

                setSelectOptions(positionSelectEl, '<option value="">No available positions</option>', true);
                return;
            }

            const placeholder = '<option value="">-- Select Vacant Position --</option>';
            const hasSelectedOption = selectedPositionId
                ? positions.some((position) => String(position.id ?? '') === String(selectedPositionId))
                : false;
            const options = positions
                .map((position) => {
                    const id = String(position.id ?? '');
                    const name = String(position.name ?? '');
                    const selected = selectedPositionId && String(selectedPositionId) === id ? ' selected' : '';
                    return `<option value="${escapeHtml(id)}"${selected}>${escapeHtml(name)}</option>`;
                })
                .join('');

            const preservedSelectedOption = (!hasSelectedOption && selectedPositionId && selectedPositionLabel)
                ? `<option value="${escapeHtml(selectedPositionId)}" selected>${escapeHtml(selectedPositionLabel)}</option>`
                : '';

            setSelectOptions(positionSelectEl, `${placeholder}${preservedSelectedOption}${options}`, false);
        } catch (error) {
            if (error?.name === 'AbortError') return;
            console.error('Failed to load positions:', error);
            if (selectedPositionId && selectedPositionLabel) {
                setSelectOptions(
                    positionSelectEl,
                    `<option value="${escapeHtml(selectedPositionId)}" selected>${escapeHtml(selectedPositionLabel)}</option>`,
                    false
                );
                return;
            }

            setSelectOptions(positionSelectEl, '<option value="">Unable to load positions</option>', true);
        }
    };

    const bindDepartmentPositionLoader = (departmentSelectEl, positionSelectEl) => {
        if (!departmentSelectEl || !positionSelectEl) return;

        const handleLoad = async () => {
            const deptId = departmentSelectEl.value || '';
            await loadPositions(deptId, positionSelectEl);
        };

        departmentSelectEl.addEventListener('change', handleLoad);

        if ($) {
            $(departmentSelectEl).on('change select2:select select2:clear', handleLoad);
        }
    };

    const getEditButtonFromEvent = (event) => {
        const relatedTarget = event?.relatedTarget
            || event?.targetTrigger
            || event?.originalEvent?.relatedTarget
            || null;
        if (!relatedTarget) return null;
        if (relatedTarget.classList?.contains('edit-posting')) return relatedTarget;
        return relatedTarget.closest ? relatedTarget.closest('.edit-posting') : null;
    };

    const fillEditForm = async (payload) => {
        if (!payload || !editForm) return;

        const updateUrl = payload.update_url || '#';
        setEditFormAction(updateUrl);
        if (editPostingIdInput) {
            editPostingIdInput.value = payload.id || currentEditId || '';
        }

        if (editDescription) editDescription.value = payload.description || '';
        if (editRequirements) editRequirements.value = payload.requirements || '';
        if (editEmploymentType) {
            setSelectValueLoose(editEmploymentType, payload.employment_type, payload.employment_type || 'Full-time');
        }
        if (editStatus) {
            setSelectValueLoose(editStatus, payload.status, payload.status || 'draft');
        }
        if (editClosingDate) editClosingDate.value = payload.closing_date || '';
        if (editRequiredHeadcount) editRequiredHeadcount.value = payload.required_headcount || 1;
        if (editRemainingSlotsBadge) {
            const remainingSlots = Number(payload.remaining_slots ?? 0);
            editRemainingSlotsBadge.textContent = String(remainingSlots);
            editRemainingSlotsBadge.className = remainingSlots === 0
                ? 'font-weight-bold text-success'
                : (remainingSlots === 1 ? 'font-weight-bold text-warning' : 'font-weight-bold text-muted');
        }

        const deptId = payload.department_id ? String(payload.department_id) : '';
        const posId = payload.position_id ? String(payload.position_id) : '';

        if (editDept) {
            setSelectValueLoose(editDept, deptId);
        }
        await loadPositions(deptId, editPos, posId, payload.position_label || '');
        syncFormValidationState(editForm);
    };

    const fetchEditPayload = async (postingId) => {
        if (!postingId || !editEndpointTemplate) return null;
        const endpoint = editEndpointTemplate.replace('__ID__', encodeURIComponent(String(postingId)));
        try {
            return await fetchJson(endpoint);
        } catch (error) {
            console.error('Failed to fetch edit payload:', error);
            return null;
        }
    };

    const fallbackPayloadFromButton = (buttonEl) => {
        if (!buttonEl) return null;
        const rawPayload = buttonEl.getAttribute('data-edit') || '{}';
        try {
            const parsedPayload = JSON.parse(rawPayload);
            if (parsedPayload && typeof parsedPayload === 'object') {
                return {
                    update_url: parsedPayload.update_url || '#',
                    department_id: parsedPayload.department_id || '',
                    position_id: parsedPayload.position_id || '',
                    position_label: parsedPayload.title || '',
                    description: parsedPayload.description || '',
                    requirements: parsedPayload.requirements || '',
                    employment_type: parsedPayload.employment_type || 'Full-time',
                    status: parsedPayload.status || 'draft',
                    closing_date: parsedPayload.closing_date || '',
                    required_headcount: parsedPayload.required_headcount || '1',
                    fulfilled_count: parsedPayload.fulfilled_count || '0',
                    remaining_slots: parsedPayload.remaining_slots || '0',
                };
            }
        } catch (error) {
            console.warn('Failed to parse job posting edit payload:', error);
        }

        return {
            update_url: buttonEl.getAttribute('data-update-url') || '#',
            department_id: buttonEl.getAttribute('data-department-id') || '',
            position_id: buttonEl.getAttribute('data-position-id') || '',
            position_label: buttonEl.getAttribute('data-title') || '',
            description: buttonEl.getAttribute('data-description') || '',
            requirements: buttonEl.getAttribute('data-requirements') || '',
            employment_type: buttonEl.getAttribute('data-employment-type') || 'Full-time',
            status: buttonEl.getAttribute('data-status') || 'draft',
            closing_date: buttonEl.getAttribute('data-closing-date') || '',
            required_headcount: buttonEl.getAttribute('data-required-headcount') || '1',
            fulfilled_count: buttonEl.getAttribute('data-fulfilled-count') || '0',
            remaining_slots: buttonEl.getAttribute('data-remaining-slots') || '0',
        };
    };

    document.addEventListener('click', async (event) => {
        const trigger = event.target?.closest?.('.edit-posting');
        if (!trigger) return;
        currentEditId = trigger.getAttribute('data-id') || null;
        setEditFormAction(trigger.getAttribute('data-update-url') || '');
        const payloadFromApi = await fetchEditPayload(currentEditId);
        const payload = payloadFromApi || fallbackPayloadFromButton(trigger);
        if (payload) {
            await fillEditForm(payload);
        }
    });

    bindDepartmentPositionLoader(createDept, createPos);
    bindDepartmentPositionLoader(editDept, editPos);

    if (createModal) {
        onModalShow(createModal, async () => {
            if (!hasCreateError) {
                resetCreateFormToDefaults();
            }

            if (createDept?.value) {
                await loadPositions(createDept.value, createPos, oldCreatePositionId);
            } else if (createPos) {
                setSelectOptions(createPos, '<option value="">-- Select Department First --</option>', true);
            }
        });
    }

    if (editModal) {
        onModalShow(editModal, async (event) => {
            const button = getEditButtonFromEvent(event);
            if (!button && !hasEditError && !currentEditId) return;

            const postingId = button?.getAttribute('data-id') || currentEditId;
            const payloadFromApi = await fetchEditPayload(postingId);
            const payload = payloadFromApi || fallbackPayloadFromButton(button);
            if (payload) {
                await fillEditForm(payload);
            }
        });
    }

    const initValidationRecovery = async () => {
        if (hasCreateError && createModal) {
            await showModalWhenReady(createModal);
            if (createDept?.value) {
                await loadPositions(createDept.value, createPos, oldCreatePositionId);
            }
        }

        if (hasEditError && editModal) {
            setEditFormAction(editUpdateUrlInput?.value || '');
            await showModalWhenReady(editModal);
            if (editDept?.value) {
                await loadPositions(editDept.value, editPos, oldEditPositionId);
            }
        }
    };

    bindJobPostingsToolbar();
    initValidationRecovery();
})();


