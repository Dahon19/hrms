import { $, onReady } from './utils';

function initClearanceItemModal() {
    if (!$) return;

    const applyItemData = ($button) => {
        if (!$button || !$button.length) return;

        $('#clearanceItemForm').attr('action', $button.data('item-action') || '#');
        $('#clearance_item_name').val($button.data('item-name') || '');
        $('#clearance_item_status').val($button.data('item-status') || 'pending');
        $('#clearance_item_remarks').val($button.data('item-remarks') || '');
    };

    $(document).on('click', '[data-item-action]', function () {
        applyItemData($(this));
    });

    $('#clearanceItemModal').on('show.bs.modal show.coreui.modal', function (event) {
        const $button = $(event.relatedTarget);
        applyItemData($button);
    });
}

function initLazyReferences() {
    const toggles = document.querySelectorAll('[data-reference-toggle]');
    if (!toggles.length) return;

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const targetSelector = toggle.getAttribute('data-reference-target');
            const src = toggle.getAttribute('data-reference-src');
            if (!targetSelector || !src) return;

            const target = document.querySelector(targetSelector);
            if (!target) return;

            if (!target.dataset.loaded) {
                target.innerHTML = '<iframe class="offboarding-reference-frame" loading="lazy"></iframe>';
                const frame = target.querySelector('iframe');
                if (frame) {
                    frame.src = src;
                }
                target.dataset.loaded = '1';
                toggle.textContent = 'Reload';
            } else {
                const frame = target.querySelector('iframe');
                if (frame) {
                    frame.src = src;
                }
            }

            if ($ && $.fn && $.fn.collapse) {
                $(target).collapse('toggle');
            } else {
                target.classList.toggle('show');
            }
        });
    });
}

onReady(function () {
    initClearanceItemModal();
    initLazyReferences();
});

