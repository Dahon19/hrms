@extends ('layouts.admin')
@section ('content')
    <div class="profile-page-shell" id="profileShowPage">
        <div class="hrms-page-header mb-4">
            <h1 class="hrms-page-title">
                <i class="cil-user"></i>Account Profile
            </h1>
            <p class="hrms-page-subtitle">Manage your personal information, security, and preferences.</p>
        </div>
        
        @include ('profile.partials.edit-form', ['profileUser' => $user])
    </div>
@endsection
