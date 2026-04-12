<?php

namespace App\Http\Requests\TravelOrders;

use Illuminate\Foundation\Http\FormRequest;

class TravelOrderDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('decision_reason');

        $this->merge([
            'decision_reason' => is_string($reason) && trim($reason) === '' ? null : $reason,
        ]);
    }

    public function rules(): array
    {
        return [
            'decision_reason' => ['required', 'string', 'max:2000'],
            'reject_action' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_reason.required' => 'Decision reason is required.',
        ];
    }
}
