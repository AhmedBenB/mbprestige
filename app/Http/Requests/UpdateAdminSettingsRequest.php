<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pricing' => ['sometimes', 'array'],
            'pricing.platform_fee' => ['sometimes', 'numeric', 'min:0'],
            'pricing.transport_cost' => ['sometimes', 'numeric', 'min:0'],
            'pricing.prep_cost' => ['sometimes', 'numeric', 'min:0'],
            'pricing.admin_cost' => ['sometimes', 'numeric', 'min:0'],
            'pricing.warranty_reserve' => ['sometimes', 'numeric', 'min:0'],
            'pricing.safety_buffer' => ['sometimes', 'numeric', 'min:0'],

            'thresholds' => ['sometimes', 'array'],
            'thresholds.reject_below_margin' => ['sometimes', 'numeric', 'min:0'],
            'thresholds.candidate_from_margin' => ['sometimes', 'numeric', 'min:0'],
            'thresholds.priority_from_margin' => ['sometimes', 'numeric', 'min:0'],

            'schedule' => ['sometimes', 'array'],
            'schedule.scan_morning' => ['sometimes', 'date_format:H:i'],
            'schedule.scan_evening' => ['sometimes', 'date_format:H:i'],
            'schedule.timezone' => ['sometimes', 'string', 'max:64'],

            'matching' => ['sometimes', 'array'],
            'matching.high_priority_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'matching.max_results' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'matching.mileage_tolerance_default' => ['sometimes', 'integer', 'min:0', 'max:50000'],

            'routing' => ['sometimes', 'array'],
            'routing.selected_organization_ids' => ['sometimes', 'array'],
            'routing.selected_organization_ids.*' => ['integer', 'distinct', 'exists:organizations,id'],
        ];
    }
}
