<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CS Form No. 212 Revised 2017 - PDS</title>
    <style>
        @page {
            size: 8.5in 13in;
            margin: 0.6in;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.25;
        }
        .sheet {
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 4px;
        }
        .header h1 {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .header p {
            margin: 2px 0;
            font-size: 9px;
            text-transform: none;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
        }
        .meta td {
            border: 1px solid #000;
            padding: 2px 5px;
            text-transform: uppercase;
            font-size: 9px;
        }
        .pds-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
        }
        .pds-table th,
        .pds-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        .pds-table th {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
        }
        .section-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            text-align: left;
            background: #d9d9d9;
        }
        .label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .value {
            text-transform: uppercase;
            min-height: 13px;
        }
        .small {
            font-size: 8px;
        }
        .center {
            text-align: center;
        }
        .right {
            text-align: right;
        }
        .line {
            display: inline-block;
            width: 100%;
            min-height: 12px;
        }
        .block {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .new-page {
            break-before: page;
            page-break-before: always;
        }
        thead {
            display: table-header-group;
        }
        tfoot {
            display: table-row-group;
        }
        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .declaration {
            font-size: 9px;
            text-align: justify;
        }
        .sign-box {
            height: 46px;
        }
        .note {
            font-size: 8px;
            font-style: italic;
            text-transform: none;
        }
        .tiny {
            font-size: 7px;
            text-transform: none;
        }
        @media print {
            .section-title {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    @php $prefill = $personalInfoDefaults ?? []; $family = $profile->familyBackground; $otherByType = $profile->otherInfos->groupBy('info_type'); $fmtDate = static function ($value) { if (!$value) { return ''; } try { return \Illuminate\Support\Carbon::parse($value)->format('m/d/Y'); } catch (\Throwable $e) { return ''; } }; $fmtMonthYear = static function ($value) { if (!$value) { return ''; } try { return \Illuminate\Support\Carbon::parse($value)->format('m/Y'); } catch (\Throwable $e) { return ''; } }; $upper = static fn ($value) => $value ? strtoupper((string) $value) : ''; $fatherFull = trim(implode(' ', array_filter([ $family?->father_last_name ? $family->father_last_name . ',' : null, $family?->father_first_name, $family?->father_middle_name, $family?->father_name_extension, ]))); $motherFull = trim(implode(' ', array_filter([ $family?->mother_last_name ? $family->mother_last_name . ',' : null, $family?->mother_first_name, $family?->mother_middle_name, ]))); $maxOtherRows = max( $otherByType->get('special_skill', collect())->count(), $otherByType->get('recognition', collect())->count(), $otherByType->get('membership', collect())->count(), 4 );
@endphp
    <div class="sheet">
        <div class="header block">
            <h1>CS Form No. 212</h1>
            <p><strong>Personal Data Sheet</strong></p>
            <p class="small"><em>Revised 2017</em></p>
            <p class="tiny">WARNING: Any misrepresentation made in the Personal Data Sheet and the Work Experience Sheet shall cause the filing of administrative/criminal case/s against the person concerned.</p>
        </div>
        <table class="meta block">
            <tr>
                <td style="width: 100%">
                    <strong>1. CS ID NO.</strong>
                    {{ $upper($employee->employee_id) }}
                </td>
            </tr>
        </table>
        <table class="pds-table block">
            <tr>
                <th colspan="8" class="section-title">
                    I. Personal Information
                </th>
            </tr>
            <tr>
                <td class="label" style="width: 11%">2. Surname</td>
                <td class="value" style="width: 22%">
                    {{ $upper($prefill['last_name'] ?? '') }}
                </td>
                <td class="label" style="width: 11%">First Name</td>
                <td class="value" style="width: 22%">
                    {{ $upper($prefill['first_name'] ?? '') }}
                </td>
                <td class="label" style="width: 11%">Name Ext.</td>
                <td class="value" style="width: 8%">
                    {{ $upper($prefill['name_extension'] ?? '') }}
                </td>
                <td class="label" style="width: 7%">M.I.</td>
                <td class="value" style="width: 8%">
                    {{ $upper(!empty($prefill['middle_name']) ? substr((string) $prefill['middle_name'], 0, 1) : '') }}
                </td>
            </tr>
            <tr>
                <td class="label">3. Date of Birth</td>
                <td class="value">
                    {{ $fmtDate($prefill['birth_date'] ?? null) }}
                </td>
                <td class="label">4. Place of Birth</td>
                <td class="value" colspan="5">
                    {{ $upper($prefill['birth_place'] ?? '') }}
                </td>
            </tr>
            <tr>
                <td class="label">5. Sex</td>
                <td class="value">{{ $upper($prefill['sex'] ?? '') }}</td>
                <td class="label">6. Civil Status</td>
                <td class="value">
                    {{ $upper($prefill['civil_status'] ?? '') }}
                </td>
                <td class="label">7. Citizenship</td>
                <td class="value" colspan="3">
                    {{ $upper($prefill['citizenship'] ?? '') }}
                </td>
            </tr>
            <tr>
                <td class="label">8. Height (m)</td>
                <td class="value">{{ $prefill['height_m'] ?? '' }}</td>
                <td class="label">9. Weight (kg)</td>
                <td class="value">{{ $prefill['weight_kg'] ?? '' }}</td>
                <td class="label">10. Blood Type</td>
                <td class="value">
                    {{ $upper($prefill['blood_type'] ?? '') }}
                </td>
                <td class="label">11. GSIS No.</td>
                <td class="value">{{ $upper($prefill['gsis_no'] ?? '') }}</td>
            </tr>
            <tr>
                <td class="label">12. Pag-IBIG No.</td>
                <td class="value"><span class="line">&nbsp;</span></td>
                <td class="label">13. PhilHealth No.</td>
                <td class="value">
                    {{ $upper($prefill['philhealth_no'] ?? '') }}
                </td>
                <td class="label">14. SSS No.</td>
                <td class="value">{{ $upper($prefill['sss_no'] ?? '') }}</td>
                <td class="label">15. TIN No.</td>
                <td class="value">{{ $upper($prefill['tin_no'] ?? '') }}</td>
            </tr>
            <tr>
                <td class="label">16. Agency Employee No.</td>
                <td class="value">{{ $upper($employee->employee_id) }}</td>
                <td class="label">17. Telephone No.</td>
                <td class="value">
                    {{ $upper($prefill['telephone_no'] ?? '') }}
                </td>
                <td class="label">18. Mobile No.</td>
                <td class="value" colspan="3">
                    {{ $upper($prefill['mobile_no'] ?? '') }}
                </td>
            </tr>
            <tr>
                <td class="label">19. Email Address</td>
                <td class="value" colspan="7">
                    {{ $upper($prefill['email_address'] ?? '') }}
                </td>
            </tr>
            <tr>
                <td class="label">20. Residential Address</td>
                <td class="value" colspan="7">
                    {{ $upper($prefill['residential_address'] ?? '') }}
                </td>
            </tr>
            <tr>
                <td class="label">21. Permanent Address</td>
                <td class="value" colspan="7">
                    {{ $upper($prefill['permanent_address'] ?? '') }}
                </td>
            </tr>
            <tr>
                <td colspan="8" class="note center">
                    FOR ITEMS 22 TO 28, WRITE N/A IF NOT APPLICABLE.
                </td>
            </tr>
        </table>
        <table class="pds-table block">
            <tr>
                <th colspan="8" class="section-title">II. Family Background</th>
            </tr>
            <tr>
                <td class="label" style="width: 13%">22. Spouse Surname</td>
                <td class="value" style="width: 19%">
                    {{ $upper($family?->spouse_last_name) }}
                </td>
                <td class="label" style="width: 13%">First Name</td>
                <td class="value" style="width: 19%">
                    {{ $upper($family?->spouse_first_name) }}
                </td>
                <td class="label" style="width: 13%">Middle Name</td>
                <td class="value" style="width: 13%">
                    {{ $upper($family?->spouse_middle_name) }}
                </td>
                <td class="label" style="width: 10%">Name Ext.</td>
                <td class="value" style="width: 10%">
                    <span class="line">&nbsp;</span>
                </td>
            </tr>
            <tr>
                <td class="label">23. Occupation</td>
                <td class="value">{{ $upper($family?->spouse_occupation) }}</td>
                <td class="label">24. Employer/Business</td>
                <td class="value" colspan="3">
                    {{ $upper($family?->spouse_employer) }}
                </td>
                <td class="label">25. Tel. No.</td>
                <td class="value">{{ $upper($family?->spouse_telephone) }}</td>
            </tr>
            <tr>
                <td class="label">26. Business Address</td>
                <td class="value" colspan="7">
                    {{ $upper($family?->spouse_business_address) }}
                </td>
            </tr>
            <tr>
                <td class="label">27. Father</td>
                <td class="value" colspan="7">{{ $upper($fatherFull) }}</td>
            </tr>
            <tr>
                <td class="label">28. Mother</td>
                <td class="value" colspan="7">{{ $upper($motherFull) }}</td>
            </tr>
            <tr>
                <th colspan="5">
                    Name of Children (Write full name and list all)
                </th>
                <th colspan="3">Date of Birth (mm/dd/yyyy)</th>
            </tr>
            @forelse ($profile->children as $child)
                <tr>
                    <td class="value" colspan="5">
                        {{ $upper($child->full_name) }}
                    </td>
                    <td class="value center" colspan="3">
                        {{ $fmtDate($child->birth_date) }}
                    </td>
                </tr>
            @empty
                @for ($i = 0; $i < 4; $i++)
                    <tr>
                        <td class="value" colspan="5">
                            <span class="line">&nbsp;</span>
                        </td>
                        <td class="value" colspan="3">
                            <span class="line">&nbsp;</span>
                        </td>
                    </tr>
                @endfor
            @endforelse
        </table>
        <table class="pds-table block">
            <thead>
                <tr>
                    <th colspan="8" class="section-title">
                        III. Educational Background
                    </th>
                </tr>
                <tr>
                    <th style="width: 11%">Level</th>
                    <th style="width: 26%">Name of School (Write in full)</th>
                    <th style="width: 18%">
                        Basic Education / Degree / Course
                    </th>
                    <th style="width: 9%">From</th>
                    <th style="width: 9%">To</th>
                    <th style="width: 9%">Highest Level / Units Earned</th>
                    <th style="width: 8%">Year Graduated</th>
                    <th style="width: 10%">
                        Scholarship / Academic Honors Received
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse ($profile->educations as $row)
                    <tr>
                        <td class="value center">
                            {{ $upper($row->education_level) }}
                        </td>
                        <td class="value">{{ $upper($row->school_name) }}</td>
                        <td class="value">{{ $upper($row->degree_course) }}</td>
                        <td class="value center">
                            {{ $fmtMonthYear($row->date_from) }}
                        </td>
                        <td class="value center">
                            {{ $fmtMonthYear($row->date_to) }}
                        </td>
                        <td class="value center">
                            {{ $upper($row->highest_level_units) }}
                        </td>
                        <td class="value center">
                            {{ $upper($row->year_graduated) }}
                        </td>
                        <td class="value">
                            {{ $upper($row->honors_received) }}
                        </td>
                    </tr>
                @empty
                    @for ($i = 0; $i < 5; $i++)
                        <tr>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                        </tr>
                    @endfor
                @endforelse
            </tbody>
        </table>
        <table class="pds-table block">
            <thead>
                <tr>
                    <th colspan="6" class="section-title">
                        IV. Civil Service Eligibility
                    </th>
                </tr>
                <tr>
                    <th style="width: 31%">
                        Career Service / RA 1080 (Board/Bar) / CSC Eligibility
                    </th>
                    <th style="width: 9%">Rating</th>
                    <th style="width: 14%">Date of Examination / Conferment</th>
                    <th style="width: 22%">
                        Place of Examination / Conferment
                    </th>
                    <th style="width: 12%">License Number</th>
                    <th style="width: 12%">Date of Validity</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($profile->civilServiceEligibilities as $row)
                    <tr>
                        <td class="value">
                            {{ $upper($row->eligibility_type) }}
                        </td>
                        <td class="value center">{{ $upper($row->rating) }}</td>
                        <td class="value center">
                            {{ $fmtDate($row->exam_date) }}
                        </td>
                        <td class="value">{{ $upper($row->exam_place) }}</td>
                        <td class="value center">
                            {{ $upper($row->license_number) }}
                        </td>
                        <td class="value center">
                            {{ $fmtDate($row->validity_date) }}
                        </td>
                    </tr>
                @empty
                    @for ($i = 0; $i < 4; $i++)
                        <tr>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                            <td class="value">&nbsp;</td>
                        </tr>
                    @endfor
                @endforelse
            </tbody>
        </table>
        <table class="pds-table block new-page">
            <thead>
                <tr>
                    <th colspan="8" class="section-title">
                        V. Work Experience (Include private employment. Start
                        from the most recent work) Description of duties should
                        be indicated in the attached Work Experience Sheet.
                    </th>
                </tr>
                <tr>
                    <th style="width: 9%">
                        Inclusive Dates<br /><span class="small">From</span>
                    </th>
                    <th style="width: 9%">
                        Inclusive Dates<br /><span class="small">To</span>
                    </th>
                    <th style="width: 22%">
                        Position Title<br /><span class="small"
                            >(Write in full/Do not abbreviate)</span
                        >
                    </th>
                    <th style="width: 22%">
                        Department / Agency / Office / Company<br /><span
                            class="small"
                            >(Write in full/Do not abbreviate)</span
                        >
                    </th>
                    <th style="width: 10%">Monthly Salary</th>
                    <th style="width: 10%">
                        Salary/Job/Pay Grade & Step<br /><span class="small"
                            >(Format "00-0")</span
                        >
                    </th>
                    <th style="width: 10%">Status of Appointment</th>
                    <th style="width: 8%">
                        Gov't Service<br /><span class="small">(Y/N)</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @php $workRows = $profile->workExperiences; $minimumWorkRows = max($workRows->count(), 10); @endphp
                @for ($i = 0; $i < $minimumWorkRows; $i++)
                    @php $row = $workRows->get($i); @endphp
                    <tr>
                        <td class="value center">
                            {{ $fmtDate($row?->date_from) }}
                        </td>
                        <td class="value center">
                            {{ $fmtDate($row?->date_to) }}
                        </td>
                        <td class="value">
                            {{ $upper($row?->position_title) }}
                        </td>
                        <td class="value">
                            {{ $upper($row?->department_office) }}
                        </td>
                        <td class="value right">
                            <span
                                class="line"
                                >{{ $upper($row?->monthly_salary) }}</span
                            >
                        </td>
                        <td class="value center">
                            {{ $upper($row?->salary_grade) }}
                        </td>
                        <td class="value center">
                            {{ $upper($row?->appointment_status) }}
                        </td>
                        <td class="value center">
                            {{ ($row?->sector ?? '') === 'government' ? 'Y' : (($row?->sector ?? '') === 'private' ? 'N' : '') }}
                        </td>
                    </tr>
                @endfor
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="8" class="note center">
                        (Continue on separate sheet if necessary)
                    </td>
                </tr>
            </tfoot>
        </table>
        <table class="pds-table block">
            <thead>
                <tr>
                    <th colspan="5" class="section-title">
                        VI. Voluntary Work or Involvement in Civic /
                        Non-Government / People / Voluntary Organization/s
                    </th>
                </tr>
                <tr>
                    <th style="width: 36%">Name and Address of Organization</th>
                    <th style="width: 13%">
                        Inclusive Dates<br /><span class="small">From</span>
                    </th>
                    <th style="width: 13%">
                        Inclusive Dates<br /><span class="small">To</span>
                    </th>
                    <th style="width: 10%">No. of Hours</th>
                    <th style="width: 28%">Position / Nature of Work</th>
                </tr>
            </thead>
            <tbody>
                @php $volRows = $profile->voluntaryWorks; $minimumVolRows = max($volRows->count(), 5); @endphp
                @for ($i = 0; $i < $minimumVolRows; $i++)
                    @php $row = $volRows->get($i); @endphp
                    <tr>
                        <td class="value">
                            {{ $upper($row?->organization_name) }}
                        </td>
                        <td class="value center">
                            {{ $fmtDate($row?->date_from) }}
                        </td>
                        <td class="value center">
                            {{ $fmtDate($row?->date_to) }}
                        </td>
                        <td class="value center">{{ $upper($row?->hours) }}</td>
                        <td class="value">
                            {{ $upper($row?->position_nature) }}
                        </td>
                    </tr>
                @endfor
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="note center">
                        (Continue on separate sheet if necessary)
                    </td>
                </tr>
            </tfoot>
        </table>
        <table class="pds-table block">
            <thead>
                <tr>
                    <th colspan="6" class="section-title">
                        VII. Learning and Development (L&D)
                        Interventions/Training Programs Attended
                    </th>
                </tr>
                <tr>
                    <th style="width: 33%">
                        Title of Learning and Development Interventions/Training
                        Programs<br /><span class="small">(Write in full)</span>
                    </th>
                    <th style="width: 10%">
                        Inclusive Dates<br /><span class="small">From</span>
                    </th>
                    <th style="width: 10%">
                        Inclusive Dates<br /><span class="small">To</span>
                    </th>
                    <th style="width: 10%">No. of Hours</th>
                    <th style="width: 12%">
                        Type of LD<br /><span class="small"
                            >(Managerial / Supervisory / Technical / etc.)</span
                        >
                    </th>
                    <th style="width: 25%">
                        Conducted / Sponsored By<br /><span class="small"
                            >(Write in full)</span
                        >
                    </th>
                </tr>
            </thead>
            <tbody>
                @php $trainingRows = $profile->trainings; $minimumTrainingRows = max($trainingRows->count(), 5); @endphp
                @for ($i = 0; $i < $minimumTrainingRows; $i++)
                    @php $row = $trainingRows->get($i); @endphp
                    <tr>
                        <td class="value">{{ $upper($row?->title) }}</td>
                        <td class="value center">
                            {{ $fmtDate($row?->date_from) }}
                        </td>
                        <td class="value center">
                            {{ $fmtDate($row?->date_to) }}
                        </td>
                        <td class="value center">{{ $upper($row?->hours) }}</td>
                        <td class="value center">
                            {{ $upper($row?->training_type) }}
                        </td>
                        <td class="value">{{ $upper($row?->conducted_by) }}</td>
                    </tr>
                @endfor
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="note center">
                        (Continue on separate sheet if necessary)
                    </td>
                </tr>
            </tfoot>
        </table>
        <table class="pds-table block">
            <tr>
                <th colspan="3" class="section-title">
                    VIII. Other Information
                </th>
            </tr>
            <tr>
                <th style="width: 33%">Special Skills and Hobbies</th>
                <th style="width: 33%">
                    Non-Academic Distinctions / Recognition<br /><span
                        class="small"
                        >(Write in full)</span
                    >
                </th>
                <th style="width: 34%">
                    Membership in Association / Organization<br /><span
                        class="small"
                        >(Write in full)</span
                    >
                </th>
            </tr>
            @for ($i = 0; $i < $maxOtherRows; $i++)
                <tr>
                    <td class="value">
                        {{ $upper(optional($otherByType->get('special_skill', collect())->values()->get($i))->description) }}
                    </td>
                    <td class="value">
                        {{ $upper(optional($otherByType->get('recognition', collect())->values()->get($i))->description) }}
                    </td>
                    <td class="value">
                        {{ $upper(optional($otherByType->get('membership', collect())->values()->get($i))->description) }}
                    </td>
                </tr>
            @endfor
        </table>
        <table class="pds-table block">
            <tr>
                <th colspan="3" class="section-title">IX. References</th>
            </tr>
            <tr>
                <th style="width: 35%">Name</th>
                <th style="width: 45%">Address</th>
                <th style="width: 20%">Tel. No.</th>
            </tr>
            @for ($i = 0; $i < 3; $i++)
                <tr>
                    <td class="value"><span class="line">&nbsp;</span></td>
                    <td class="value"><span class="line">&nbsp;</span></td>
                    <td class="value"><span class="line">&nbsp;</span></td>
                </tr>
            @endfor
        </table>
        <table class="pds-table block">
            <tr>
                <th colspan="2" class="section-title">X. Declaration</th>
            </tr>
            <tr>
                <td colspan="2" class="declaration">
                    I declare under oath that I have personally accomplished
                    this Personal Data Sheet which is a true, correct and
                    complete statement pursuant to the provisions of pertinent
                    laws, rules and regulations of the Republic of the
                    Philippines. I authorize the head of agency or his/her duly
                    authorized representative to verify and validate the
                    contents stated herein. I agree that any misrepresentation
                    made in this document and its attachments shall cause the
                    filing of administrative/criminal case/s against me.
                </td>
            </tr>
            <tr>
                <td style="width: 50%" class="center sign-box">
                    <span class="line">&nbsp;</span>
                    <div class="small">Date Accomplished</div>
                </td>
                <td style="width: 50%" class="center sign-box">
                    <span class="line">&nbsp;</span>
                    <div class="small">
                        Signature over Printed Name of Employee
                    </div>
                </td>
            </tr>
            <tr>
                <td class="center small">Right Thumbmark (if required)</td>
                <td class="center small">
                    Government Issued ID / Place / Date of Issuance (if
                    required)
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
