@extends ('layouts.admin')
@section ('content')
    @php
        $detailMap = $evaluation ? $evaluation->details->keyBy('criteria_id') : collect();
        $status = strtolower((string) ($evaluation?->status ?? 'pending'));
        $statusVariant = match ($status) {
            'submitted' => 'info',
            'final' => 'success',
            default => 'secondary',
        };
        $statusText = match ($status) {
            'pending' => 'in progress',
            'submitted' => 'for final check',
            'final' => 'completed',
            default => $status,
        };
        $positionTitle = optional($employee->positions->first()?->position)->position ?? 'N/A';
        $nextStepTitle = match (true) {
            $status === 'pending' && $canEdit => 'Step 2: Review',
            $status === 'submitted' => 'Step 3: Finalize',
            $status === 'final' => 'Complete',
            default => 'Read only',
        };
        $nextStepMessage = match (true) {
            $status === 'pending' && $canEdit => 'Save Draft keeps this editable. Submit sends it for final HR review.',
            $status === 'submitted' => 'HR or an SPMS admin will lock this result.',
            $status === 'final' => 'Scores and rating are already locked.',
            default => 'This scorecard can only be viewed now.',
        };
    @endphp
    <div
        class="container-fluid pt-4 spms-page"
        id="spmsEvaluationShowPage"
        data-page="spms.evaluations.show"
    >
        <x-page-header
            eyebrow="SPMS"
            title="{{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }}"
            subtitle="{{ $cycle->title }} · {{ ucfirst($statusText) }}"
        >
            <x-slot:actions>
                <a
                    href="{{ route('spms.cycle.show', $cycle->id) }}"
                    class="btn btn-outline-secondary btn-sm"
                >
                    <i class="cil-arrow-left mr-1"></i> Back
                </a>
            </x-slot:actions>
        </x-page-header>
        <div class="alert alert-light border shadow-sm mb-3">
            <div class="font-weight-bold">{{ $nextStepTitle }}</div>
            <div class="text-muted mb-0">{{ $nextStepMessage }}</div>
        </div>
        @if (($previousCycleSeededCount ?? 0) > 0)
            <div class="alert alert-info border-0 shadow-sm mb-3">
                <strong>Previous review copied.</strong>
                {{ (int) $previousCycleSeededCount }} criterion entr{{ (int) $previousCycleSeededCount === 1 ? 'y was' : 'ies were' }}
                copied from the employee's latest SPMS review. Check each score before saving or submitting.
            </div>
        @endif
        <div class="row g-3 spms-overview-row mb-3">
            <div class="col-xl-4 col-md-6">
                <div class="spms-overview-card">
                    <div class="spms-overview-label">Employee</div>
                    <div class="spms-overview-value spms-overview-value--text">
                        {{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }}
                    </div>
                    <div class="spms-overview-meta">
                        {{ $employee->department?->department ?? 'N/A' }} - {{ $positionTitle }}
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="spms-overview-card">
                    <div class="spms-overview-label">Evaluator</div>
                    <div class="spms-overview-value spms-overview-value--text">
                        {{ $evaluation?->evaluator?->name ?? auth()->user()->name }}
                    </div>
                    <div class="spms-overview-meta">
                        {{ optional($cycle->period_start)->format('M d, Y') }} - {{ optional($cycle->period_end)->format('M d, Y') }}
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12">
                <div class="spms-overview-card">
                    <div class="spms-overview-label">Current Step</div>
                    <div
                        class="spms-overview-value spms-overview-value--status"
                    >
                        <x-ui.status-badge
                            class="text-uppercase"
                            :status="$status"
                            :text="$statusText"
                            :variant="$statusVariant"
                        />
                    </div>
                    <div class="spms-overview-meta">Current process step</div>
                </div>
            </div>
        </div>
        <div class="row g-4 spms-evaluation-layout">
            <div class="col-lg-9">
                <x-ui.table-card
                    title="Scorecard"
                    subtitle="Scores and remarks for each review area."
                    class="hrms-list-card"
                >
                    @if ($canEdit)
                        <form
                            method="POST"
                            action="{{ route('spms.evaluation.save') }}"
                            id="spmsEvaluationForm"
                            novalidate
                        >
                            @csrf
                            <input
                                type="hidden"
                                name="employee_id"
                                value="{{ $employee->id }}"
                            />
                            <input
                                type="hidden"
                                name="cycle_id"
                                value="{{ $cycle->id }}"
                            />
                            <input
                                type="hidden"
                                name="intent"
                                id="spmsEvaluationIntent"
                                value="draft"
                            />
                            <table
                                class="table table-hover align-middle mb-0 hrms-table hrms-list-table"
                                id="spmsEvaluationCriteriaTable"
                            >
                                <thead>
                                    <tr>
                                        <th>Criteria</th>
                                        <th>Description</th>
                                        <th class="text-center">Weight</th>
                                        <th class="text-center">Score (1-5)</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($criteria as $index => $criterion)
                                        @php $detail = $detailMap->get($criterion->id); $isAttendanceKpiRow = isset($attendanceCriterionId) && (int) $attendanceCriterionId === (int) $criterion->id; $score = $isAttendanceKpiRow ? old('details.' . $index . '.score', $attendanceCriterionScore ?? $detail?->score ?? 1) : old('details.' . $index . '.score', $detail?->score ?? 1); $remarks = old('details.' . $index . '.remarks', $detail?->remarks); @endphp
                                        <tr>
                                            <td class="font-weight-bold">
                                                <input
                                                    type="hidden"
                                                    name="details[{{ $index }}][criteria_id]"
                                                    value="{{ $criterion->id }}"
                                                />
                                                {{ $criterion->name }}
                                                @if ($isAttendanceKpiRow)
                                                    <div>
                                                        <x-ui.status-badge
                                                            class="mt-1"
                                                            status="computed"
                                                            text="Auto-computed"
                                                            variant="info"
                                                        />
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $criterion->description ?: '-' }}
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="spms-criterion-weight"
                                                    data-weight="{{ (float) $criterion->weight }}"
                                                    >{{ number_format((float) $criterion->weight, 2) }}%</span
                                                >
                                            </td>
                                            <td
                                                class="text-center"
                                                style="min-width: 170px"
                                            >
                                                @if ($isAttendanceKpiRow)
                                                    <input
                                                        type="hidden"
                                                        name="details[{{ $index }}][score]"
                                                        value="{{ $score }}"
                                                    />
                                                    <input
                                                        type="text"
                                                        class="form-control form-control-sm text-center spms-score-input"
                                                        value="{{ number_format((float) $score, 2) }}"
                                                        readonly
                                                        data-score="{{ (float) $score }}"
                                                    />
                                                    @if ($attendanceKpiScore)
                                                        <small
                                                            class="text-muted d-block mt-1"
                                                        >
                                                            Rating: {{ (int) $attendanceKpiScore->rating }} |
                                                            Presence: {{ number_format((float) $attendanceKpiScore->attendance_rate, 2) }}%
                                                            | On-Time: {{ number_format((float) $attendanceKpiScore->punctuality_rate, 2) }}%
                                                        </small>
                                                    @endif
                                                @else
                                                    <select
                                                        name="details[{{ $index }}][score]"
                                                        class="form-control form-control-sm spms-score-input text-center"
                                                        required
                                                    >
                                                        @for ($i = 1; $i <= 5; $i++)
                                                            <option
                                                                value="{{ $i }}"
                                                                @selected ((float) $score === (float) $i)
                                                                >{{ $i }}
                                                            </option>
                                                        @endfor
                                                    </select>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($isAttendanceKpiRow)
                                                    <input
                                                        type="hidden"
                                                        name="details[{{ $index }}][remarks]"
                                                        value="{{ $remarks ?: 'Attendance KPI auto-integrated from monthly score.' }}"
                                                    />
                                                    <textarea
                                                        class="form-control form-control-sm"
                                                        rows="2"
                                                        readonly
                                                        >{{ $remarks ?: 'Attendance KPI auto-integrated from monthly score.' }}</textarea
                                                    >
                                                @else
                                                    <textarea
                                                        name="details[{{ $index }}][remarks]"
                                                        class="form-control form-control-sm"
                                                        rows="2"
                                                        placeholder="Optional remarks..."
                                                        >{{ $remarks }}</textarea
                                                    >
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="text-muted small mt-3">
                                Save Draft keeps the scorecard editable. Submit sends it for final review and locks evaluator editing.
                            </div>
                            <div class="d-flex mt-3 spms-eval-actions gap-2">
                                <button
                                    type="submit"
                                    data-spms-intent="draft"
                                    class="btn btn-outline-secondary"
                                >
                                    <i class="cil-save mr-1"></i> Save Draft
                                </button>
                                <button
                                    type="submit"
                                    data-spms-intent="submitted"
                                    class="btn btn-primary spms-submit-confirm"
                                    data-confirm-text="Submit this scorecard? Editing is disabled after submission."
                                >
                                    <i class="cil-paper-plane mr-1"></i> Submit
                                </button>
                            </div>
                        </form>
                    @else
                        @if ($evaluation)
                            <table
                                class="table table-hover align-middle mb-0 hrms-table hrms-list-table"
                            >
                                <thead>
                                    <tr>
                                        <th>Criteria</th>
                                        <th>Description</th>
                                        <th class="text-center">Weight</th>
                                        <th class="text-center">Score</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($criteria as $criterion)
                                        @php $detail = $detailMap->get($criterion->id); @endphp
                                        <tr>
                                            <td class="font-weight-bold">
                                                {{ $criterion->name }}
                                            </td>
                                            <td>
                                                {{ $criterion->description ?: '-' }}
                                            </td>
                                            <td class="text-center">
                                                {{ number_format((float) $criterion->weight, 2) }}%
                                            </td>
                                            <td class="text-center">
                                                {{ $detail ? number_format((float) $detail->score, 2) : '-' }}
                                            </td>
                                            <td>
                                                {{ $detail?->remarks ?: '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="spms-empty-state text-center py-4">
                                <i class="cil-info"></i>
                                <h6>Evaluation not available</h6>
                                <p class="mb-0">No evaluation record exists yet for this employee and cycle.</p>
                            </div>
                        @endif
                    @endif
                </x-ui.table-card>
            </div>
            <div class="col-lg-3">
                <div
                    class="card shadow-sm border-0 hrms-list-card spms-summary-card spms-summary-panel"
                >
                    <div class="card-header border-0 bg-transparent py-3">
                        <div>
                            <div class="spms-panel-eyebrow">Summary</div>
                            <h3
                                class="card-title font-weight-bold mb-0 text-dark"
                            >
                                <i class="cil-calculator mr-2 text-primary"></i
                                >Summary
                            </h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="text-muted small text-uppercase">
                                Total Score
                            </div>
                            <div
                                class="h3 font-weight-bold mb-0"
                                id="spmsTotalScoreDisplay"
                            >
                                {{ number_format((float) ($evaluation?->total_score ?? 0), 2) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase">
                                Rating
                            </div>
                            <div
                                class="h5 font-weight-bold mb-0 text-primary"
                                id="spmsRatingLabelDisplay"
                            >
                                {{ str_replace('_', ' ', (string) ($evaluation?->rating_label ?? app(\App\Services\SpmsScoringService::class)->scoreLabel((float) ($evaluation?->total_score ?? 0)))) }}
                            </div>
                        </div>
                        <div class="spms-summary-divider"></div>
                        <div class="spms-summary-meta">
                            <div class="spms-summary-meta__label">
                                Current Step
                            </div>
                            <div class="spms-summary-meta__value">
                                {{ match ($status) {
                                    'pending' => 'In progress',
                                    'submitted' => 'For final check',
                                    'final' => 'Completed',
                                    default => ucfirst($status),
                                } }}
                            </div>
                        </div>
                        <div class="spms-summary-meta">
                            <div class="spms-summary-meta__label">Cycle</div>
                            <div class="spms-summary-meta__value">
                                {{ $cycle->title }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
