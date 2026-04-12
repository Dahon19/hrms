<?php

namespace App\Http\Requests;

use App\Services\AccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->has('name') && $this->user()) {
            $this->merge([
                'name' => (string) $this->user()->name,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'avatar' => ['nullable', 'image', 'max:2048'], // max 2MB
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $user = $this->user();
            if (!$user) {
                return;
            }

            $submittedName = trim((string) $this->input('name', ''));
            $currentName = trim((string) $user->name);

            $canEditOwnAccountName = $user->isAdmin() || AccessControl::isHrStaff($user);

            if (!$canEditOwnAccountName && $submittedName !== '' && $submittedName !== $currentName) {
                $validator->errors()->add('name', 'Account name changes require an HR request.');
            }
        });
    }
}
