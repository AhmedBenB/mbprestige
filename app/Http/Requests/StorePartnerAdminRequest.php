<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePartnerAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'organization_name' => trim((string) $this->input('organization_name')),
            'organization_slug' => Str::slug((string) $this->input('organization_slug')),
            'organization_location' => trim((string) $this->input('organization_location')) ?: null,
            'organization_description' => trim((string) $this->input('organization_description')) ?: null,
            'admin_first_name' => trim((string) $this->input('admin_first_name')),
            'admin_last_name' => trim((string) $this->input('admin_last_name')),
            'admin_email' => Str::lower(trim((string) $this->input('admin_email'))),
            'admin_phone' => $this->normalizePhone($this->input('admin_phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:120'],
            'organization_slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'organization_location' => ['nullable', 'string', 'max:160'],
            'organization_description' => ['nullable', 'string', 'max:1000'],
            'admin_first_name' => ['required', 'string', 'max:80'],
            'admin_last_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    private function normalizePhone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? '+' . $digits : null;
    }
}
