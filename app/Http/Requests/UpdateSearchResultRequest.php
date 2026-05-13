<?php

namespace App\Http\Requests;

use App\Models\SearchResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSearchResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['sometimes', 'required', 'string', Rule::in([
                SearchResult::STATUS_CANDIDATE,
                SearchResult::STATUS_APPROVED,
                SearchResult::STATUS_REJECTED,
                SearchResult::STATUS_ON_HOLD,
                SearchResult::STATUS_SHARED,
            ])],
            'admin_summary' => ['sometimes', 'nullable', 'string'],
            'review_notes' => ['sometimes', 'nullable', 'string'],
            'shared_channel' => ['sometimes', 'nullable', 'string', 'max:40'],
            'shared_note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
