@extends ('layouts.auth')
@section ('title', 'Register')
@section ('subtitle', 'Create a new account')
@section ('content')
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="input-group mb-3">
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                class="form-control @error('name') is-invalid @enderror"
                placeholder="Full name"
                required
                autofocus
                autocomplete="name"
            />
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="cil-user"></span>
                </div>
            </div>
            @error ('name')
                <span class="invalid-feedback d-block">{{ $message }}</span>
            @enderror
        </div>
        <div class="input-group mb-3">
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                placeholder="Email address"
                required
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
                placeholder="Password"
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
            <button type="submit" class="btn btn-primary">Register</button>
        </div>
        <p class="mb-0 mt-3"><a href="{{ route('login') }}">Already registered?</a></p>
    </form>
@endsection
