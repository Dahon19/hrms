@extends ('layouts.auth')
@section ('title', 'Reset Password')
@section ('subtitle', 'Set a new account password')
@section ('content')
    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input
            type="hidden"
            name="token"
            value="{{ $request->route('token') }}"
        />
        <div class="input-group mb-3">
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $request->email) }}"
                class="form-control @error('email') is-invalid @enderror"
                placeholder="Email address"
                required
                autofocus
                autocomplete="username"
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
        <div class="input-group mb-3">
            <input
                id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                placeholder="New password"
                required
                autocomplete="new-password"
            />
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="cil-lock-locked"></span>
                </div>
            </div>
            @error ('password')
                <span class="invalid-feedback d-block">{{ $message }}</span>
            @enderror
        </div>
        <div class="input-group mb-3">
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                class="form-control @error('password_confirmation') is-invalid @enderror"
                placeholder="Confirm password"
                required
                autocomplete="new-password"
            />
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="cil-lock-locked"></span>
                </div>
            </div>
            @error ('password_confirmation')
                <span class="invalid-feedback d-block">{{ $message }}</span>
            @enderror
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">
                Reset Password
            </button>
        </div>
    </form>
@endsection
