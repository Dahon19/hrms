<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Northeastern College | HRMS Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link
        rel="icon"
        type="image/webp"
        href="{{ asset('assets/img/Northeastern College.webp') }}"
    />
    <link
        rel="shortcut icon"
        type="image/webp"
        href="{{ asset('assets/img/Northeastern College.webp') }}"
    />
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    />
    @vite (['resources/css/landing.css', 'resources/css/job-portal.css', 'resources/css/emerald-theme.css', 'resources/css/toasts.css', 'resources/js/job-portal.js', 'resources/js/flash-toasts.js'])
</head>
<body>
    @php
        $openingsCount = $postings->count();
        $heroTypewriterPhrases = [
            'recruitment workflows',
            'attendance oversight',
            'performance reviews',
        ];
    @endphp
    <x-toast />
    <div
        class="site-shell landing-shell"
        id="portalPage"
        data-page="portal.landing"
    >
        <header class="topbar" id="topbar">
            <div class="topbar-left">
                <a
                    class="brand"
                    href="{{ route('landing') }}"
                    aria-label="Return to landing page"
                >
                    <span class="brand-mark brand-mark-logo">
                        <img
                            src="{{ asset('assets/img/Northeastern College.webp') }}"
                            data-fallback="{{ asset('assets/dist/img/AdminLTELogo.png') }}"
                            alt="Northeastern College Logo"
                            onerror="
                                this.onerror = null;
                                this.src = this.dataset.fallback;
                            "
                        />
                    </span>
                    <span class="brand-name">Northeastern College</span>
                </a>
                <button
                    type="button"
                    class="topbar-menu-toggle"
                    id="portalMenuToggle"
                    aria-controls="portalNav"
                    aria-expanded="false"
                    aria-label="Toggle site navigation"
                >
                    <span></span> <span></span> <span></span>
                </button>
                <nav class="topbar-nav" id="portalNav">
                    <a href="#home" class="is-active">Home</a>
                    <a href="#features">Features</a>
                    <a href="#modules">Modules</a>
                    <a href="#how-it-works">How It Works</a>
                    <a href="#about">About</a> <a href="#contact">Contact</a>
                </nav>
            </div>
            <nav class="topbar-actions">
                <a class="btn btn-primary" href="{{ route('login') }}">Login</a>
            </nav>
        </header>
        <main class="landing-main">
            <section id="home" class="portal-hero-section section-reveal">
                <div class="hero-parallax" aria-hidden="true">
                    <span
                        class="parallax-shape shape-a"
                        data-parallax
                        data-speed="0.12"
                    ></span>
                    <span
                        class="parallax-shape shape-b"
                        data-parallax
                        data-speed="0.2"
                    ></span>
                    <span
                        class="parallax-shape shape-c"
                        data-parallax
                        data-speed="0.08"
                    ></span>
                </div>
                <div class="portal-hero-grid">
                    <div class="portal-hero-copy">
                        <p class="eyebrow">Human Resource Management System</p>
                        <h1>
                            Build Careers and Performance Excellence with
                            <span>Northeastern College HRMS</span>
                        </h1>
                        <p class="hero-typed-line">
                            Built for
                            <span
                                class="hero-typewriter"
                                id="portalHeroTypewriter"
                                data-phrases='@json($heroTypewriterPhrases)'
                                aria-live="polite"
                            >recruitment workflows</span>
                        </p>
                        <p>A centralized system for recruitment, employee records, leave processes, attendance monitoring, SPMS evaluation, and Recognition &amp; Rewards operations.</p>
                        <div class="hero-cta">
                            <a href="#recruitment" class="btn btn-primary"
                                >View Open Roles</a
                            >
                            <a href="#features" class="btn btn-light"
                                >Explore Features</a
                            >
                        </div>
                    </div>
                    <aside class="hero-panel" data-parallax data-speed="0.14">
                        <h3>Application Guide</h3>
                        <div class="hero-kpi-grid">
                            <article>
                                <span>Open Vacancies</span>
                                <strong>{{ $openingsCount }}</strong>
                            </article>
                            <article>
                                <span>Submission Method</span>
                                <strong>Online</strong>
                            </article>
                            <article>
                                <span>Required Files</span>
                                <strong>2-3 Docs</strong>
                            </article>
                            <article>
                                <span>Status Updates</span>
                                <strong>Email</strong>
                            </article>
                        </div>
                    </aside>
                </div>
            </section>
            <section id="features" class="landing-section section-reveal">
                <div class="section-parallax" aria-hidden="true">
                    <span
                        class="parallax-shape shape-c"
                        data-parallax
                        data-speed="0.06"
                    ></span>
                </div>
                <div class="section-heading">
                    <p class="eyebrow">Platform Features</p>
                    <h2>Enterprise-Ready HR Experience</h2>
                </div>
                <div class="feature-grid">
                    <article class="feature-card">
                        <i class="cil-badge"></i>
                        <h3>Employee Management</h3>
                        <p>Structured employee profiles, records, and lifecycle updates from one workspace.</p>
                    </article>
                    <article class="feature-card">
                        <i class="cil-user-follow"></i>
                        <h3>Recruitment Tracking</h3>
                        <p>Publish vacancies, track applicants, and monitor each stage of hiring progress.</p>
                    </article>
                    <article class="feature-card">
                        <i class="cil-calendar"></i>
                        <h3>Leave Requests</h3>
                        <p>Digital leave filing, approval workflows, and balances with clear status visibility.</p>
                    </article>
                    <article class="feature-card">
                        <i class="cil-clock"></i>
                        <h3>Attendance Monitoring</h3>
                        <p>Attendance logs, KPI trends, and punctuality insights for workforce compliance.</p>
                    </article>
                    <article class="feature-card">
                        <i class="cil-clipboard"></i>
                        <h3>SPMS Evaluation</h3>
                        <p>Weighted criteria scoring and cycle-driven performance evaluation for supervisors.</p>
                    </article>
                    <article class="feature-card">
                        <i class="cil-star"></i>
                        <h3>Recognition &amp; Rewards</h3>
                        <p>Identify and reward tenure, attendance, and performance excellence consistently.</p>
                    </article>
                </div>
            </section>
            <section id="modules" class="landing-section section-reveal">
                <div class="section-parallax" aria-hidden="true">
                    <span
                        class="parallax-shape shape-b"
                        data-parallax
                        data-speed="0.04"
                    ></span>
                </div>
                <div class="section-heading">
                    <p class="eyebrow">Core Modules</p>
                    <h2>Interactive Module Overview</h2>
                </div>
                <div class="module-grid">
                    <article class="module-card">
                        <i class="cil-bullhorn"></i>
                        <h3>Recruitment</h3>
                        <p>Post vacancies, receive applications, and track candidate screening progress.</p>
                    </article>
                    <article class="module-card">
                        <i class="cil-user"></i>
                        <h3>Employee Management</h3>
                        <p>Maintain employee profiles, assignments, and record lifecycle updates.</p>
                    </article>
                    <article class="module-card">
                        <i class="cil-calendar-check"></i>
                        <h3>Leave Management</h3>
                        <p>Automate leave requests, approvals, balances, and policy visibility.</p>
                    </article>
                    <article class="module-card">
                        <i class="cil-clock"></i>
                        <h3>Attendance</h3>
                        <p>Track attendance logs, punctuality KPIs, and operational attendance trends.</p>
                    </article>
                    <article class="module-card">
                        <i class="cil-clipboard"></i>
                        <h3>SPMS Evaluation</h3>
                        <p>Manage weighted cycle evaluations from setup to finalized results and cycle closure.</p>
                    </article>
                    <article class="module-card">
                        <i class="cil-star"></i>
                        <h3>Recognition &amp; Rewards</h3>
                        <p>Recognize tenure, attendance, and performance-based achievements.</p>
                    </article>
                </div>
            </section>
            <section
                id="how-it-works"
                class="landing-section how-section section-reveal"
            >
                <div class="section-parallax" aria-hidden="true">
                    <span
                        class="parallax-shape shape-a"
                        data-parallax
                        data-speed="0.03"
                    ></span>
                </div>
                <div class="section-heading">
                    <p class="eyebrow">How It Works</p>
                    <h2>4-Step HRMS Workflow</h2>
                </div>
                <div class="flow-grid">
                    <article class="flow-card">
                        <span>01</span>
                        <h3>Employee Onboarding</h3>
                        <p>Register employee records, assign departments, and activate HR profiles.</p>
                    </article>
                    <article class="flow-card">
                        <span>02</span>
                        <h3>Attendance Tracking</h3>
                        <p>Capture attendance logs and monitor punctuality metrics each cycle period.</p>
                    </article>
                    <article class="flow-card">
                        <span>03</span>
                        <h3>Performance Evaluation</h3>
                        <p>Supervisors complete SPMS weighted scoring and finalize evaluations per employee.</p>
                    </article>
                    <article class="flow-card">
                        <span>04</span>
                        <h3>Rewards and Recognition</h3>
                        <p>Generate recognition insights and reward qualified employees using system data.</p>
                    </article>
                </div>
            </section>
            <section
                id="recruitment"
                class="landing-section recruitment-section section-reveal"
            >
                <div class="portal-hero">
                    <p class="eyebrow">Recruitment</p>
                    <h2>Current Opportunities</h2>
                    <p class="hero-copy">Explore vacant positions and submit applications through the official HRMS career portal.</p>
                </div>
                <div class="jobs-container">
                    @forelse ($postings as $job)
                        @php
                            $isActiveApplicationModal = $errors->any() && (string) old('job_posting_id') === (string) $job->id;
                        @endphp
                        <div class="job-card">
                            <div class="job-info">
                                <span
                                    class="job-type-badge"
                                    >{{ $job->employment_type }}</span
                                >
                                <h3>
                                    {{ ucfirst($job->position?->position ?? $job->title) }}
                                </h3>
                                <div class="job-meta">
                                    <span
                                        ><i class="cil-building"></i>
                                        {{ $job->department?->department ?? 'N/A' }}</span
                                    >
                                    @if ($job->closing_date)
                                        <span
                                            ><i class="cil-calendar"></i> Apply
                                            by {{ $job->closing_date->format('M d, Y') }}</span
                                        >
                                    @endif
                                </div>
                            </div>
                            <div class="job-action">
                                <button
                                    type="button"
                                    class="btn btn-outline"
                                    data-toggle="modal"
                                    data-target="#applyModal{{ $job->id }}"
                                >
                                    View &amp; Apply
                                </button>
                            </div>
                        </div>
                        <div
                            class="modal fade job-apply-modal"
                            id="applyModal{{ $job->id }}"
                            data-auto-open="{{ $isActiveApplicationModal ? '1' : '0' }}"
                            tabindex="-1"
                            role="dialog"
                            aria-hidden="true"
                            aria-labelledby="applyModal{{ $job->id }}Label"
                        >
                            <div
                                class="modal-dialog modal-lg modal-dialog-centered"
                                role="document"
                            >
                                <div class="modal-content">
                                    <form
                                        action="{{ route('jobs.apply', $job->id) }}"
                                        method="POST"
                                        enctype="multipart/form-data"
                                        class="job-apply-form"
                                        data-keep-submit-enabled="1"
                                        novalidate
                                    >
                                        @csrf
                                        <input type="hidden" name="job_posting_id" value="{{ $job->id }}" />
                                        <div class="modal-header">
                                            <div>
                                                <h5
                                                    class="job-apply-title"
                                                    id="applyModal{{ $job->id }}Label"
                                                >
                                                    Apply for {{ ucfirst($job->position?->position ?? $job->title) }}
                                                </h5>
                                                <p class="job-apply-subtitle">Application Form</p>
                                            </div>
                                            <button
                                                type="button"
                                                class="job-apply-close"
                                                data-dismiss="modal"
                                                aria-label="Close"
                                            >
                                                <i
                                                    class="cil-x"
                                                    aria-hidden="true"
                                                ></i>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            @if ($isActiveApplicationModal)
                                                <div class="alert alert-danger" role="alert">
                                                    <div class="font-weight-bold mb-1">Please correct the following before resubmitting.</div>
                                                    <ul class="mb-0 pl-3">
                                                        @foreach ($errors->all() as $error)
                                                            <li>{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                            <section
                                                class="job-apply-section job-overview-section"
                                                aria-label="Job Overview"
                                            >
                                                <h6 class="section-title mb-2">
                                                    Job Description
                                                </h6>
                                                <div class="job-overview-block">
                                                    {{ $job->description ?: 'No description provided.' }}
                                                </div>
                                                <hr
                                                    class="job-overview-divider"
                                                />
                                                <h6 class="section-title mb-2">
                                                    Duties and Requirements
                                                </h6>
                                                <div class="job-overview-block">
                                                    {{ $job->requirements ?: 'Refer to the required documents and details below.' }}
                                                </div>
                                            </section>
                                            <section
                                                class="job-apply-section"
                                                aria-label="Applicant Information"
                                            >
                                                <h6 class="section-title">
                                                    Applicant Information
                                                </h6>
                                                <div class="form-row">
                                                    <div
                                                        class="col-md-6 form-group mb-4"
                                                    >
                                                        <label
                                                            class="form-label"
                                                            for="full_name_{{ $job->id }}"
                                                            >Full Name
                                                            <span
                                                                class="text-danger"
                                                                >*</span
                                                            ></label
                                                        >
                                                        <input
                                                            type="text"
                                                            id="full_name_{{ $job->id }}"
                                                            name="full_name"
                                                            class="form-control"
                                                            value="{{ $isActiveApplicationModal ? old('full_name') : '' }}"
                                                            pattern="[A-Za-z]+(?:[ .'-][A-Za-z]+)*"
                                                            data-validation-message="Full name can contain letters, spaces, apostrophes, periods, and hyphens only."
                                                            maxlength="255"
                                                            required
                                                            placeholder="Juan Dela Cruz"
                                                            autocomplete="name"
                                                        />
                                                        </div>
                                                    <div
                                                        class="col-md-6 form-group mb-4"
                                                    >
                                                        <label
                                                            class="form-label"
                                                            for="email_{{ $job->id }}"
                                                            >Email Address
                                                            <span
                                                                class="text-danger"
                                                                >*</span
                                                            ></label
                                                        >
                                                        <input
                                                            type="email"
                                                            id="email_{{ $job->id }}"
                                                            name="email"
                                                            class="form-control"
                                                            value="{{ $isActiveApplicationModal ? old('email') : '' }}"
                                                            required
                                                            placeholder="yourname@gmail.com"
                                                            autocomplete="email"
                                                            pattern="^[A-Za-z0-9._%+-]+@(gmail\.com|yahoo\.com)$"
                                                            title="Use a Gmail or Yahoo email address only."
                                                        />
                                                    </div>
                                                </div>
                                                <div class="form-row">
                                                    <div
                                                        class="col-md-4 form-group mb-4"
                                                    >
                                                        <label
                                                            class="form-label"
                                                            for="gender_{{ $job->id }}"
                                                            >Gender
                                                            <span
                                                                class="text-danger"
                                                                >*</span
                                                            ></label
                                                        >
                                                        <select
                                                            id="gender_{{ $job->id }}"
                                                            name="gender"
                                                            class="form-control"
                                                            required
                                                        >
                                                            <option value="">
                                                                -- Select Gender
                                                                --
                                                            </option>
                                                            <option
                                                                value="male"
                                                                {{ $isActiveApplicationModal && old('gender') === 'male' ? 'selected' : '' }}
                                                            >
                                                                Male
                                                            </option>
                                                            <option
                                                                value="female"
                                                                {{ $isActiveApplicationModal && old('gender') === 'female' ? 'selected' : '' }}
                                                            >
                                                                Female
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div
                                                        class="col-md-4 form-group mb-4"
                                                    >
                                                        <label
                                                            class="form-label"
                                                            for="birthday_{{ $job->id }}"
                                                            >Birthday
                                                            <span
                                                                class="text-danger"
                                                                >*</span
                                                            ></label
                                                        >
                                                        <input
                                                            type="date"
                                                            id="birthday_{{ $job->id }}"
                                                            name="birthday"
                                                            class="form-control"
                                                            value="{{ $isActiveApplicationModal ? old('birthday') : '' }}"
                                                            required
                                                            max="{{ now()->toDateString() }}"
                                                        />
                                                    </div>
                                                    <div
                                                        class="col-md-4 form-group mb-4"
                                                    >
                                                        <label
                                                            class="form-label"
                                                            for="phone_{{ $job->id }}"
                                                            >Phone Number</label
                                                        >
                                                        <input
                                                            type="tel"
                                                            id="phone_{{ $job->id }}"
                                                            name="phone"
                                                            class="form-control"
                                                            value="{{ $isActiveApplicationModal ? old('phone') : '' }}"
                                                            autocomplete="tel"
                                                            inputmode="numeric"
                                                            pattern="(?:\+63|0)9\d{9}"
                                                            maxlength="13"
                                                            title="Use a valid Philippine mobile number such as 09171234567 or +639171234567."
                                                        />
                                                        </div>
                                                    <div
                                                        class="col-md-12 form-group mb-4"
                                                    >
                                                        <label
                                                            class="form-label"
                                                            for="address_{{ $job->id }}"
                                                            >Address
                                                            <span
                                                                class="text-danger"
                                                                >*</span
                                                            ></label
                                                        >
                                                        <input
                                                            id="address_{{ $job->id }}"
                                                            name="address"
                                                            class="form-control"
                                                            rows="2"
                                                            maxlength="1000"
                                                            required
                                                            placeholder="Barangay, Municipality, Province"
                                                            autocomplete="street-address"
                                                        {{ $isActiveApplicationModal ? old('address') : '' }}/>
                                                        </div>
                                                </div>
                                            </section>
                                            <section
                                                class="job-apply-section"
                                                aria-label="Upload Documents"
                                            >
                                                <h6 class="section-title">
                                                    Upload Documents
                                                </h6>
                                                <div class="form-row">
                                                    <div
                                                        class="col-md-4 form-group mb-4"
                                                    >
                                                        <div
                                                            class="upload-card"
                                                        >
                                                            <div
                                                                class="upload-head"
                                                            >
                                                                <i
                                                                    class="cil-description"
                                                                ></i>
                                                                <span
                                                                    >Application
                                                                    Letter
                                                                    <span
                                                                        class="text-danger"
                                                                        >*</span
                                                                    ></span
                                                                >
                                                            </div>
                                                            <input
                                                                type="file"
                                                                id="application_letter_{{ $job->id }}"
                                                                name="application_letter"
                                                                class="filepond"
                                                                required
                                                                accept=".pdf,.doc,.docx"
                                                                data-accepted-file-types=".pdf,.doc,.docx"
                                                                data-max-file-size="5MB"
                                                                data-filepond-label-idle='Drop application letter here or <span class="filepond--label-action">Browse</span>'
                                                            />
                                                            <p class="upload-constraint mb-0">PDF/DOC/DOCX, max 5MB</p>
                                                        </div>
                                                    </div>
                                                    <div
                                                        class="col-md-4 form-group mb-4"
                                                    >
                                                        <div
                                                            class="upload-card"
                                                        >
                                                            <div
                                                                class="upload-head"
                                                            >
                                                                <i
                                                                    class="cil-file"
                                                                ></i>
                                                                <span
                                                                    >Resume / CV
                                                                    <span
                                                                        class="text-danger"
                                                                        >*</span
                                                                    ></span
                                                                >
                                                            </div>
                                                            <input
                                                                type="file"
                                                                id="resume_{{ $job->id }}"
                                                                name="resume"
                                                                class="filepond"
                                                                required
                                                                accept=".pdf,.doc,.docx"
                                                                data-accepted-file-types=".pdf,.doc,.docx"
                                                                data-max-file-size="5MB"
                                                                data-filepond-label-idle='Drop resume here or <span class="filepond--label-action">Browse</span>'
                                                            />
                                                            <p class="upload-constraint mb-0">PDF/DOC/DOCX, max 5MB</p>
                                                        </div>
                                                    </div>
                                                    <div
                                                        class="col-md-4 form-group mb-4"
                                                    >
                                                        <div
                                                            class="upload-card"
                                                        >
                                                            <div
                                                                class="upload-head"
                                                            >
                                                                <i
                                                                    class="cil-description"
                                                                ></i>
                                                                <span
                                                                    >Transcript
                                                                    of Records
                                                                    <small
                                                                        class="text-muted"
                                                                        >(Optional)</small
                                                                    ></span
                                                                >
                                                            </div>
                                                            <input
                                                                type="file"
                                                                id="transcript_{{ $job->id }}"
                                                                name="transcript"
                                                                class="filepond"
                                                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                                data-accepted-file-types=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                                data-max-file-size="5MB"
                                                                data-filepond-label-idle='Drop transcript here or <span class="filepond--label-action">Browse</span>'
                                                            />
                                                            <p class="upload-constraint mb-0">PDF/DOC/DOCX/JPG/PNG, max 5MB</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </section>
                                        </div>
                                        <div class="modal-footer">
                                            <button
                                                type="button"
                                                class="btn btn-light px-3"
                                                data-dismiss="modal"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="submit"
                                                class="btn btn-primary job-apply-submit"
                                            >
                                                <span
                                                    class="spinner-border spinner-border-sm"
                                                    role="status"
                                                    aria-hidden="true"
                                                ></span>
                                                <span>Submit Application</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-portal">
                            <i class="cil-search"></i>
                            <h3>No vacancies at the moment</h3>
                            <p class="muted">Check back later or follow official social media channels for updates.</p>
                        </div>
                    @endforelse
                </div>
            </section>
            <section
                id="about"
                class="landing-section about-section section-reveal"
            >
                <div class="section-heading">
                    <p class="eyebrow">About the System</p>
                    <h2>Purpose-Built for Institutional HR Governance</h2>
                </div>
                <div class="about-layout">
                    <p>Northeastern College HRMS consolidates workforce data, approvals, performance management, and recognition programs into a secure, auditable platform. It helps HR teams and leadership make timely, data-informed decisions while keeping employee processes structured and transparent.</p>
                </div>
            </section>
            <section
                id="cta"
                class="landing-section cta-section section-reveal"
            >
                <div class="cta-inner">
                    <div>
                        <p class="eyebrow">Get Started</p>
                        <h2>Access Northeastern College HRMS</h2>
                        <p>Explore the platform, review openings, or contact the HR team for account access.</p>
                    </div>
                    <a href="#contact" class="btn btn-primary">Contact HR</a>
                </div>
            </section>
            <section
                id="contact"
                class="landing-section contact-section section-reveal"
            >
                <div class="section-heading">
                    <p class="eyebrow">Contact</p>
                    <h2>Get in Touch</h2>
                </div>
                <div class="contact-grid">
                    <article>
                        <h3>Recruitment Office</h3>
                        <p>Email: <a href="mailto:careers@northeastern.edu.ph">careers@northeastern.edu.ph</a></p>
                        <p>Office Hours: Monday to Friday, 8:00 AM - 5:00 PM</p>
                    </article>
                    <article>
                        <h3>Quick Links</h3>
                        <p><a href="#home">Home</a></p>
                        <p><a href="#features">Features</a></p>
                        <p><a href="#modules">System Modules</a></p>
                        <p><a href="#how-it-works">How It Works</a></p>
                        <p><a href="#recruitment">Recruitment</a></p>
                        <p><a href="#cta">Get Started</a></p>
                    </article>
                    <article>
                        <h3>Social</h3>
                        <p><a href="#" aria-label="Facebook"><i class="cib-facebook mr-1"></i> Facebook</a></p>
                        <p><a href="#" aria-label="LinkedIn"><i class="cib-linkedin mr-1"></i> LinkedIn</a></p>
                    </article>
                </div>
            </section>
        </main>
        <footer class="portal-footer">
            <span>Northeastern College &copy; {{ date('Y') }}</span>
            <span class="muted">HRMS Careers and Workforce Platform</span>
        </footer>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
