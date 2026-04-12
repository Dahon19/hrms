<?php

namespace App\Http\Requests\TravelOrders;

use App\Models\TravelOrderTransportation;
use Illuminate\Validation\Rule;

class UpdateTravelOrderRequest extends StoreTravelOrderRequest
{
    public function rules(): array
    {
        $transportOptions = TravelOrderTransportation::activeNames();
        $currentTransportMode = trim((string) ($this->route('travel_order')?->transport_mode ?? ''));

        if ($currentTransportMode !== '' && !in_array($currentTransportMode, $transportOptions, true)) {
            $transportOptions[] = $currentTransportMode;
        }

        $transportModeRules = ['required', 'string', 'max:255'];
        if ($transportOptions !== []) {
            $transportModeRules[] = Rule::in($transportOptions);
        }

        return [
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:5000'],
            'date_from' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:date_to'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i'],
            'transport_mode' => $transportModeRules,
            'budget_proposal' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ];
    }
}
