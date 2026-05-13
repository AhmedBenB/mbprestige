<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ClaimClientAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'manage_token' => trim((string) $this->input('manage_token')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'manage_token' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
