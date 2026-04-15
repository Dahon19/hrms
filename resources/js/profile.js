import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { $, onReady } from './utils';

function initProfileAvatarPreview() {
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatar-preview');
    const avatarShell = avatarPreview?.closest('.profile-avatar-shell');
    const profileSidecard = document.getElementById('profileSidecard');
    const avatarEditButton = document.getElementById('profileAvatarEditButton');
    const avatarPicker = document.getElementById('profileAvatarPicker');
    const avatarEditor = document.getElementById('profileAvatarEditor');
    const avatarZoom = document.getElementById('profileAvatarZoom');
    const avatarZoomValue = document.getElementById('profileAvatarZoomValue');
    const avatarZoomOutButton = document.getElementById('profileAvatarZoomOutButton');
    const avatarZoomInButton = document.getElementById('profileAvatarZoomInButton');
    const avatarResetButton = document.getElementById('profileAvatarResetButton');
    const avatarCancelButton = document.getElementById('profileAvatarCancelButton');
    const avatarApplyButton = document.getElementById('profileAvatarApplyButton');

    if (!avatarInput || !avatarPreview || !avatarShell) {
        return;
    }

    const defaultPreviewSrc = avatarPreview.dataset.originalSrc || avatarPreview.src;
    const state = {
        committedPreviewSrc: defaultPreviewSrc,
        cropper: null,
        pendingObjectUrl: null,
        pendingFilename: 'avatar.jpg',
    };

    function toggleEditor(show) {
        if (!avatarEditor) {
            return;
        }

        avatarEditor.classList.toggle('d-none', !show);
        avatarShell.classList.toggle('profile-avatar-shell--cropping', show);
        avatarPreview.classList.toggle('profile-avatar-image--editing', show);
        profileSidecard?.classList.toggle('profile-sidecard--editing', show);
    }

    function revokePendingObjectUrl() {
        if (!state.pendingObjectUrl) {
            return;
        }

        URL.revokeObjectURL(state.pendingObjectUrl);
        state.pendingObjectUrl = null;
    }

    function setPreviewSource(src) {
        avatarPreview.src = src;
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

    function destroyCropper({ restoreCommittedPreview = false } = {}) {
        avatarPreview.onload = null;

        if (state.cropper) {
            state.cropper.destroy();
            state.cropper = null;
        }

        revokePendingObjectUrl();
        toggleEditor(false);
        syncZoomSlider(100);

        if (restoreCommittedPreview) {
            setPreviewSource(state.committedPreviewSrc);
        }
    }

    function fileFromCanvas(canvas) {
        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (!blob) {
                    reject(new Error('Unable to generate cropped avatar.'));
                    return;
                }

                resolve(
                    new File([blob], state.pendingFilename.replace(/\.[^.]+$/, '') + '.jpg', {
                        type: 'image/jpeg',
                        lastModified: Date.now(),
                    }),
                );
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
            state.committedPreviewSrc = event.target?.result || defaultPreviewSrc;
            setPreviewSource(state.committedPreviewSrc);
        };
        reader.readAsDataURL(file);
    }

    function openCropper(file) {
        if (!file || !file.type || !file.type.startsWith('image/')) {
            return;
        }

        destroyCropper();

        state.pendingFilename = file.name || 'avatar.jpg';
        state.pendingObjectUrl = URL.createObjectURL(file);
        setPreviewSource(state.pendingObjectUrl);

        avatarPreview.onload = () => {
            avatarPreview.onload = null;
            state.cropper = new Cropper(avatarPreview, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                background: false,
                responsive: true,
                guides: false,
                center: false,
                highlight: false,
                movable: true,
                scalable: true,
                zoomable: true,
                rotatable: false,
                cropBoxMovable: false,
                cropBoxResizable: false,
                ready() {
                    toggleEditor(true);
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
            state.committedPreviewSrc = canvas.toDataURL('image/jpeg', 0.92);
            destroyCropper();
            setPreviewSource(state.committedPreviewSrc);
        } catch (error) {
            console.error('Unable to apply avatar crop.', error);
        }
    }

    avatarInput.addEventListener('FilePond:addfile', function (event) {
        const file = event?.detail?.file?.file || null;
        setPreviewFromFile(file);
    });

    avatarInput.addEventListener('FilePond:removefile', function () {
        state.committedPreviewSrc = defaultPreviewSrc;
        destroyCropper();
        setPreviewSource(defaultPreviewSrc);
    });

    if (avatarPicker) {
        avatarPicker.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (file) {
                openCropper(file);
            }

            this.value = '';
        });
    }

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

    if (avatarZoomOutButton) {
        avatarZoomOutButton.addEventListener('click', function () {
            nudgeZoom(-15);
        });
    }

    if (avatarZoomInButton) {
        avatarZoomInButton.addEventListener('click', function () {
            nudgeZoom(15);
        });
    }

    if (avatarResetButton) {
        avatarResetButton.addEventListener('click', function () {
            if (!state.cropper) {
                return;
            }

            state.cropper.reset();
            syncZoomSlider(100);
        });
    }

    if (avatarCancelButton) {
        avatarCancelButton.addEventListener('click', function () {
            destroyCropper({ restoreCommittedPreview: true });
        });
    }

    if (avatarApplyButton) {
        avatarApplyButton.addEventListener('click', function () {
            applyCrop();
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
            destroyCropper({ restoreCommittedPreview: true });
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
