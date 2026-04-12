function findFilePondInstance(field) {
    return window.FilePond?.find?.(field) || null;
}

function getAssociatedSubmitButtons(form) {
    if (!(form instanceof HTMLFormElement)) {
        return [];
    }

    const submitButtons = new Set(
        Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'))
    );

    const formId = form.getAttribute('id');
    if (!formId) {
        return Array.from(submitButtons);
    }

    const escapedFormId = typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
        ? CSS.escape(formId)
        : formId.replace(/["\\]/g, '\\$&');

    document.querySelectorAll(
        `button[type="submit"][form="${escapedFormId}"], input[type="submit"][form="${escapedFormId}"]`
    ).forEach((button) => {
        submitButtons.add(button);
    });

    return Array.from(submitButtons);
}

function isManagedForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        return false;
    }

    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    if (method === 'GET') {
        return false;
    }

    if (form.matches('[data-skip-coreui-validation]')) {
        return false;
    }

    if (
        form.closest('.ui-table-toolbar') ||
        form.classList.contains('ui-table-toolbar') ||
        form.className.toLowerCase().includes('toolbar')
    ) {
        return false;
    }

    return true;
}

function fieldLabel(field) {
    const id = field.getAttribute('id');
    if (id) {
        const label = document.querySelector(`label[for="${id}"]`);
        if (label) {
            return label.textContent.replace(/\*/g, '').trim();
        }
    }

    return (
        field.getAttribute('aria-label') ||
        field.getAttribute('name') ||
        'This field'
    );
}

function defaultMessage(field) {
    if (field.dataset.validationMessage) {
        return field.dataset.validationMessage;
    }

    if (field.validity.valueMissing) {
        return `${fieldLabel(field)} is required.`;
    }

    if (field.validity.typeMismatch) {
        if (field.type === 'email') {
            return 'Please provide a valid email address.';
        }

        if (field.type === 'url') {
            return 'Please provide a valid URL.';
        }
    }

    if (field.validity.patternMismatch) {
        return `Please provide a valid ${fieldLabel(field).toLowerCase()}.`;
    }

    if (field.validity.tooShort) {
        return `${fieldLabel(field)} is too short.`;
    }

    if (field.validity.tooLong) {
        return `${fieldLabel(field)} is too long.`;
    }

    if (field.validity.rangeUnderflow || field.validity.rangeOverflow) {
        return `${fieldLabel(field)} is out of range.`;
    }

    if (field.validity.badInput) {
        return `Please provide a valid ${fieldLabel(field).toLowerCase()}.`;
    }

    return field.validationMessage || `Please provide a valid ${fieldLabel(field).toLowerCase()}.`;
}

function feedbackInsertionTarget(field) {
    if (field.classList.contains('filepond')) {
        return field.nextElementSibling?.classList.contains('filepond--root')
            ? field.nextElementSibling
            : field;
    }

    if (
        field instanceof HTMLSelectElement &&
        field.classList.contains('select2-hidden-accessible')
    ) {
        return field.nextElementSibling?.classList.contains('select2-container')
            ? field.nextElementSibling
            : field;
    }

    const inputGroup = field.closest('.input-group');
    if (inputGroup) {
        return inputGroup;
    }

    return field;
}

function ensureFeedbackElement(field) {
    const target = feedbackInsertionTarget(field);
    const scope = target.parentElement || field.parentElement;

    if (!scope) {
        return null;
    }

    let feedback = Array.from(scope.children).find((node) => {
        return (
            node !== target &&
            node.classList?.contains('invalid-feedback') &&
            node.dataset.generatedFor === (field.name || field.id || '')
        );
    });

    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.dataset.generatedFor = field.name || field.id || '';

        if (target.nextSibling) {
            scope.insertBefore(feedback, target.nextSibling);
        } else {
            scope.appendChild(feedback);
        }
    }

    return feedback;
}

function applyFieldState(field) {
    if (!(field instanceof HTMLElement)) {
        return;
    }

    const invalid = !field.checkValidity();
    const feedback = ensureFeedbackElement(field);

    field.classList.toggle('is-invalid', invalid);
    field.classList.toggle('is-valid', !invalid && field.value !== '');

    if (feedback) {
        feedback.textContent = invalid ? defaultMessage(field) : '';
        feedback.classList.toggle('d-block', invalid);
    }

    if (field.classList.contains('filepond')) {
        const root = field.nextElementSibling?.classList.contains('filepond--root')
            ? field.nextElementSibling
            : null;

        if (root) {
            root.classList.toggle('is-invalid', invalid);
            root.classList.toggle('is-valid', !invalid && Boolean(findFilePondInstance(field)?.getFiles?.()?.length));
        }
    }
}

function syncFilePondField(field) {
    if (!(field instanceof HTMLInputElement) || field.type !== 'file' || !field.classList.contains('filepond')) {
        return;
    }

    const pond = findFilePondInstance(field);
    const files = pond?.getFiles?.() || [];
    const hasFiles = files.length > 0;
    const hasError = files.some((file) => file.status === 8 || file.status === 6);

    field.setCustomValidity('');

    if (field.required && !hasFiles) {
        field.setCustomValidity('Please upload a file.');
    } else if (hasError) {
        field.setCustomValidity('Please fix the selected file before submitting.');
    }

    applyFieldState(field);
}

function syncFormFields(form) {
    const fields = Array.from(form.elements).filter((element) => {
        return (
            element instanceof HTMLInputElement ||
            element instanceof HTMLSelectElement ||
            element instanceof HTMLTextAreaElement
        );
    });

    fields.forEach((field) => {
        if (field.disabled) {
            return;
        }

        if (field instanceof HTMLInputElement && field.type === 'file' && field.classList.contains('filepond')) {
            syncFilePondField(field);
            return;
        }

        field.setCustomValidity('');
        applyFieldState(field);
    });
}

function bindFieldListeners(form) {
    const fields = Array.from(form.elements).filter((element) => {
        return (
            element instanceof HTMLInputElement ||
            element instanceof HTMLSelectElement ||
            element instanceof HTMLTextAreaElement
        );
    });

    fields.forEach((field) => {
        const handler = () => {
            if (field instanceof HTMLInputElement && field.type === 'file' && field.classList.contains('filepond')) {
                syncFilePondField(field);
                updateSubmitState(form);
                return;
            }

            field.setCustomValidity('');
            applyFieldState(field);
            updateSubmitState(form);
        };

        field.addEventListener('input', handler);
        field.addEventListener('change', handler);

        if (field instanceof HTMLInputElement && field.type === 'file' && field.classList.contains('filepond')) {
            ['FilePond:addfile', 'FilePond:removefile', 'FilePond:updatefiles'].forEach((eventName) => {
                field.addEventListener(eventName, () => {
                    syncFilePondField(field);
                    updateSubmitState(form);
                });
            });
        }
    });
}

function modalFirstFocusableField(modal) {
    if (!(modal instanceof HTMLElement)) {
        return null;
    }

    return modal.querySelector(
        '.modal-body input:not([type="hidden"]):not([disabled]), .modal-body select:not([disabled]), .modal-body textarea:not([disabled])'
    );
}

function updateSubmitState(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const submitButtons = getAssociatedSubmitButtons(form);

    if (!submitButtons.length) {
        return;
    }

    if (form.dataset.keepSubmitEnabled === '1') {
        submitButtons.forEach((button) => {
            button.disabled = form.dataset.submitting === '1';
        });
        return;
    }

    const allRequiredValid = Array.from(form.elements).every((element) => {
        if (
            !(element instanceof HTMLInputElement) &&
            !(element instanceof HTMLSelectElement) &&
            !(element instanceof HTMLTextAreaElement)
        ) {
            return true;
        }

        if (element.disabled) {
            return true;
        }

        if (element.willValidate === false) {
            return true;
        }

        return element.checkValidity();
    });

    submitButtons.forEach((button) => {
        if (form.dataset.submitting === '1') {
            button.disabled = true;
            return;
        }

        button.disabled = !allRequiredValid;
    });
}

function setSubmittingState(form, submitting) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    form.dataset.submitting = submitting ? '1' : '0';

    const submitButtons = getAssociatedSubmitButtons(form);

    submitButtons.forEach((button) => {
        if (button instanceof HTMLButtonElement) {
            if (submitting) {
                if (!button.dataset.originalHtml) {
                    button.dataset.originalHtml = button.innerHTML;
                }

                button.innerHTML = '<span class="spinner-border spinner-border-sm hrms-btn__spinner me-2" role="status" aria-hidden="true"></span><span class="visually-hidden">Loading</span>';
            } else if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }
        }

        button.disabled = submitting;
    });

    if (!submitting) {
        updateSubmitState(form);
    }
}

let modalUxBound = false;

function bindModalUx() {
    if (modalUxBound || typeof window.$ === 'undefined') {
        return;
    }

    window.$(document).on('shown.bs.modal', '.modal', function () {
        const firstField = modalFirstFocusableField(this);
        if (firstField instanceof HTMLElement) {
            window.setTimeout(() => firstField.focus({ preventScroll: true }), 40);
        }
    });

    modalUxBound = true;
}

export function initCoreUiValidation(root = document) {
    bindModalUx();

    const forms = [
        ...(root instanceof HTMLFormElement ? [root] : []),
        ...Array.from(root.querySelectorAll('form')),
    ].filter(isManagedForm);

    forms.forEach((form) => {
        if (form.dataset.coreuiValidationReady === '1') {
            return;
        }

        form.setAttribute('novalidate', 'novalidate');
        bindFieldListeners(form);
        updateSubmitState(form);

        form.addEventListener('submit', (event) => {
            syncFormFields(form);
            form.classList.add('was-validated');

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                setSubmittingState(form, false);

                const firstInvalid = form.querySelector('.is-invalid, :invalid');
                if (firstInvalid instanceof HTMLElement) {
                    firstInvalid.focus({ preventScroll: false });
                }

                updateSubmitState(form);
                return;
            }

            setSubmittingState(form, true);
        });

        form.addEventListener('input', () => updateSubmitState(form));
        form.addEventListener('change', () => updateSubmitState(form));

        form.dataset.coreuiValidationReady = '1';
    });
}
