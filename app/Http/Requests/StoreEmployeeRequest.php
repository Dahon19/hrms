<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    private const EMPLOYEE_NAME_REGEX = "/^(?=.{1,255}$)[A-Za-z]+(?:[ .'-][A-Za-z]+)*$/";
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->normalizeWhitespace($this->input('first_name')),
            'last_name' => $this->normalizeWhitespace($this->input('last_name')),
            'address' => $this->normalizeOptionalText($this->input('address')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'unique:users,email'],
            'employee_id' => ['required', 'unique:employees,employee_id'],
            'gender' => ['required', 'in:male,female'],
            'first_name' => ['required', 'string', 'max:255', 'regex:' . self::EMPLOYEE_NAME_REGEX],
            'last_name' => ['required', 'string', 'max:255', 'regex:' . self::EMPLOYEE_NAME_REGEX],
            'address' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['required', 'exists:departments,id'],
            'position_ids' => ['required', 'array', 'min:1'],
            'position_ids.*' => ['exists:positions,id'],
            'hire_date' => ['nullable', 'date'],
            'nfc_uid' => ['nullable', 'string', 'unique:employee_nfcs,nfc_uid'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.regex' => 'First name can contain letters, spaces, apostrophes, periods, and hyphens only.',
            'last_name.regex' => 'Last name can contain letters, spaces, apostrophes, periods, and hyphens only.',
            'position_ids.required' => 'Select at least one position.',
            'position_ids.array' => 'Select at least one position.',
            'position_ids.min' => 'Select at least one position.',
        ];
    }

    public function attributes(): array
    {
        return [
            'position_ids' => 'positions',
            'position_ids.*' => 'position',
            'department_id' => 'department',
            'nfc_uid' => 'NFC card',
        ];
    }

    private function normalizeWhitespace(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        $normalized = $this->normalizeWhitespace($value);

        return $normalized !== '' ? $normalized : null;
    }
}
