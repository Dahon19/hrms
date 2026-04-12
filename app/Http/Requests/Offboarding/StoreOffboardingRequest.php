<?php

namespace App\Http\Requests\Offboarding;

use Illuminate\Foundation\Http\FormRequest;

class StoreOffboardingRequest extends FormRequest
{
    public const SEPARATION_REASONS = [
        'Resignation',
        'Retirement',
        'End of Contract',
        'Termination',
        'AWOL',
        'Transfer',
        'Other',
    ];

    protected function prepareForValidation(): void
    {
        $reason = $this->input('resignation_reason', $this->input('reason'));
        $normalizedReason = collect(self::SEPARATION_REASONS)
            ->first(fn (string $option) => mb_strtolower($option) === mb_strtolower((string) $reason));

        $this->merge([
            'resignation_reason' => $normalizedReason ?? $reason,
            'last_working_day' => $this->input('last_working_day', $this->input('effective_last_working_day')),
            'separation_date' => $this->input('separation_date', now()->toDateString()),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->canManageOffboarding() ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'separation_date' => ['required', 'date'],
            'last_working_day' => ['required', 'date', 'after_or_equal:separation_date'],
            'resignation_reason' => ['required', 'string', 'max:150', 'in:' . implode(',', self::SEPARATION_REASONS)],
            'resignation_letter_attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Select the employee to start offboarding.',
            'employee_id.exists' => 'The selected employee could not be found.',
            'separation_date.required' => 'Set the separation date.',
            'last_working_day.required' => 'Set the last working day.',
            'last_working_day.after_or_equal' => 'Last working day cannot be earlier than the separation date.',
            'resignation_reason.required' => 'Select the separation reason.',
            'resignation_reason.in' => 'Select a valid separation reason.',
            'resignation_letter_attachment.mimes' => 'Supporting letter must be a PDF, Word document, or image file.',
            'resignation_letter_attachment.max' => 'Supporting letter must not exceed 10 MB.',
        ];
    }
}

