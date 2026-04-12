import { $, onReady } from './utils';

function initProfileAvatarPreview() {
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatar-preview');
    const avatarEditButton = document.getElementById('profileAvatarEditButton');
    const avatarPicker = document.getElementById('profileAvatarPicker');
    if (!avatarInput || !avatarPreview) return;

    const defaultPreviewSrc = avatarPreview.dataset.originalSrc || avatarPreview.src;

    function setPreviewFromFile(file) {
        if (!file || !file.type || !file.type.startsWith('image/')) {
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            avatarPreview.src = event.target.result;
        };
        reader.readAsDataURL(file);
    }

    avatarInput.addEventListener('FilePond:addfile', function (event) {
        const file = event?.detail?.file?.file || null;
        setPreviewFromFile(file);
    });

    avatarInput.addEventListener('FilePond:removefile', function () {
        avatarPreview.src = defaultPreviewSrc;
    });

    if (avatarPicker) {
        avatarPicker.addEventListener('change', async function () {
            const file = this.files && this.files[0];
            if (!file) {
                return;
            }

            const pond = window.FilePond?.find?.(avatarInput);
            if (pond) {
                pond.removeFiles();
                try {
                    await pond.addFile(file);
                } catch (error) {
                    console.error('Unable to add avatar to FilePond.', error);
                }
            } else {
                setPreviewFromFile(file);
            }

            this.value = '';
        });
    }

    if (avatarEditButton) {
        avatarEditButton.addEventListener('keydown', function (event) {
            if (!avatarPicker) {
                return;
            }

            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            avatarPicker.click();
        });
    }

    if ($) {
        $('#profileEditModal').on('hidden.bs.modal', function () {
            if (!avatarInput.files || !avatarInput.files.length) {
                avatarPreview.src = defaultPreviewSrc;
            }
        });
    }
}

function initProfileAutoModal() {
    if (!$) return;
    const directRouteTrigger = document.getElementById('profileShowPageTrigger');
    if (directRouteTrigger) {
        const modalId = directRouteTrigger.dataset.autoModal;
        if (modalId) {
            $('#' + modalId).modal('show');
        }
    }

    const editModalTrigger = document.getElementById('profileEditModalTrigger');
    if (!editModalTrigger) return;

    if (editModalTrigger.dataset.open === '1') {
        $('#profileEditModal').modal('show');
    }
}

onReady(function () {
    initProfileAvatarPreview();
    initProfileAutoModal();
});
