@extends ('layouts.admin')
@section ('content')
    <div class="profile-page-shell" id="profileEditPage">
        <div class="hrms-page-header mb-4">
            <h1 class="hrms-page-title">
                <i class="cil-user"></i>Edit Profile
            </h1>
            <p class="hrms-page-subtitle">Update your account information and security settings.</p>
        </div>
        
        @include ('profile.partials.edit-form', ['profileUser' => Auth::user()])
    </div>
@endsection
