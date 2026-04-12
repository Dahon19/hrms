<?php

namespace App\Http\Requests\Offboarding;

use App\Models\ClearanceItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClearanceItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->input('notes', $this->input('remarks')),
        ]);
    }

    public function authorize(): bool
    {
        /** @var ClearanceItem|null $item */
        $item = $this->route('item');

        return $item ? ($this->user()?->can('approveItem', $item) ?? false) : false;
    }

    public function rules(): array
    {
        $requiresNotes = $this->input('status') === ClearanceItem::STATUS_BLOCKED;

        return [
            'status' => ['required', Rule::in([
                ClearanceItem::STATUS_PENDING,
                ClearanceItem::STATUS_CLEARED,
                ClearanceItem::STATUS_BLOCKED,
            ])],
            'notes' => [$requiresNotes ? 'required' : 'nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Notes are required when blocking a clearance item.',
        ];
    }
}

