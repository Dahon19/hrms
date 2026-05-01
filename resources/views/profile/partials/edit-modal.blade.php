@php $profileUser = $profileUser ?? Auth::user(); $avatarLetter = strtoupper(substr($profileUser->employee->first_name ?? $profileUser->name ?? 'U', 0, 1)); $avatarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">' . '<rect width="100%" height="100%" fill="#6c757d"/>' . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="96" fill="#ffffff">' . e($avatarLetter) . '</text></svg>'; $avatarFallback = 'data:image/svg+xml;base64,' . base64_encode($avatarSvg); $avatarUrl = null; if (!empty($profileUser->avatar)) { $parts = explode('/', $profileUser->avatar); $folder = $parts[0] ?? null; $subfolder = $parts[1] ?? null; $filename = $parts[2] ?? null; if ($folder && $subfolder && $filename) { $avatarUrl = route('storage.file', [ 'folder' => $folder, 'subfolder' => $subfolder, 'filename' => $filename, ]); } } $profileName = trim(($profileUser->employee->first_name ?? $profileUser->name ?? 'User') . ' ' . ($profileUser->employee->last_name ?? '')); $positionLabel = ucfirst($profileUser->employee?->positions?->first()?->position?->position ?? 'No Position Assigned'); $departmentLabel = $profileUser->employee?->department?->department ?? 'N/A'; $showProfileEditOnLoad = old('form_context') === 'profile_edit' && $errors->hasAny([ 'avatar', 'name', 'email', 'current_password', 'password', ]); @endphp
@php
    $canEditAccountName = $profileUser->isAdmin() || \App\Services\AccessControl::isHrStaff($profileUser);
@endphp
<x-ui.modal
    id="profileEditModal"
    size="lg"
    class="profile-modal"
>
    <div id="profileModalHeaderGroup">
        <div id="profileMainHeader">
            <x-ui.modal-header
                title="Profile"
                subtitle="Manage account details, avatar, and password."
            />
        </div>
        <div id="profileAvatarHeader" class="d-none">
            <x-ui.modal-header
                title="Update Profile Photo"
                subtitle="Upload a photo, adjust the crop, and apply it to your profile."
                :dismissible="false"
            />
        </div>
    </div>

    <div id="profileEditModalContent">
        @include ('profile.partials.edit-form', ['profileUser' => $profileUser, 'attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => 'profile-modal-body'])])
    </div>
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
