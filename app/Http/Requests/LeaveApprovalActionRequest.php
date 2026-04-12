<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeaveApprovalActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');
        $status = $this->input('status');

        $this->merge([
            'notes' => is_string($notes) && trim($notes) === '' ? null : $notes,
            'status' => is_string($status) && trim($status) === '' ? null : $status,
            'suggested_start_date' => $this->filled('suggested_start_date')
                ? (string) $this->input('suggested_start_date')
                : null,
            'suggested_end_date' => $this->filled('suggested_end_date')
                ? (string) $this->input('suggested_end_date')
                : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requiresDecisionNotes = $this->routeIs('leaves.head.decline')
            || $this->routeIs('leaves.hr.decline')
            || $this->routeIs('leaves.president.decline');

        $requiresStatus = $this->routeIs('leaves.president.decline');

        return [
            'notes' => [$requiresDecisionNotes ? 'required' : 'nullable', 'string', 'max:2000'],
            'suggested_start_date' => ['nullable', 'date'],
            'suggested_end_date' => ['nullable', 'date', 'after_or_equal:suggested_start_date'],
            'status' => [$requiresStatus ? 'required' : 'nullable', 'string', 'in:Needs Revision,Declined'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'notes.required' => 'Notes are required for this decision.',
            'status.required' => 'Please choose a decision status.',
            'suggested_end_date.after_or_equal' => 'Suggested end date must be on or after the suggested start date.',
            'status.in' => 'Status must be either Needs Revision or Declined.',
        ];
    }
}
