<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationEcarsTradeAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $baseUrl = trim((string) $this->input('base_url'));
        $password = (string) $this->input('password');

        $this->merge([
            'login_email' => strtolower(trim((string) $this->input('login_email'))),
            'login_username' => trim((string) $this->input('login_username')),
            'password' => trim($password) !== '' ? $password : null,
            'base_url' => $baseUrl !== '' ? rtrim($baseUrl, '/') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'login_email' => ['nullable', 'email', 'max:190', 'required_without:login_username'],
            'login_username' => ['nullable', 'string', 'max:190', 'required_without:login_email'],
            'password' => ['nullable', 'string', 'max:255'],
            'base_url' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }
}
