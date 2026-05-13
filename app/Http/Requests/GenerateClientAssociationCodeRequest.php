<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateClientAssociationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => trim((string) $this->input('label')) ?: null,
            'max_uses' => $this->input('max_uses') !== null && $this->input('max_uses') !== ''
                ? (int) $this->input('max_uses')
                : 1,
        ]);
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:120'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
