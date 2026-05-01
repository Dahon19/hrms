import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { $, onReady } from './utils';

function initProfileAvatarPreview() {
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreviewMain = document.getElementById('avatar-preview-main');
    const avatarPreviewEditor = document.getElementById('avatar-preview-editor');
    const openAvatarEditorButton = document.getElementById('openAvatarEditorButton');
    const avatarEditButton = document.getElementById('profileAvatarEditButton');
    const avatarPicker = document.getElementById('profileAvatarPicker');
    const avatarZoom = document.getElementById('profileAvatarZoom');
    const avatarZoomValue = document.getElementById('profileAvatarZoomValue');
    const avatarZoomOutButton = document.getElementById('profileAvatarZoomOutButton');
    const avatarZoomInButton = document.getElementById('profileAvatarZoomInButton');
    const avatarResetButton = document.getElementById('profileAvatarResetButton');
    const avatarCancelButton = document.getElementById('profileAvatarCancelButton');
    const avatarApplyButton = document.getElementById('profileAvatarApplyButton');

    const profileMainHeader = document.getElementById('profileMainHeader');
    const profileAvatarHeader = document.getElementById('profileAvatarHeader');
    const profileMainFooter = document.getElementById('profileMainFooter');
    const profileAvatarFooter = document.getElementById('profileAvatarFooter');
    const profileMainView = document.getElementById('profileMainView');
    const profileAvatarView = document.getElementById('profileAvatarView');

    if (!avatarInput || !avatarPreviewMain || !avatarPreviewEditor || !$) {
        return;
    }

    const defaultPreviewSrc = avatarPreviewMain.dataset.originalSrc || avatarPreviewMain.src;
    const state = {
        committedPreviewSrc: defaultPreviewSrc,
        cropper: null,
        pendingObjectUrl: null,
        pendingFilename: 'avatar.jpg',
    };

    const modalDialog = document.querySelector('#profileEditModal .modal-dialog');

    function showAvatarView() {
        if (modalDialog) {
            modalDialog.classList.remove('modal-lg');
            modalDialog.classList.add('modal-md');
        }
        profileMainHeader?.classList.add('d-none');
        profileAvatarHeader?.classList.remove('d-none');
        profileMainFooter?.classList.add('d-none');
        profileAvatarFooter?.classList.remove('d-none');
        profileMainView?.classList.add('d-none');
        profileAvatarView?.classList.remove('d-none');
        initCropperWithCurrentPreview();
    }

    function showMainView() {
        if (modalDialog) {
            modalDialog.classList.remove('modal-md');
            modalDialog.classList.add('modal-lg');
        }
        profileMainHeader?.classList.remove('d-none');
        profileAvatarHeader?.classList.add('d-none');
        profileMainFooter?.classList.remove('d-none');
        profileAvatarFooter?.classList.add('d-none');
        profileMainView?.classList.remove('d-none');
        profileAvatarView?.classList.add('d-none');
        destroyCropper({ restoreCommittedPreview: true });
    }

    function revokePendingObjectUrl() {
        if (!state.pendingObjectUrl) {
            return;
        }

        URL.revokeObjectURL(state.pendingObjectUrl);
        state.pendingObjectUrl = null;
    }

    function syncZoomSlider(value) {
        if (!avatarZoom) {
            return;
        }

        const normalizedValue = Math.max(100, Math.min(300, Math.round(value)));
        avatarZoom.value = String(normalizedValue);

        if (avatarZoomValue) {
            avatarZoomValue.textContent = `${normalizedValue}%`;
        }
    }

    function setCommittedPreview(src) {
        state.committedPreviewSrc = src;
        avatarPreviewMain.src = src;
        avatarPreviewEditor.src = src;
    }

    function destroyCropper({ restoreCommittedPreview = false } = {}) {
        avatarPreviewEditor.onload = null;

        if (state.cropper) {
            state.cropper.destroy();
            state.cropper = null;
        }

        revokePendingObjectUrl();
        syncZoomSlider(100);

        if (restoreCommittedPreview) {
            avatarPreviewEditor.src = state.committedPreviewSrc;
        }
    }

    function fileFromCanvas(canvas) {
        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (!blob) {
                    reject(new Error('Unable to generate cropped avatar.'));
                    return;
                }

                resolve(new File([blob], state.pendingFilename.replace(/\.[^.]+$/, '') + '.jpg', {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                }));
            }, 'image/jpeg', 0.92);
        });
    }

    function assignFileToInput(file) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        avatarInput.files = dataTransfer.files;
    }

    async function replacePondFile(file) {
        const pond = window.FilePond?.find?.(avatarInput);

        if (!pond) {
            assignFileToInput(file);
            return;
        }

        pond.removeFiles();
        await pond.addFile(file);
    }

    function setPreviewFromFile(file) {
        if (!file || !file.type || !file.type.startsWith('image/')) {
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            const src = event.target?.result || defaultPreviewSrc;
            setCommittedPreview(src);
        };
        reader.readAsDataURL(file);
    }

    function initCropperWithCurrentPreview() {
        destroyCropper();
        avatarPreviewEditor.src = state.committedPreviewSrc;
        avatarPreviewEditor.onload = () => {
            avatarPreviewEditor.onload = null;
            state.cropper = new Cropper(avatarPreviewEditor, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                background: false,
                responsive: true,
                guides: false,
                center: true,
                highlight: false,
                movable: true,
                scalable: true,
                zoomable: true,
                rotatable: false,
                cropBoxMovable: false,
                cropBoxResizable: false,
                ready() {
                    syncZoomSlider(100);
                },
                zoom(event) {
                    const ratio = event?.detail?.ratio ?? 1;
                    syncZoomSlider(ratio * 100);
                },
            });
        };
    }

    function openCropper(file) {
        if (!file || !file.type || !file.type.startsWith('image/')) {
            return;
        }

        destroyCropper();

        state.pendingFilename = file.name || 'avatar.jpg';
        state.pendingObjectUrl = URL.createObjectURL(file);
        avatarPreviewEditor.src = state.pendingObjectUrl;

        avatarPreviewEditor.onload = () => {
            avatarPreviewEditor.onload = null;
            state.cropper = new Cropper(avatarPreviewEditor, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                background: false,
                responsive: true,
                guides: false,
                center: true,
                highlight: false,
                movable: true,
                scalable: true,
                zoomable: true,
                rotatable: false,
                cropBoxMovable: false,
                cropBoxResizable: false,
                ready() {
                    syncZoomSlider(100);
                },
                zoom(event) {
                    const ratio = event?.detail?.ratio ?? 1;
                    syncZoomSlider(ratio * 100);
                },
            });
        };
    }

    async function applyCrop() {
        if (!state.cropper) {
            return;
        }

        try {
            const canvas = state.cropper.getCroppedCanvas({
                width: 512,
                height: 512,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            const file = await fileFromCanvas(canvas);
            await replacePondFile(file);
            const src = canvas.toDataURL('image/jpeg', 0.92);
            setCommittedPreview(src);
            destroyCropper();
            showMainView();
        } catch (error) {
            console.error('Unable to apply avatar crop.', error);
        }
    }

    avatarInput.addEventListener('FilePond:addfile', function (event) {
        const file = event?.detail?.file?.file || null;
        setPreviewFromFile(file);
    });

    avatarInput.addEventListener('FilePond:removefile', function () {
        setCommittedPreview(defaultPreviewSrc);
        destroyCropper({ restoreCommittedPreview: true });
    });

    if (avatarZoom) {
        avatarZoom.addEventListener('input', function () {
            if (!state.cropper) {
                return;
            }

            state.cropper.zoomTo(Number(this.value) / 100);
        });
    }

    function nudgeZoom(delta) {
        if (!avatarZoom || !state.cropper) {
            return;
        }

        const nextValue = Math.max(100, Math.min(300, Number(avatarZoom.value) + delta));
        syncZoomSlider(nextValue);
        state.cropper.zoomTo(nextValue / 100);
    }

    avatarZoomOutButton?.addEventListener('click', function () {
        nudgeZoom(-15);
    });

    avatarZoomInButton?.addEventListener('click', function () {
        nudgeZoom(15);
    });

    avatarResetButton?.addEventListener('click', function () {
        if (!state.cropper) {
            return;
        }

        state.cropper.reset();
        syncZoomSlider(100);
    });

    avatarCancelButton?.addEventListener('click', function () {
        showMainView();
    });

    avatarApplyButton?.addEventListener('click', function () {
        applyCrop();
    });

    openAvatarEditorButton?.addEventListener('click', function () {
        avatarPicker?.click();
    });

    if (avatarPicker) {
        avatarPicker.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (file) {
                showAvatarView();
                openCropper(file);
            }

            this.value = '';
        });
    }

    avatarEditButton?.addEventListener('keydown', function (event) {
        if (!avatarPicker) {
            return;
        }

        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        avatarPicker.click();
    });

    $('#profileEditModal').on('hidden.bs.modal', function () {
        showMainView();
    });
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
