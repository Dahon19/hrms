<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RewardAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-rewards') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'reward_title_id' => ['required', 'integer', Rule::exists('reward_titles', 'id')],
            'award_date' => ['required', 'date'],
        ];
    }
}
