@extends ('layouts.admin')
@section ('content')
    <div class="container-fluid pt-3">
        <x-attendance-hero eyebrow="Attendance Calendar" />
        @if (!$holidayTableAvailable)
            <div class="alert alert-warning border-0 shadow-sm">
                Holiday setup is not available yet. Run
                <code>php artisan migrate</code> to create the holidays table.
            </div>
        @endif
        <div class="row attendance-calendar-summary">
            <div class="col-md-4 mb-3">
                <div
                    class="attendance-calendar-stat attendance-calendar-stat--holiday"
                >
                    <div class="attendance-calendar-stat__eyebrow">Month</div>
                    <div class="attendance-calendar-stat__value">
                        {{ $month->format('F Y') }}
                    </div>
                    <div class="attendance-calendar-stat__meta">
                        {{ $holidayCount }} holiday setup{{ $holidayCount === 1 ? '' : 's' }}
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div
                    class="attendance-calendar-stat attendance-calendar-stat--leave"
                >
                    <div class="attendance-calendar-stat__eyebrow">
                        On Leave
                    </div>
                    <div class="attendance-calendar-stat__value">
                        {{ $onLeaveCount }}
                    </div>
                    <div class="attendance-calendar-stat__meta">
                        Approved leave entries on the calendar
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div
                    class="attendance-calendar-stat attendance-calendar-stat--pending"
                >
                    <div class="attendance-calendar-stat__eyebrow">
                        Pending Leave
                    </div>
                    <div class="attendance-calendar-stat__value">
                        {{ $pendingLeaveCount }}
                    </div>
                    <div class="attendance-calendar-stat__meta">
                        Requests still in routing
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="attendance-calendar-shell">
                    <div class="attendance-calendar-shell__header">
                        <div>
                            <div class="attendance-calendar-shell__eyebrow">
                                DTR Planner
                            </div>
                            <h4 class="attendance-calendar-shell__title mb-0">
                                Holiday and Leave Calendar
                            </h4>
                        </div>
                        <div class="attendance-calendar-legend">
                            <span class="attendance-calendar-legend__item"
                                ><span
                                    class="attendance-calendar-legend__swatch attendance-calendar-legend__swatch--holiday"
                                ></span
                                >Holiday</span
                            >
                            <span class="attendance-calendar-legend__item"
                                ><span
                                    class="attendance-calendar-legend__swatch attendance-calendar-legend__swatch--leave"
                                ></span
                                >On Leave</span
                            >
                            <span class="attendance-calendar-legend__item"
                                ><span
                                    class="attendance-calendar-legend__swatch attendance-calendar-legend__swatch--pending"
                                ></span
                                >Pending Leave</span
                            >
                        </div>
                    </div>
                    <div
                        id="attendanceHolidayCalendar"
                        class="attendance-holiday-calendar"
                        data-events='@json($events)'
                        data-date-details='@json($dateDetails)'
                        data-selected-date="{{ $selectedDate }}"
                        data-can-manage="{{ $canManage ? '1' : '0' }}"
                        data-feed-url="{{ route('attendance.calendar.feed') }}"
                        data-store-url="{{ route('attendance.calendar.store') }}"
                        data-update-template="{{ route('attendance.calendar.update', ['holiday' => '__HOLIDAY__']) }}"
                        data-delete-template="{{ route('attendance.calendar.destroy', ['holiday' => '__HOLIDAY__']) }}"
                    ></div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="attendance-calendar-aside">
                    <div class="attendance-calendar-aside__header">
                        <div class="attendance-calendar-aside__eyebrow">
                            Selected Date
                        </div>
                        <h4
                            class="attendance-calendar-aside__title mb-0"
                            id="attendanceCalendarSelectedDateLabel"
                        ></h4>
                    </div>
                    <div
                        class="attendance-calendar-panel attendance-calendar-panel--holiday"
                    >
                        <div class="attendance-calendar-panel__title">
                            Holiday Setup
                        </div>
                        <div
                            id="attendanceCalendarHolidayState"
                            class="attendance-calendar-panel__body"
                        ></div>
                        @if ($canManage && $holidayTableAvailable)
                            <div class="attendance-calendar-panel__actions">
                                <x-ui.button
                                    variant="outline-danger"
                                    size="sm"
                                    class="d-none"
                                    id="attendanceHolidayDeleteBtn"
                                    icon="cil-trash"
                                >
                                    Remove
                                </x-ui.button>
                                <x-ui.button
                                    variant="primary"
                                    size="sm"
                                    id="attendanceHolidayActionBtn"
                                    icon="cil-calendar-check"
                                    data-coreui-toggle="modal"
                                    data-coreui-target="#holidayModal"
                                >
                                    Set Holiday
                                </x-ui.button>
                            </div>
                        @endif
                    </div>
                    <div
                        class="attendance-calendar-panel attendance-calendar-panel--leave"
                    >
                        <div class="attendance-calendar-panel__title">
                            Employees On Leave
                        </div>
                        <div
                            id="attendanceCalendarOnLeaveList"
                            class="attendance-calendar-list"
                        ></div>
                    </div>
                    <div
                        class="attendance-calendar-panel attendance-calendar-panel--pending"
                    >
                        <div class="attendance-calendar-panel__title">
                            Pending Leave Requests
                        </div>
                        <div
                            id="attendanceCalendarPendingLeaveList"
                            class="attendance-calendar-list"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if ($canManage && $holidayTableAvailable)
        <x-ui.modal id="holidayModal" size="md">
            <x-ui.modal-header
                title="Set Holiday"
                subtitle="Configure a holiday entry for the attendance calendar."
                title-id="holidayModalTitle"
            />
            <form
                id="holidayForm"
                method="POST"
                action="{{ route('attendance.calendar.store') }}"
            >
                @csrf
                <input
                    type="hidden"
                    name="_method"
                    id="holidayFormMethod"
                    value="POST"
                />
                <div class="modal-body">
                    <div class="form-group">
                        <label for="holiday_date">Date</label>
                        <input
                            type="date"
                            class="form-control"
                            id="holiday_date"
                            name="holiday_date"
                            required
                        />
                    </div>
                    <div class="form-group">
                        <label for="holiday_name">Holiday Name</label>
                        <input
                            type="text"
                            class="form-control"
                            id="holiday_name"
                            name="name"
                            maxlength="255"
                            required
                        />
                    </div>
                    <div class="form-group">
                        <label for="holiday_type">Type</label>
                        <input
                            type="text"
                            class="form-control"
                            id="holiday_type"
                            name="type"
                            maxlength="100"
                            placeholder="Regular Holiday, Special Holiday, Local Holiday"
                        />
                    </div>
                    <div class="form-group mb-0">
                        <label for="holiday_remarks">Remarks</label>
                        <textarea
                            class="form-control"
                            id="holiday_remarks"
                            name="remarks"
                            rows="3"
                            maxlength="1000"
                            placeholder="Optional notes for DTR setup"
                        ></textarea>
                    </div>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button type="button" variant="light" data-coreui-dismiss="modal">
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        id="holidayModalSubmitBtn"
                        icon="cil-save"
                    >
                        Save Holiday
                    </x-ui.button>
                </x-ui.modal-footer>
            </form>
            <form id="holidayDeleteForm" method="POST" class="d-none">
                @csrf
                @method ('DELETE')
            </form>
        </x-ui.modal>
    @endif
@endsection
@push ('styles')
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css"
    />
@endpush
@push ('scripts')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
@endpush
