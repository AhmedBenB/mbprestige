<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AttachOrganizationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper((string) $this->input('code'));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?: '';

        $this->merge([
            'code' => $code,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:4', 'max:20'],
        ];
    }
}
