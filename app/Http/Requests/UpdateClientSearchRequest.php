<?php

namespace App\Http\Requests;

use App\Models\CustomerSearch;
use App\Support\VehicleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'consent_email' => ['sometimes', 'boolean'],
            'consent_sms' => ['sometimes', 'boolean'],
            'criteria' => ['sometimes', 'array'],
            'criteria.make' => ['sometimes', 'required', 'string', 'max:80'],
            'criteria.model' => ['sometimes', 'nullable', 'string', 'max:120'],
            'criteria.budget_max' => ['sometimes', 'required', 'numeric', 'min:1'],
            'criteria.year_min' => ['sometimes', 'required', 'integer', 'min:1990', 'max:' . date('Y')],
            'criteria.fuel' => ['sometimes', 'nullable', 'string', Rule::in(['diesel', 'essence', 'hybride', 'electrique'])],
            'criteria.transmission' => ['sometimes', 'nullable', 'string', Rule::in(['automatic', 'manual'])],
            'criteria.mileage_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'criteria.color' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $phoneDigits = preg_replace('/\D+/', '', (string) $this->input('phone'));

            $this->merge([
                'phone' => $phoneDigits !== '' ? '+' . $phoneDigits : null,
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CustomerSearch|null $search */
            $search = $this->route('search');
            $make = $this->input('criteria.make', $search?->make);
            $model = $this->input('criteria.model', $search?->model);
            $phone = trim((string) $this->input('phone', ''));
            $consentSms = $this->has('consent_sms')
                ? filter_var($this->input('consent_sms'), FILTER_VALIDATE_BOOLEAN)
                : (bool) $search?->consent_sms;

            if ($this->hasAny(['first_name', 'last_name'])) {
                $firstName = trim((string) $this->input('first_name', $search?->client_first_name));
                $lastName = trim((string) $this->input('last_name', $search?->client_last_name));

                if ($firstName === '' || $lastName === '') {
                    $validator->errors()->add('first_name', 'Le prenom et le nom sont obligatoires.');
                }
            }

            if ($phone !== '' && !preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
                $validator->errors()->add('phone', 'Le telephone doit etre au format international, avec un indicatif pays et uniquement des chiffres.');
            }

            if ($consentSms && trim((string) ($this->input('phone', $search?->client_phone))) === '') {
                $validator->errors()->add('phone', 'Le telephone est obligatoire si vous acceptez les alertes SMS.');
            }

            if ($this->has('criteria') || $this->has('criteria.make') || $this->has('criteria.model')) {
                if (!VehicleCatalog::isValidMake($make)) {
                    $validator->errors()->add('criteria.make', 'La marque selectionnee est invalide.');
                }

                if ($model !== null && $model !== '' && !VehicleCatalog::isValidModelForMake($make, $model)) {
                    $validator->errors()->add('criteria.model', 'Le modele ne correspond pas a la marque selectionnee.');
                }
            }
        });
    }
}
