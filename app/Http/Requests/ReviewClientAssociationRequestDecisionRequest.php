<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewClientAssociationRequestDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'decision' => strtolower(trim((string) $this->input('decision'))),
            'admin_response' => trim((string) $this->input('admin_response')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accepted,rejected'],
            'admin_response' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
