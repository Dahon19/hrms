import './bootstrap';

import Alpine from 'alpinejs';
import importedJQuery from 'jquery';
import 'datatables.net-bs4/css/dataTables.bootstrap4.min.css';
import 'datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css';
import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap4-theme/dist/select2-bootstrap4.min.css';

const $ = window.jQuery || window.$ || importedJQuery;

window.Alpine = Alpine;
window.$ = $;
window.jQuery = $;

function pageNeedsFilePond(root = document) {
    return Boolean(root.querySelector('input[type="file"].filepond'));
}

function pageNeedsCoreUiValidation(root = document) {
    const forms = Array.from(root.querySelectorAll('form'));

    return forms.some((form) => {
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
    });
}

async function bootUi() {
    const select2Module = await import('select2/dist/js/select2.full.js');
    const select2Factory = select2Module?.default;

    if (!$.fn.select2 && typeof select2Factory === 'function') {
        select2Factory(window, $);
    }

    document.dispatchEvent(new CustomEvent('hrms:select2-ready'));

    await Promise.all([
        import('datatables.net-bs4'),
        import('datatables.net-responsive-bs4'),
    ]);

    Alpine.start();

    if (pageNeedsFilePond(document)) {
        const { initFilePond } = await import('./filepond-init');
        initFilePond(document);
    }

    if (pageNeedsCoreUiValidation(document)) {
        const { initCoreUiValidation } = await import('./form-validation');
        initCoreUiValidation(document);
    }
}

bootUi().catch((error) => {
    console.error('UI bootstrap failed.', error);
});


