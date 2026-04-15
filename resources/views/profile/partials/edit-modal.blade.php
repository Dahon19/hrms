@php $profileUser = $profileUser ?? Auth::user(); $avatarLetter = strtoupper(substr($profileUser->employee->first_name ?? $profileUser->name ?? 'U', 0, 1)); $avatarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">' . '<rect width="100%" height="100%" fill="#6c757d"/>' . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="96" fill="#ffffff">' . e($avatarLetter) . '</text></svg>'; $avatarFallback = 'data:image/svg+xml;base64,' . base64_encode($avatarSvg); $avatarUrl = null; if (!empty($profileUser->avatar)) { $parts = explode('/', $profileUser->avatar); $folder = $parts[0] ?? null; $subfolder = $parts[1] ?? null; $filename = $parts[2] ?? null; if ($folder && $subfolder && $filename) { $avatarUrl = route('storage.file', [ 'folder' => $folder, 'subfolder' => $subfolder, 'filename' => $filename, ]); } } $profileName = trim(($profileUser->employee->first_name ?? $profileUser->name ?? 'User') . ' ' . ($profileUser->employee->last_name ?? '')); $positionLabel = ucfirst($profileUser->employee?->positions?->first()?->position?->position ?? 'No Position Assigned'); $departmentLabel = $profileUser->employee?->department?->department ?? 'N/A'; $showProfileEditOnLoad = old('form_context') === 'profile_edit' && $errors->hasAny([ 'avatar', 'name', 'email', 'current_password', 'password', ]); @endphp
@php
    $canEditAccountName = $profileUser->isAdmin() || \App\Services\AccessControl::isHrStaff($profileUser);
@endphp
<x-ui.modal
    id="profileEditModal"
    size="lg"
    class="profile-modal"
>
            <form
                id="profileEditForm"
                method="POST"
                action="{{ route('profile.update') }}"
                enctype="multipart/form-data"
            >
                <input type="hidden" name="form_context" value="profile_edit" />
                @csrf
                @method ('PATCH')
                <x-ui.modal-header
                    title="Profile"
                    subtitle="Manage account details, avatar, and password."
                />
                <div class="modal-body profile-modal-body">
                    <div class="profile-workspace">
                        <aside class="profile-sidecard" id="profileSidecard">
                            <div class="profile-avatar-shell profile-avatar-shell--editor">
                                <img
                                    id="avatar-preview"
                                    src="{{ $avatarUrl ?: $avatarFallback }}"
                                    class="profile-avatar-image"
                                    data-original-src="{{ $avatarUrl ?: $avatarFallback }}"
                                    alt="User profile picture"
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
                                    class="profile-avatar-edit"
                                    id="profileAvatarEditButton"
                                    aria-label="Change avatar"
                                    role="button"
                                    tabindex="0"
                                >
                                    <i class="cil-pencil"></i>
                                </label>
                            </div>
                            <div
                                class="profile-avatar-editor d-none"
                                id="profileAvatarEditor"
                                aria-live="polite"
                            >
                                <div class="profile-avatar-editor__header">
                                    <div>
                                        <div class="profile-avatar-editor__eyebrow">Avatar Editor</div>
                                        <span class="profile-avatar-editor__title">Crop and Scale</span>
                                    </div>
                                    <span
                                        class="profile-avatar-editor__zoom-badge"
                                        id="profileAvatarZoomValue"
                                    >
                                        100%
                                    </span>
                                </div>
                                <p class="profile-avatar-editor__copy mb-0">
                                    Drag the image to reposition it, then use zoom to frame your profile photo.
                                </p>
                                <label
                                    for="profileAvatarZoom"
                                    class="profile-avatar-editor__label"
                                >
                                    Zoom
                                </label>
                                <input
                                    type="range"
                                    id="profileAvatarZoom"
                                    class="custom-range profile-avatar-editor__zoom"
                                    min="100"
                                    max="300"
                                    value="100"
                                    step="1"
                                />
                                <div class="profile-avatar-editor__actions">
                                    <button
                                        type="button"
                                        class="btn btn-light btn-sm profile-avatar-editor__action"
                                        id="profileAvatarResetButton"
                                    >
                                        Reset
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-light btn-sm profile-avatar-editor__action"
                                        id="profileAvatarCancelButton"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm profile-avatar-editor__action"
                                        id="profileAvatarApplyButton"
                                    >
                                        Apply Crop
                                    </button>
                                </div>
                            </div>
                            <p class="profile-avatar-help mb-0">
                                Use the pencil icon to upload, crop, and scale your avatar before saving.
                            </p>
                            <div class="profile-sidecard__identity">
                                <h4 class="profile-identity-name mb-1">{{ $profileName }}</h4>
                                <p class="profile-identity-role mb-0">{{ $positionLabel }}</p>
                                <p class="profile-sidecard__subline mb-0">{{ $departmentLabel }}</p>
                            </div>
                            <div class="profile-sidecard__stats">
                                <div class="profile-stat">
                                    <span class="profile-stat__label"><i class="cil-credit-card"></i> Employee ID</span>
                                    <strong class="profile-stat__value">{{ $profileUser->employee->employee_id ?? 'N/A' }}</strong>
                                </div>
                                <div class="profile-stat">
                                    <span class="profile-stat__label"><i class="cil-calendar"></i> Member Since</span>
                                    <strong class="profile-stat__value">{{ $profileUser->created_at->format('M d, Y') }}</strong>
                                </div>
                            </div>
                            @error ('avatar')
                                <small class="text-danger d-block mt-2">{{ $message }}</small>
                            @enderror
                        </aside>
                        <div class="profile-maincard">
                            <div class="profile-section">
                                <div class="profile-section-heading">Account</div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="profile-name">Account Name</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="cil-user"></i></span>
                                                </div>
                                                <input
                                                    type="text"
                                                    name="name"
                                                    id="profile-name"
                                                    class="form-control @error('name') is-invalid @enderror"
                                                    value="{{ old('name', $profileUser->name) }}"
                                                    @if (!$canEditAccountName)
                                                        readonly
                                                        aria-readonly="true"
                                                    @endif
                                                />
                                            </div>
                                            @if (!$canEditAccountName)
                                                <small class="form-text text-muted">
                                                    Account name updates are handled by HR upon request.
                                                </small>
                                            @endif
                                            @error ('name')
                                                <span class="invalid-feedback d-block">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="profile-email">Email Address</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="cil-envelope-letter"></i></span>
                                                </div>
                                                <input
                                                    type="email"
                                                    name="email"
                                                    id="profile-email"
                                                    autocomplete="email"
                                                    class="form-control @error('email') is-invalid @enderror"
                                                    value="{{ old('email', $profileUser->email) }}"
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
                                <div class="profile-section-heading profile-section-heading--security">Security</div>
                                <p class="profile-section-note">Leave these blank to keep the current password.</p>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="profile-current-password">Current Password</label>
                                            <input
                                                id="profile-current-password"
                                                type="password"
                                                name="current_password"
                                                autocomplete="current-password"
                                                class="form-control @error('current_password') is-invalid @enderror"
                                            />
                                            @error ('current_password')
                                                <span class="invalid-feedback d-block">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="profile-password">New Password</label>
                                            <input
                                                id="profile-password"
                                                type="password"
                                                name="password"
                                                autocomplete="new-password"
                                                class="form-control @error('password') is-invalid @enderror"
                                            />
                                            @error ('password')
                                                <span class="invalid-feedback d-block">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="profile-password-confirmation">Confirm Password</label>
                                            <input
                                                id="profile-password-confirmation"
                                                type="password"
                                                name="password_confirmation"
                                                autocomplete="new-password"
                                                class="form-control"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <x-ui.modal-footer>
                    <button
                        type="button"
                        class="btn btn-light btn-sm"
                        data-coreui-dismiss="modal"
                    >
                        Close
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary btn-sm"
                    >
                        Save Changes
                    </button>
                </x-ui.modal-footer>
            </form>
</x-ui.modal>
<form
    id="send-verification"
    method="POST"
    action="{{ route('verification.send') }}"
>
    @csrf
</form>
<div
    id="profileEditModalTrigger"
    data-open="{{ $showProfileEditOnLoad ? '1' : '0' }}"
    class="d-none"
></div>
