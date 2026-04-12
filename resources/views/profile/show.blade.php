{{-- resources/views/profile/show.blade.php --}}
@extends ('layouts.admin')
@section ('content')
    {{-- Trigger the profile modal on page load via data attribute (handled by profile.js) --}}
    <div
        id="profileShowPageTrigger"
        data-auto-modal="profileEditModal"
        class="d-none"
    ></div>
@endsection
