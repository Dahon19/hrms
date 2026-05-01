@php
    $canEditAccountName = $profileUser->isAdmin() || \App\Services\AccessControl::isHrStaff($profileUser);
    $avatarLetter = strtoupper(substr($profileUser->employee->first_name ?? $profileUser->name ?? 'U', 0, 1));
    $avatarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">' . '<rect width="100%" height="100%" fill="#6c757d"/>' . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="96" fill="#ffffff">' . e($avatarLetter) . '</text></svg>';
    $avatarFallback = 'data:image/svg+xml;base64,' . base64_encode($avatarSvg);
    $avatarUrl = null;
    if (!empty($profileUser->avatar)) {
        $parts = explode('/', $profileUser->avatar);
        $folder = $parts[0] ?? null;
        $subfolder = $parts[1] ?? null;
        $filename = $parts[2] ?? null;
        if ($folder && $subfolder && $filename) {
            $avatarUrl = route('storage.file', [
                'folder' => $folder,
                'subfolder' => $subfolder,
                'filename' => $filename,
            ]);
        }
    }
    $profileName = trim(($profileUser->employee->first_name ?? $profileUser->name ?? 'User') . ' ' . ($profileUser->employee->last_name ?? ''));
    $positionLabel = ucfirst($profileUser->employee?->positions?->first()?->position?->position ?? 'No Position Assigned');
    $departmentLabel = $profileUser->employee?->department?->department ?? 'N/A';
@endphp

<form
    id="profileEditForm"
    method="POST"
    action="{{ route('profile.update') }}"
    enctype="multipart/form-data"
>
    <input type="hidden" name="form_context" value="profile_edit" />
    @csrf
    @method('patch')
    <div id="profileModalHeaderGroup" class="d-none">
        <div id="profileMainHeader"></div>
        <div id="profileAvatarHeader">
            <h5 class="mb-1 text-primary">Update Profile Photo</h5>
            <p class="small text-muted mb-4">Upload a photo, adjust the crop, and apply it to your profile.</p>
        </div>
    </div>

    <div class="profile-ux-container {{ isset($attributes) && $attributes instanceof \Illuminate\View\ComponentAttributeBag ? $attributes->get('class') : '' }}">
        <div id="profileMainView" class="profile-workspace">
            <aside class="profile-sidecard" id="profileSidecard">
                <div class="profile-avatar-panel">
                    <div class="profile-section-heading profile-section-heading--avatar mb-3">Profile Photo</div>
                    <div class="profile-avatar-shell profile-avatar-shell--editor mb-3">
                        <img
                            id="avatar-preview-main"
                            src="{{ $avatarUrl ?: $avatarFallback }}"
                            class="profile-avatar-image"
                            data-original-src="{{ $avatarUrl ?: $avatarFallback }}"
                            alt="User profile picture"
                        />
                    </div>
                    <button
                        type="button"
                        class="btn btn-outline-primary btn-sm rounded-pill w-100 mb-3"
                        id="openAvatarEditorButton"
                    >
                        <i class="cil-camera mr-2"></i>Change Photo
                    </button>
                </div>
                <div class="profile-identity-card text-center text-md-left">
                    <h4 class="profile-identity-name mb-1">{{ $profileName }}</h4>
                    <p class="profile-identity-role mb-1">{{ $positionLabel }}</p>
                    <p class="profile-sidecard__subline mb-0">{{ $departmentLabel }}</p>
                </div>
                @error ('avatar')
                    <small class="text-danger d-block mt-2">{{ $message }}</small>
                @enderror
            </aside>
            <div class="profile-maincard">
                <div class="profile-section">
                    <div class="profile-section-heading mb-3">Account Information</div>
                    <div class="profile-stat-summary mb-4">
                        <div class="profile-stat-box">
                            <span class="profile-stat-box__label">Employee ID</span>
                            <span class="profile-stat-box__value">{{ $profileUser->employee->employee_id ?? 'N/A' }}</span>
                        </div>
                        <div class="profile-stat-box">
                            <span class="profile-stat-box__label">Joined NC</span>
                            <span class="profile-stat-box__value">{{ $profileUser->created_at->format('M Y') }}</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="profile-label" for="profile-name">Full Name</label>
                                <div class="input-group input-group--premium">
                                    <span class="input-group-text" aria-hidden="true"><i class="cil-contact"></i></span>
                                    <input
                                        type="text"
                                        name="name"
                                        id="profile-name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $profileUser->name) }}"
                                        placeholder="Enter your full account name"
                                        @if (!$canEditAccountName)
                                            readonly
                                            aria-readonly="true"
                                        @endif
                                    />
                                </div>
                                @if (!$canEditAccountName)
                                    <small class="form-text text-muted mt-1">
                                        Locked: Identity updates require HR approval.
                                    </small>
                                @endif
                                @error ('name')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="profile-label" for="profile-email">Email Address</label>
                                <div class="input-group input-group--premium">
                                    <span class="input-group-text" aria-hidden="true"><i class="cil-at"></i></span>
                                    <input
                                        type="email"
                                        name="email"
                                        id="profile-email"
                                        autocomplete="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email', $profileUser->email) }}"
                                        placeholder="Enter your work email address"
                                        required
                                    />
                                </div>
                                @error ('email')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                @if ($profileUser instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $profileUser->hasVerifiedEmail())
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="cil-warning mr-1"></i> Email is unverified.
                        <button
                            form="send-verification"
                            class="btn btn-sm btn-outline-secondary"
                        >
                            Resend verification
                        </button>
                    </div>
                @endif
                <div class="profile-section">
                    <div class="profile-section-heading profile-section-heading--security mb-3">Security & Integrity</div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label class="profile-label" for="profile-current-password">Current Password</label>
                                <div class="input-group input-group--premium">
                                    <span class="input-group-text" aria-hidden="true"><i class="cil-lock-locked"></i></span>
                                    <input
                                        id="profile-current-password"
                                        type="password"
                                        name="current_password"
                                        autocomplete="current-password"
                                        class="form-control @error('current_password') is-invalid @enderror"
                                        placeholder="Required only when changing password"
                                    />
                                </div>
                                @error ('current_password')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label class="profile-label" for="profile-password">New Password</label>
                                <div class="input-group input-group--premium">
                                    <span class="input-group-text" aria-hidden="true"><i class="cil-shield-alt"></i></span>
                                    <input
                                        id="profile-password"
                                        type="password"
                                        name="password"
                                        autocomplete="new-password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        placeholder="Leave blank to keep current password"
                                    />
                                </div>
                                @error ('password')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label class="profile-label" for="profile-password-confirmation">Confirm Password</label>
                                <div class="input-group input-group--premium">
                                    <span class="input-group-text" aria-hidden="true"><i class="cil-check-circle"></i></span>
                                    <input
                                        id="profile-password-confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        autocomplete="new-password"
                                        class="form-control"
                                        placeholder="Repeat the new password"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="profileMainFooter" class="profile-form-actions mt-4 text-right">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="cil-save mr-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- Avatar Edit View (Inline) -->
        <div id="profileAvatarView" class="d-none">
            <div class="profile-avatar-modal__stage mb-4">
                <div class="profile-avatar-shell profile-avatar-shell--editor profile-avatar-shell--cropping mx-auto">
                    <img
                        id="avatar-preview-editor"
                        src="{{ $avatarUrl ?: $avatarFallback }}"
                        class="profile-avatar-image"
                        alt="Profile photo editor preview"
                    />
                    <input
                        type="file"
                        id="profileAvatarPicker"
                        class="profile-avatar-picker"
                        accept="image/*"
                        tabindex="-1"
                        aria-hidden="true"
                    />
                    <input
                        type="file"
                        name="avatar"
                        class="filepond profile-avatar-filepond"
                        id="avatarInput"
                        accept="image/*"
                        data-max-file-size="2MB"
                        data-filepond-label-idle=' '
                    />
                    <label
                        for="profileAvatarPicker"
                        class="profile-avatar-edit profile-avatar-edit--floating"
                        id="profileAvatarEditButton"
                        aria-label="Upload a new photo"
                        role="button"
                        tabindex="0"
                    >
                        <i class="cil-pencil"></i>
                    </label>
                </div>
            </div>
            <div
                class="profile-avatar-editor p-3 border rounded bg-light"
                id="profileAvatarEditor"
                aria-live="polite"
            >
                <div class="profile-avatar-editor__header d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="profile-avatar-editor__eyebrow text-muted small text-uppercase font-weight-bold">Avatar Editor</div>
                        <span class="profile-avatar-editor__title h6 mb-0">Crop and Scale</span>
                    </div>
                    <span
                        class="badge badge-primary"
                        id="profileAvatarZoomValue"
                    >
                        100%
                    </span>
                </div>
                <p class="profile-avatar-editor__copy text-muted small mb-3">
                    Drag the image to reposition it, then use zoom to frame your profile photo.
                </p>
                <div class="form-group mb-0">
                    <label
                        for="profileAvatarZoom"
                        class="small font-weight-bold"
                    >
                        Zoom
                    </label>
                    <div class="profile-avatar-editor__zoom-row d-flex align-items-center">
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm mr-2"
                            id="profileAvatarZoomOutButton"
                            aria-label="Zoom out"
                        >
                            <i class="cil-minus"></i>
                        </button>
                        <input
                            type="range"
                            id="profileAvatarZoom"
                            class="custom-range flex-grow-1"
                            min="100"
                            max="300"
                            value="100"
                            step="1"
                        />
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm ml-2"
                            id="profileAvatarZoomInButton"
                            aria-label="Zoom in"
                        >
                            <i class="cil-plus"></i>
                        </button>
                    </div>
                </div>
                <div id="profileAvatarFooter" class="mt-4 d-flex justify-content-end gap-2">
                    <button
                        type="button"
                        class="btn btn-light btn-sm"
                        id="profileAvatarResetButton"
                    >
                        Reset
                    </button>
                    <button
                        type="button"
                        class="btn btn-light btn-sm"
                        id="profileAvatarCancelButton"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        id="profileAvatarApplyButton"
                    >
                        Apply Photo
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
