<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = Str::lower(trim((string) $this->input('email')));
        $phoneDigits = preg_replace('/\D+/', '', (string) $this->input('phone'));

        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => $email,
            'phone' => $phoneDigits !== '' ? '+' . $phoneDigits : null,
        ]);
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $phone = trim((string) $this->input('phone'));

            if ($phone !== '' && ! preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
                $validator->errors()->add('phone', 'Le telephone doit etre au format international, avec un indicatif pays et uniquement des chiffres.');
            }
        });
    }
}
