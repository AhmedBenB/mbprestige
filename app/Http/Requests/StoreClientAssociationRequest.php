<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientAssociationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'organization_id' => (int) $this->input('organization_id'),
            'message' => trim((string) $this->input('message')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
