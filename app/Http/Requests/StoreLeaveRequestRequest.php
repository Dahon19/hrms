<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user || $user->isAdmin()) {
            return false;
        }

        return !$this->isPresidentOfficeApprover($user);
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'Please select a leave type.',
            'leave_type_id.exists' => 'The selected leave type is invalid.',
            'start_date.required' => 'Please select a start date.',
            'end_date.required' => 'Please select an end date.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'attachment.mimes' => 'Attachment must be a PDF, JPG, or PNG file.',
            'attachment.max' => 'Attachment must not exceed 5MB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'leave_type_id' => 'leave type',
            'start_date' => 'start date',
            'end_date' => 'end date',
        ];
    }

    private function isPresidentOfficeApprover($user): bool
    {
        if ($user->positionName() !== 'head') {
            return false;
        }

        $departmentName = strtolower(trim($user->employee?->department?->department ?? ''));
        if ($departmentName === '') {
            return false;
        }

        $normalized = preg_replace('/[^a-z0-9 ]/i', '', $departmentName);
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

        return $normalized === 'presidents office';
    }
}
