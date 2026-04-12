@extends ('layouts.auth')
@section ('title', 'Confirm Password')
@section ('subtitle', 'Security check required')
@section ('content')
    <p class="text-muted small mb-3">Confirm password before continuing.</p>
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="input-group mb-3">
            <input
                id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                placeholder="Password"
                required
                autocomplete="current-password"
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
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Confirm</button>
        </div>
    </form>
@endsection
