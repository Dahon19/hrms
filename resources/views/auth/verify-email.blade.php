@extends ('layouts.auth')
@section ('title', 'Verify Email')
@section ('subtitle', 'Confirm email to continue')
@section ('content')
    <p class="text-muted small">Account registration received. Verify email using the link sent. If the link was not received, request another verification email.</p>
    <div class="d-flex align-items-center justify-content-between mt-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
                Resend Verification Email
            </button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                Log Out
            </button>
        </form>
    </div>
@endsection
