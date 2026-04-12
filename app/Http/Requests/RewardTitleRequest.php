<?php

namespace App\Http\Requests;

use App\Models\RewardTitle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RewardTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-rewards') ?? false;
    }

    public function rules(): array
    {
        $rewardTitleId = $this->route('reward_title') instanceof RewardTitle
            ? $this->route('reward_title')->id
            : null;

        return [
            'award_type' => ['required', Rule::in(['tenure', 'attendance', 'performance', 'special'])],
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('reward_titles', 'title')
                    ->where(fn ($query) => $query->where('award_type', $this->input('award_type')))
                    ->ignore($rewardTitleId),
            ],
        ];
    }
}
