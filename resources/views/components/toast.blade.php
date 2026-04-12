@php
    $statusMessage = null;

    if (session('status')) {
        $statusMap = [
            'verification-link-sent' => 'A new verification link has been sent to the registered email.',
            'password-updated' => 'Password updated successfully.',
            'profile-updated' => 'Profile updated successfully.',
        ];

        $statusMessage = $statusMap[session('status')] ?? session('status');
    }

    $flashPayload = array_values(
        array_filter([
            session('success') ? ['type' => 'success', 'message' => session('success')] : null,
            session('error') ? ['type' => 'error', 'message' => session('error')] : null,
            session('warning') ? ['type' => 'warning', 'message' => session('warning')] : null,
            session('info') ? ['type' => 'info', 'message' => session('info')] : null,
            $statusMessage ? ['type' => 'success', 'message' => $statusMessage] : null,
        ]),
    );
@endphp

<div
    id="hrms-toast-region"
    class="hrms-toast-region"
    aria-live="polite"
    aria-atomic="false"
>
    <div id="toast-container" class="hrms-toast-container"></div>
</div>

<div
    id="flash-messages-data"
    class="d-none"
    data-messages-b64="{{ base64_encode(json_encode($flashPayload, JSON_UNESCAPED_UNICODE)) }}"
></div>
