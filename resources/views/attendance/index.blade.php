@extends ('layouts.admin')
@section ('content')
    <div
        class="container-fluid attendance-page"
        id="attendanceKioskCard"
        data-kiosk-enabled="{{ $isHrHead ? '1' : '0' }}"
        data-csrf="{{ csrf_token() }}"
        data-nfc-url="{{ rtrim(request()->getBaseUrl(), '/') }}/api/nfc/latest"
        data-attendance-url="{{ route('attendance.self') }}"
        data-feed-url="{{ route('attendance.live') }}"
        data-today="{{ now()->toDateString() }}"
    >
        <div class="row justify-content-center">
            <div class="col-12">
                <div
                    class="card shadow-sm border-0 attendance-fs-target"
                    id="attendanceFullscreenTarget"
                >
                    @if ($isHrHead || $isAdmin)
                        <div
                            class="card-body border-bottom attendance-kiosk-head"
                        >
                            <div
                                class="row align-items-center attendance-kiosk-top-row"
                                id="kioskTopRow"
                            >
                                <div
                                    class="col-md-6 text-center border-right attendance-kiosk-clock-col"
                                    id="clockBranding"
                                >
                                    <h5 class="text-muted small text-uppercase">
                                        <i class="cil-clock mr-1"></i> Server
                                        Time
                                    </h5>
                                    <h1
                                        id="realtime-clock"
                                        class="display-4 font-weight-bold text-primary mb-0"
                                        data-timestamp="{{ now()->timestamp * 1000 }}"
                                    >
                                        {{ now()->format('h:i:s A') }}
                                    </h1>
                                    <p class="text-muted">{{ now()->format('l, F j, Y') }}</p>
                                </div>
                                <div
                                    class="col-md-6 text-center mt-4 position-relative attendance-kiosk-action-col"
                                    id="actionBranding"
                                >
                                    @if ($isHrHead)
                                        <div
                                            class="attendance-panel-fs-controls"
                                        >
                                            <x-ui.button
                                                variant="outline-secondary"
                                                size="sm"
                                                class="attendance-fs-btn"
                                                id="attendanceEnterFullscreen"
                                                icon="cil-fullscreen"
                                            />
                                            <x-ui.button
                                                variant="outline-secondary"
                                                size="sm"
                                                class="attendance-fs-btn d-none"
                                                id="attendanceExitFullscreen"
                                                icon="cil-fullscreen-exit"
                                            />
                                        </div>
                                    @endif
                                    <div class="mb-3">
                                        @if ($isHrHead)
                                            <x-ui.status-badge
                                                class="px-3 py-2 text-uppercase attendance-kiosk-mode-badge"
                                                status="active"
                                                text="Tap NFC Card to Record Attendance"
                                                variant="primary"
                                            />
                                        @elseif ($isAdmin)
                                            <x-ui.status-badge
                                                class="px-3 py-2 text-uppercase attendance-kiosk-mode-badge"
                                                status="active"
                                                text="Administrator Mode"
                                                variant="primary"
                                            />
                                        @endif
                                    </div>
                                    @if ($isHrHead)
                                        <div
                                            id="kioskProfileCard"
                                            class="d-inline-block text-center kiosk-profile-card"
                                        >
                                            <div
                                                class="kiosk-avatar-wrapper mb-2"
                                            >
                                                <img
                                                    id="kioskAvatar"
                                                    class="img-fluid rounded-circle shadow-sm"
                                                />
                                            </div>
                                            <div
                                                class="font-weight-bold h5 mb-1 kiosk-name"
                                                id="kioskName"
                                            ></div>
                                            <div
                                                class="text-muted small kiosk-department"
                                                id="kioskDepartment"
                                            ></div>
                                        </div>
                                    @else
                                        <div class="text-muted small mt-4">
                                            Admin accounts do not record
                                            attendance.
                                        </div>
                                    @endif
                                    <div
                                        class="mt-3 text-muted small kiosk-status-message"
                                        id="kioskStatusMessage"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="table-responsive attendance-table-mobile-wrap">
                        <table
                            class="table table-hover align-middle mb-0 hrms-table attendance-table"
                            id="attendanceTable"
                            data-dt-search="0"
                            data-no-datatable="1"
                        >
                            <thead>
                                <tr>
                                    <th class="text-uppercase">Employee</th>
                                    <th class="text-uppercase">Morning In</th>
                                    <th class="text-uppercase">Morning Out</th>
                                    <th class="text-uppercase">Afternoon In</th>
                                    <th class="text-uppercase">
                                        Afternoon Out
                                    </th>
                                    <th class="text-uppercase">Status</th>
                                    <th class="d-none">Last Tap</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $latestTapTs = function ($att) { $dateString = $att->date instanceof \Carbon\CarbonInterface ? $att->date->toDateString() : (string) $att->date; $fields = ['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out']; $timestamps = []; foreach ($fields as $field) { $time = $att->$field ?? null; if ($time) { $timestamps[] = \Carbon\Carbon::parse($dateString . ' ' . $time)->timestamp; } } if (!empty($timestamps)) { return max($timestamps); } return optional($att->updated_at)->timestamp ?? 0; }; @endphp
                                @forelse ($attendance as $att)
                                
                                    @php $lastTapTimestamp = $latestTapTs($att); $statusVariant = match ($att->status) { 'present' => 'success', 'late' => 'warning', 'official_business' => 'info', 'excused' => 'primary', 'holiday' => 'danger', default => 'secondary', }; $statusLabel = match ($att->status) { 'official_business' => 'Official Business', 'excused' => 'Excused', 'holiday' => 'Holiday', default => ucfirst($att->status), }; $morningInLabel = $att->morning_time_in ? \Carbon\Carbon::parse($att->morning_time_in)->format('h:i A') : '--:--'; $morningOutLabel = $att->morning_time_out ? \Carbon\Carbon::parse($att->morning_time_out)->format('h:i A') : '--:--'; $afternoonInLabel = $att->afternoon_time_in ? \Carbon\Carbon::parse($att->afternoon_time_in)->format('h:i A') : '--:--'; $afternoonOutLabel = $att->afternoon_time_out ? \Carbon\Carbon::parse($att->afternoon_time_out)->format('h:i A') : '--:--'; @endphp
                                    <tr
                                        class="text-center"
                                        data-employee-id="{{ $att->employee_id }}"
                                        data-date="{{ \Carbon\Carbon::parse($att->date)->toDateString() }}"
                                    >
                                        <td
                                            class="text-left align-middle employee-col"
                                            data-label="Employee"
                                        >
                                            <strong>
                                                {{ $att->employee->first_name ?? 'Unknown' }} {{ $att->employee->last_name ?? '' }}
                                            </strong>
                                        </td>
                                        <td
                                            class="align-middle text-success font-weight-bold"
                                            data-label="Morning In"
                                        >
                                            {{ $morningInLabel }}
                                        </td>
                                        <td
                                            class="align-middle text-danger font-weight-bold"
                                            data-label="Morning Out"
                                        >
                                            {{ $morningOutLabel }}
                                        </td>
                                        <td
                                            class="align-middle text-success font-weight-bold"
                                            data-label="Afternoon In"
                                        >
                                            {{ $afternoonInLabel }}
                                        </td>
                                        <td
                                            class="align-middle text-danger font-weight-bold"
                                            data-label="Afternoon Out"
                                        >
                                            {{ $afternoonOutLabel }}
                                        </td>
                                        <td
                                            class="align-middle"
                                            data-label="Status"
                                        >
                                            <x-ui.status-badge
                                                class="px-3 py-2 attendance-status-pill"
                                                :status="$att->status"
                                                :text="$statusLabel"
                                                :variant="$statusVariant"
                                            />
                                        </td>
                                        <td class="d-none">
                                            {{ $lastTapTimestamp }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr
                                        class="text-center text-muted attendance-empty-row"
                                    >
                                        <td class="py-5" colspan="7">
                                            No attendance logs found today.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if (method_exists($attendance, 'links'))
                        <div class="px-3 py-3 border-top bg-white attendance-table-pagination">
                            {{ $attendance->links() }}
                        </div>
                    @endif
                    @if ($isHrHead)
                        <div
                            class="attendance-confirm-overlay"
                            id="attendanceFullscreenConfirm"
                            aria-hidden="true"
                        >
                            <div
                                class="attendance-confirm-dialog"
                                role="dialog"
                                aria-modal="true"
                                aria-labelledby="attendanceConfirmTitle"
                            >
                                <div class="attendance-confirm-head">
                                    <span class="attendance-confirm-icon"
                                        ><i class="cil-fullscreen"></i
                                    ></span>
                                    <h4
                                        class="attendance-confirm-title"
                                        id="attendanceConfirmTitle"
                                    >
                                        Fullscreen Confirmation
                                    </h4>
                                </div>
                                <p class="attendance-confirm-message" id="attendanceConfirmMessage">Proceed with fullscreen action?</p>
                                <div class="attendance-confirm-actions">
                                    <x-ui.button
                                        variant="outline-secondary"
                                        class="attendance-confirm-cancel"
                                        id="attendanceConfirmCancel"
                                    >
                                        Cancel</x-ui.button
                                    >
                                    <x-ui.button
                                        variant="primary"
                                        class="attendance-confirm-proceed"
                                        id="attendanceConfirmProceed"
                                    >
                                        Proceed</x-ui.button
                                    >
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
