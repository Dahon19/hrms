<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpmsSaveEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'cycle_id' => ['required', 'integer', 'exists:spms_cycles,id'],
            'intent' => ['required', Rule::in(['draft', 'pending', 'submitted'])],
            'details' => ['required', 'array', 'min:1'],
            'details.*.criteria_id' => ['required', 'integer', 'exists:spms_criteria,id'],
            'details.*.score' => ['required', 'numeric', 'min:1', 'max:5'],
            'details.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
