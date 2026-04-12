<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PdsSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $section = (string) $this->route('section');

        return match ($section) {
            'personal-information' => $this->personalInformationRules(),
            'family-background' => $this->familyBackgroundRules(),
            'education-background' => $this->educationBackgroundRules(),
            'civil-service-eligibility' => $this->civilServiceRules(),
            'work-experience' => $this->workExperienceRules(),
            'voluntary-work' => $this->voluntaryWorkRules(),
            'learning-development' => $this->learningDevelopmentRules(),
            'other-information' => $this->otherInformationRules(),
            default => ['section' => ['required', Rule::in([])]],
        };
    }

    public function withValidator($validator): void
    {
        $section = (string) $this->route('section');

        if ($section === 'work-experience') {
            $validator->after(function ($validator): void {
                $entries = collect($this->input('entries', []));
                $dupes = $entries
                    ->filter(fn ($item) => !empty($item['position_title']) && !empty($item['date_from']))
                    ->map(function ($item) {
                        return strtolower(trim((string) ($item['position_title'] ?? ''))) . '|'
                            . (string) ($item['date_from'] ?? '') . '|'
                            . (string) ($item['date_to'] ?? '');
                    });

                if ($dupes->count() !== $dupes->unique()->count()) {
                    $validator->errors()->add('entries', 'Duplicate work experience period detected.');
                }
            });
        }
    }

    private function personalInformationRules(): array
    {
        return [
            // Draft-friendly: strict completeness is enforced on mark-completed.
            'last_name' => ['nullable', 'string', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'name_extension' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', Rule::in(['male', 'female'])],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'citizenship' => ['nullable', 'string', 'max:120'],
            'height_m' => ['nullable', 'numeric', 'between:0.5,2.8'],
            'weight_kg' => ['nullable', 'numeric', 'between:10,400'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'gsis_no' => ['nullable', 'string', 'max:40'],
            'sss_no' => ['nullable', 'string', 'max:40'],
            'tin_no' => ['nullable', 'string', 'max:40'],
            'philhealth_no' => ['nullable', 'string', 'max:40'],
            'residential_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'telephone_no' => ['nullable', 'string', 'max:50'],
            'mobile_no' => ['nullable', 'string', 'max:50'],
            'email_address' => ['nullable', 'email', 'max:190'],
        ];
    }

    private function familyBackgroundRules(): array
    {
        return [
            'spouse_last_name' => ['nullable', 'string', 'max:120'],
            'spouse_first_name' => ['nullable', 'string', 'max:120'],
            'spouse_middle_name' => ['nullable', 'string', 'max:120'],
            'spouse_occupation' => ['nullable', 'string', 'max:120'],
            'spouse_employer' => ['nullable', 'string', 'max:190'],
            'spouse_business_address' => ['nullable', 'string', 'max:255'],
            'spouse_telephone' => ['nullable', 'string', 'max:50'],
            'father_last_name' => ['nullable', 'string', 'max:120'],
            'father_first_name' => ['nullable', 'string', 'max:120'],
            'father_middle_name' => ['nullable', 'string', 'max:120'],
            'father_name_extension' => ['nullable', 'string', 'max:30'],
            'mother_last_name' => ['nullable', 'string', 'max:120'],
            'mother_first_name' => ['nullable', 'string', 'max:120'],
            'mother_middle_name' => ['nullable', 'string', 'max:120'],
            'children' => ['nullable', 'array'],
            'children.*.id' => ['nullable', 'integer'],
            'children.*.full_name' => ['nullable', 'string', 'max:190'],
            'children.*.birth_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    private function educationBackgroundRules(): array
    {
        return [
            'entries' => ['nullable', 'array'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.education_level' => ['required_with:entries.*.school_name', Rule::in(['elementary', 'secondary', 'vocational', 'college', 'graduate'])],
            'entries.*.school_name' => ['nullable', 'string', 'max:255'],
            'entries.*.degree_course' => ['nullable', 'string', 'max:255'],
            'entries.*.date_from' => ['nullable', 'date'],
            'entries.*.date_to' => ['nullable', 'date', 'after_or_equal:entries.*.date_from'],
            'entries.*.highest_level_units' => ['nullable', 'string', 'max:120'],
            'entries.*.year_graduated' => ['nullable', 'string', 'max:10'],
            'entries.*.honors_received' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function civilServiceRules(): array
    {
        return [
            'entries' => ['nullable', 'array'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.eligibility_type' => ['required_with:entries.*.rating', 'string', 'max:190'],
            'entries.*.rating' => ['nullable', 'string', 'max:40'],
            'entries.*.exam_date' => ['nullable', 'date'],
            'entries.*.exam_place' => ['nullable', 'string', 'max:190'],
            'entries.*.license_number' => ['nullable', 'string', 'max:120'],
            'entries.*.validity_date' => ['nullable', 'date', 'after_or_equal:entries.*.exam_date'],
        ];
    }

    private function workExperienceRules(): array
    {
        return [
            'entries' => ['nullable', 'array'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.date_from' => ['nullable', 'date'],
            'entries.*.date_to' => ['nullable', 'date', 'after_or_equal:entries.*.date_from'],
            'entries.*.position_title' => ['required_with:entries.*.department_office', 'string', 'max:190'],
            'entries.*.department_office' => ['nullable', 'string', 'max:190'],
            'entries.*.salary_grade' => ['nullable', 'string', 'max:50'],
            'entries.*.appointment_status' => ['nullable', 'string', 'max:120'],
            'entries.*.sector' => ['nullable', Rule::in(['government', 'private'])],
        ];
    }

    private function voluntaryWorkRules(): array
    {
        return [
            'entries' => ['nullable', 'array'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.organization_name' => ['required_with:entries.*.position_nature', 'string', 'max:190'],
            'entries.*.date_from' => ['nullable', 'date'],
            'entries.*.date_to' => ['nullable', 'date', 'after_or_equal:entries.*.date_from'],
            'entries.*.hours' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'entries.*.position_nature' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function learningDevelopmentRules(): array
    {
        return [
            'entries' => ['nullable', 'array'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.title' => ['required_with:entries.*.conducted_by', 'string', 'max:255'],
            'entries.*.date_from' => ['nullable', 'date'],
            'entries.*.date_to' => ['nullable', 'date', 'after_or_equal:entries.*.date_from'],
            'entries.*.hours' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'entries.*.training_type' => ['nullable', 'string', 'max:120'],
            'entries.*.conducted_by' => ['nullable', 'string', 'max:190'],
        ];
    }

    private function otherInformationRules(): array
    {
        return [
            'special_skills' => ['nullable', 'array'],
            'special_skills.*' => ['nullable', 'string', 'max:255'],
            'recognitions' => ['nullable', 'array'],
            'recognitions.*' => ['nullable', 'string', 'max:255'],
            'memberships' => ['nullable', 'array'],
            'memberships.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}

