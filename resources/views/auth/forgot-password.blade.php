@extends ('layouts.auth')
@section ('title', 'Forgot Password')
@section ('subtitle', 'A reset link will be sent to the registered email')
@section ('content')
    <p class="text-muted small mb-3">Enter the registered email to receive a password reset link.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="input-group mb-3">
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                placeholder="Email address"
                required
                autofocus
            />
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="cil-envelope-open"></span>
                </div>
            </div>
            @error ('email')
                <span class="invalid-feedback d-block">{{ $message }}</span>
            @enderror
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">
                Email Reset Link
            </button>
        </div>
    </form>
@endsection
