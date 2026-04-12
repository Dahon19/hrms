@extends ('layouts.admin')

@section ('content')
    @php
        $pendingApplicantApprovals = ($view ?? 'active') === 'history'
            ? collect()
            : ($pendingApplicantApprovals ?? collect());
    @endphp
    <div class="container-fluid pt-4" id="jobPostingApplicantsPage">
        <x-page-header
            eyebrow="Recruitment"
            title="Applicants"
            subtitle="Review candidate submissions and required application documents."
        >
            <x-slot:actions>
                @if (($canReviewApprovals ?? false) && $pendingApplicantApprovals->isNotEmpty())
                    <span class="badge badge-soft-warning px-3 py-2">
                        {{ $pendingApplicantApprovals->count() }} pending request{{ $pendingApplicantApprovals->count() === 1 ? '' : 's' }}
                    </span>
                @endif
                <span class="badge badge-soft-primary px-3 py-2">
                    {{ $applicants->total() }} applicant{{ $applicants->total() === 1 ? '' : 's' }}
                </span>
            </x-slot:actions>
        </x-page-header>

        <x-ui.table-card class="border-0 hrms-list-card">
            <x-slot:header>
                <div class="applicants-table-header">
                    <div class="applicants-table-header__copy">
                        <h5 class="mb-1">Applicant Directory</h5>
                        <small>Manage applicants</small>
                    </div>
                    <a
                        href="{{ route('job-postings.applicants', ['view' => ($view ?? 'active') === 'history' ? 'active' : 'history']) }}"
                        class="btn applicant-view-toggle__link"
                        aria-label="{{ ($view ?? 'active') === 'history' ? 'Switch to active applicants' : 'Switch to applicant history' }}"
                    >
                        <i
                            class="{{ ($view ?? 'active') === 'history' ? 'cil-user-follow' : 'cil-history' }}"
                        ></i>
                        <span
                            >{{ ($view ?? 'active') === 'history' ? 'View Active' : 'View History' }}</span
                        >
                    </a>
                </div>
            </x-slot:header>
            <x-slot:controls>
                <x-ui.table-toolbar
                    as="div"
                    class="job-posting-applicants-toolbar"
                >
                    <div
                        class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                    >
                        <label
                            class="form-label"
                            for="jobPostingApplicantsSearchInput"
                            >Search</label
                        >
                        <input
                            id="jobPostingApplicantsSearchInput"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Search"
                        />
                    </div>
                    <div
                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                    >
                        <x-ui.button
                            type="button"
                            variant="primary"
                            size="sm"
                            id="jobPostingApplicantsApply"
                        >
                            Apply</x-ui.button
                        >
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>
            <table
                class="table table-hover mb-0 align-middle hrms-list-table hrms-table"
            >
                <thead class="bg-light text-uppercase small font-weight-bold">
                    <tr>
                        <th class="pl-4 py-3">Applicant</th>
                        <th class="py-3">Applied For</th>
                        <th class="py-3">Contact</th>
                        <th class="py-3 text-center">Submitted</th>
                        <th class="py-3 text-center">
                            {{ ($view ?? 'active') === 'history' ? 'Status' : 'Actions' }}
                        </th>
                    </tr>
                </thead>
                <tbody id="jobPostingApplicantsTableBody">
                    @foreach ($pendingApplicantApprovals as $approval)
                        @php
                            $applicant = $approval->subject;
                            if (!$applicant) {
                                continue;
                            }
                            $jobTitle = ucfirst($applicant->jobPosting?->position?->position ?? $applicant->jobPosting?->title ?? 'Application');
                            $genderLabel = $applicant->gender ? ucfirst($applicant->gender) : 'N/A';
                            $birthdayLabel = $applicant->birthday ? \Illuminate\Support\Carbon::parse($applicant->birthday)->format('M d, Y') : 'N/A';
                            $subject = rawurlencode('Application Update: ' . $jobTitle);
                            $body = rawurlencode("Good day,\n\nStatus update regarding the application for " . $jobTitle . ".\n\nRegards.");
                            $gmailUrl = 'https://mail.google.com/mail/?view=cm&fs=1&to=' . rawurlencode($applicant->email) . '&su=' . $subject . '&body=' . $body;
                            $yahooUrl = 'https://compose.mail.yahoo.com/?to=' . rawurlencode($applicant->email) . '&subj=' . $subject . '&body=' . $body;
                            $buildStorageUrl = function (?string $path): string {
                                $path = str_replace('\\', '/', trim((string) $path, '/'));
                                if ($path === '') {
                                    return '';
                                }

                                $parts = explode('/', $path);
                                if (($parts[0] ?? '') === 'storage') {
                                    array_shift($parts);
                                }
                                if (count($parts) < 3) {
                                    return '';
                                }

                                return route('storage.file', [
                                    'folder' => $parts[0],
                                    'subfolder' => $parts[1],
                                    'filename' => implode('/', array_slice($parts, 2)),
                                ], false);
                            };
                            $letterUrl = $buildStorageUrl($applicant->application_letter_path);
                            $resumeUrl = $buildStorageUrl($applicant->resume_path);
                            $transcriptUrl = $buildStorageUrl($applicant->transcript_path);
                        @endphp
                        <tr class="table-warning" data-search="{{ strtolower(trim($applicant->full_name . ' ' . $jobTitle . ' ' . ($applicant->email ?? ''))) }}">
                            <td class="pl-4 align-middle">
                                <div class="font-weight-bold text-dark">
                                    {{ $applicant->full_name }}
                                </div>
                                <div class="text-muted small">
                                    {{ $approval->actionLabel() }} by {{ trim(($approval->requester?->employee?->first_name ?? '') . ' ' . ($approval->requester?->employee?->last_name ?? '')) ?: ($approval->requester?->name ?? 'Unknown user') }}
                                </div>
                                <div class="text-warning small">Awaiting HR review</div>
                            </td>
                            <td class="align-middle">
                                <div class="font-weight-bold text-dark">
                                    {{ $jobTitle }}
                                </div>
                                <div class="text-muted small">
                                    {{ $applicant->jobPosting?->department?->department ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="align-middle">
                                <div>
                                    <i class="cil-envelope-open mr-1 text-muted"></i>
                                    {{ $applicant->email }}
                                </div>
                                <div class="text-muted small">
                                    <i class="cil-phone mr-1"></i>
                                    {{ $applicant->phone ?: 'N/A' }}
                                </div>
                            </td>
                            <td class="align-middle text-center text-muted small">
                                {{ $approval->created_at?->format('M d, Y h:i A') }}
                            </td>
                            <td class="align-middle text-center">
                                <div class="crud-actions justify-content-center flex-wrap">
                                    <x-ui.button
                                        type="documents"
                                        size="sm"
                                        class="applicant-docs-trigger"
                                        data-toggle="modal"
                                        data-target="#applicantDocumentsModal"
                                        data-applicant="{{ $applicant->full_name }}"
                                        data-job-title="{{ $jobTitle }}"
                                        data-email="{{ $applicant->email }}"
                                        data-gender="{{ $genderLabel }}"
                                        data-birthday="{{ $birthdayLabel }}"
                                        data-address="{{ $applicant->address ?: 'N/A' }}"
                                        data-gmail-url="{{ $gmailUrl }}"
                                        data-yahoo-url="{{ $yahooUrl }}"
                                        data-letter-url="{{ $letterUrl }}"
                                        data-resume-url="{{ $resumeUrl }}"
                                        data-transcript-url="{{ $transcriptUrl }}"
                                        aria-label="View Applicant Documents"
                                        title="View Applicant Documents"
                                    />
                                    <form
                                        method="POST"
                                        action="{{ route('job-postings.approvals.approve', $approval) }}"
                                        class="d-inline"
                                    >
                                        @csrf
                                        <x-ui.button
                                            type="submit"
                                            variant="success"
                                            size="sm"
                                            aria-label="Approve Applicant Request"
                                            title="Approve Applicant Request"
                                        >
                                            Approve
                                        </x-ui.button>
                                    </form>
                                    <form
                                        method="POST"
                                        action="{{ route('job-postings.approvals.reject', $approval) }}"
                                        class="d-inline"
                                    >
                                        @csrf
                                        <x-ui.button
                                            type="submit"
                                            variant="danger"
                                            size="sm"
                                            aria-label="Decline Applicant Request"
                                            title="Decline Applicant Request"
                                        >
                                            Decline
                                        </x-ui.button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @forelse ($applicants as $applicant)
                        @php
                        $jobTitle = ucfirst($applicant->jobPosting?->position?->position ?? $applicant->jobPosting?->title ?? 'Application');
                        $genderLabel = $applicant->gender ? ucfirst($applicant->gender) : 'N/A';
                        $birthdayLabel = $applicant->birthday ? \Illuminate\Support\Carbon::parse($applicant->birthday)->format('M d, Y') : 'N/A';
                        $subject = rawurlencode('Application Update: ' . $jobTitle);
                        $body = rawurlencode("Good day,\n\nStatus update regarding the application for " . $jobTitle . ".\n\nRegards.");
                        $gmailUrl = 'https://mail.google.com/mail/?view=cm&fs=1&to=' . rawurlencode($applicant->email) . '&su=' . $subject . '&body=' . $body;
                        $yahooUrl = 'https://compose.mail.yahoo.com/?to=' . rawurlencode($applicant->email) . '&subj=' . $subject . '&body=' . $body;
                        $buildStorageUrl = function (?string $path): string {
                            $path = str_replace('\\', '/', trim((string) $path, '/'));
                            if ($path === '') {
                                return '';
                            }

                            $parts = explode('/', $path);
                            if (($parts[0] ?? '') === 'storage') {
                                array_shift($parts);
                            }
                            if (count($parts) < 3) {
                                return '';
                            }

                            $folder = $parts[0];
                            $subfolder = $parts[1];
                            $filename = implode('/', array_slice($parts, 2));

                            return route('storage.file', [
                                'folder' => $folder,
                                'subfolder' => $subfolder,
                                'filename' => $filename,
                            ], false);
                        };
                        $letterUrl = $buildStorageUrl($applicant->application_letter_path);
                        $resumeUrl = $buildStorageUrl($applicant->resume_path);
                        $transcriptUrl = $buildStorageUrl($applicant->transcript_path);
                        $status = strtolower(trim((string) ($applicant->status ?? 'submitted')));
                        if ($status === '') {
                            $status = 'submitted';
                        }
                        $isHired = $status === 'hired';
                        $accountStatus = strtolower((string) ($applicant->account_status ?? ($isHired ? 'active' : 'inactive')));
                        $isInactive = $accountStatus !== 'active';
                        $postingRequired = max((int) ($applicant->jobPosting->required_headcount ?? 1), 1);
                        $postingFulfilled = (int) ($applicant->jobPosting->hired_count ?? 0);
                        $postingFull = $postingFulfilled >= $postingRequired;
                    @endphp
                        <tr
                            data-search="{{ strtolower(trim($applicant->full_name . ' ' . $jobTitle . ' ' . ($applicant->email ?? ''))) }}"
                        >
                            <td class="pl-4 align-middle">
                                <div class="font-weight-bold text-dark">
                                    {{ $applicant->full_name }}
                                </div>
                                <div class="text-muted small">
                                    <i class="cil-people mr-1"></i
                                    >{{ $genderLabel }}
                                    <span class="mx-1">|</span>
                                    <i class="cil-calendar mr-1"></i
                                    >{{ $birthdayLabel }}
                                </div>
                                @if ($applicant->message)
                                    <div class="text-muted small">
                                        {{ \Illuminate\Support\Str::limit($applicant->message, 90) }}
                                    </div>
                                @endif
                            </td>
                            <td class="align-middle">
                                <div class="font-weight-bold text-dark">
                                    {{ ucfirst($applicant->jobPosting?->position?->position ?? $applicant->jobPosting?->title ?? 'N/A') }}
                                </div>
                                <div class="text-muted small">
                                    {{ $applicant->jobPosting?->department?->department ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="align-middle">
                                <div>
                                    <i
                                        class="cil-envelope-open mr-1 text-muted"
                                    ></i>
                                    {{ $applicant->email }}
                                </div>
                                <div class="text-muted small">
                                    <i class="cil-phone mr-1"></i>
                                    {{ $applicant->phone ?: 'N/A' }}
                                </div>
                                <div class="text-muted small">
                                    <i class="cil-location-pin mr-1"></i>
                                    {{ $applicant->address ?: 'N/A' }}
                                </div>
                            </td>
                            <td
                                class="align-middle text-center text-muted small"
                            >
                                {{ $applicant->created_at?->format('M d, Y h:i A') }}
                            </td>
                            <td class="align-middle text-center">
                                @if (($view ?? 'active') === 'history')
                                    @php
                                    $historyBadgeClass = match ($status) {
                                        'hired', 'completed' => 'badge-success',
                                        'archived' => 'badge-secondary',
                                        default => 'badge-light',
                                    };
                                    $historyLabel = $status === 'hired' ? 'Completed' : ucfirst($status);
                                @endphp
                                    <div class="crud-actions justify-content-center">
                                        <x-ui.button
                                            type="documents"
                                            size="sm"
                                            class="applicant-docs-trigger"
                                            data-toggle="modal"
                                            data-target="#applicantDocumentsModal"
                                            data-applicant="{{ $applicant->full_name }}"
                                            data-job-title="{{ $jobTitle }}"
                                            data-email="{{ $applicant->email }}"
                                            data-gender="{{ $genderLabel }}"
                                            data-birthday="{{ $birthdayLabel }}"
                                            data-address="{{ $applicant->address ?: 'N/A' }}"
                                            data-gmail-url="{{ $gmailUrl }}"
                                            data-yahoo-url="{{ $yahooUrl }}"
                                            data-letter-url="{{ $letterUrl }}"
                                            data-resume-url="{{ $resumeUrl }}"
                                            data-transcript-url="{{ $transcriptUrl }}"
                                            aria-label="View Applicant Documents"
                                            title="View Applicant Documents"
                                        />
                                    </div>
                                    <div class="mt-2">
                                        <span
                                            class="badge {{ $historyBadgeClass }} px-3 py-2 text-uppercase"
                                        >
                                            {{ $historyLabel }}
                                        </span>
                                    </div>
                                @else
                                    <div
                                        class="crud-actions justify-content-center"
                                    >
                                        <x-ui.button
                                            type="documents"
                                            size="sm"
                                            class="applicant-docs-trigger"
                                            data-toggle="modal"
                                            data-target="#applicantDocumentsModal"
                                            data-applicant="{{ $applicant->full_name }}"
                                            data-job-title="{{ $jobTitle }}"
                                            data-email="{{ $applicant->email }}"
                                            data-gender="{{ $genderLabel }}"
                                            data-birthday="{{ $birthdayLabel }}"
                                            data-address="{{ $applicant->address ?: 'N/A' }}"
                                            data-gmail-url="{{ $gmailUrl }}"
                                            data-yahoo-url="{{ $yahooUrl }}"
                                            data-letter-url="{{ $letterUrl }}"
                                            data-resume-url="{{ $resumeUrl }}"
                                            data-transcript-url="{{ $transcriptUrl }}"
                                            aria-label="View Applicant Documents"
                                            title="View Applicant Documents"
                                        />
                                        @if (!$isHired && $status !== 'archived')
                                            @if ($postingFull)
                                                <x-ui.button
                                                    type="approve"
                                                    size="sm"
                                                    disabled
                                                    aria-label="Complete Applicant Unavailable"
                                                    title="Vacancy already filled"
                                                />
                                            @else
                                                <form
                                                    method="POST"
                                                    action="{{ route('job-postings.applicants.complete', $applicant) }}"
                                                    class="d-inline"
                                                >
                                                    @csrf
                                                    <x-ui.button
                                                        type="submit"
                                                        variant="approve"
                                                        size="sm"
                                                        aria-label="Complete Applicant"
                                                        title="Complete Applicant"
                                                    />
                                                </form>
                                            @endif
                                        @endif
                                        @if (!$isHired && $status !== 'archived')
                                            <form
                                                method="POST"
                                                action="{{ route('job-postings.applicants.archive', $applicant) }}"
                                                class="d-inline"
                                            >
                                                @csrf
                                                <x-ui.button
                                                    type="submit"
                                                    variant="archive"
                                                    size="sm"
                                                    aria-label="Archive Applicant"
                                                    title="Archive Applicant"
                                                />
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @if ($pendingApplicantApprovals->isEmpty())
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    No applicants found.
                                </td>
                            </tr>
                        @endif
                    @endforelse
                </tbody>
            </table>

            <x-slot:footer>
                {{ $applicants->links() }}
            </x-slot:footer>
        </x-ui.table-card>
    </div>
    <x-modal
        id="applicantDocumentsModal"
        title="Applicant Documents"
        subtitle="Review submitted applicant files and contact information."
        size="lg"
    >
        <x-slot:body>
            <div class="px-3 pt-2 pb-1 text-muted small border-bottom">
                <div>
                    <span id="applicantDocsName">Applicant</span> -
                    <span id="applicantDocsJob">Job Posting</span>
                </div>
                <div>
                    <span id="applicantDocsGender">N/A</span>
                    <span class="mx-1">|</span>
                    <span id="applicantDocsBirthday">N/A</span>
                </div>
                <div id="applicantDocsAddress">Address: N/A</div>
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 0.75rem">
                <div class="d-flex flex-wrap" style="gap: 0.5rem">
                    <button
                        type="button"
                        id="applicantDocLetterBtn"
                        class="btn btn-sm btn-outline-secondary"
                    >
                        <i class="cil-description text-primary mr-1"></i>
                        Application Letter
                    </button>
                    <button
                        type="button"
                        id="applicantDocResumeBtn"
                        class="btn btn-sm btn-outline-secondary"
                    >
                        <i class="cil-file text-primary mr-1"></i> Resume
                    </button>
                    <button
                        type="button"
                        id="applicantDocTranscriptBtn"
                        class="btn btn-sm btn-outline-secondary"
                    >
                        <i class="cil-description text-primary mr-1"></i>
                        Transcript
                    </button>
                </div>
                <div class="d-flex align-items-center justify-content-end ml-auto">
                    <a
                        id="applicantMailAction"
                        href="#"
                        target="_blank"
                        rel="noopener"
                        class="btn btn-sm btn-outline-secondary d-none"
                    >
                        <i class="cil-envelope-open mr-1 text-primary"></i>
                        Email
                    </a>
                </div>
            </div>
            <div class="border rounded overflow-hidden">
                <div class="px-3 py-2 border-bottom text-muted small">
                    <span id="applicantDocPreviewTitle"
                        >Select a document to preview.</span
                    >
                </div>
                <div
                    id="applicantDocPreviewEmpty"
                    class="text-center text-muted small py-5"
                >
                    Document preview will appear here.
                </div>
                <iframe
                    id="applicantDocPreviewFrame"
                    title="Applicant document preview"
                    style="
                        width: 100%;
                        height: 65vh;
                        border: 0;
                        display: none;
                    "
                ></iframe>
            </div>
        </x-slot:body>
    </x-modal>
@endsection
