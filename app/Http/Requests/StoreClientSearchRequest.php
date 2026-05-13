<?php

namespace App\Http\Requests;

use App\Support\VehicleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClientSearchRequest extends FormRequest
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
            'comment' => ['nullable', 'string', 'max:2000'],
            'consent_email' => ['required', 'boolean'],
            'consent_sms' => ['required', 'boolean'],
            'criteria' => ['required', 'array'],
            'criteria.make' => ['required', 'string', 'max:80'],
            'criteria.model' => ['nullable', 'string', 'max:120'],
            'criteria.budget_max' => ['required', 'numeric', 'min:1'],
            'criteria.year_min' => ['required', 'integer', 'min:1990', 'max:' . date('Y')],
            'criteria.fuel' => ['nullable', 'string', Rule::in(['diesel', 'essence', 'hybride', 'electrique'])],
            'criteria.transmission' => ['nullable', 'string', Rule::in(['automatic', 'manual'])],
            'criteria.mileage_max' => ['nullable', 'integer', 'min:0'],
            'criteria.color' => ['nullable', 'string', 'max:60'],
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
            $user = $this->user();
            $firstName = trim((string) $this->input('first_name', $user?->first_name));
            $lastName = trim((string) $this->input('last_name', $user?->last_name));
            $phone = trim((string) $this->input('phone', $user?->phone));
            $consentEmail = filter_var($this->input('consent_email'), FILTER_VALIDATE_BOOLEAN);
            $consentSms = filter_var($this->input('consent_sms'), FILTER_VALIDATE_BOOLEAN);
            $make = $this->input('criteria.make');
            $model = $this->input('criteria.model');

            if ($firstName === '' || $lastName === '') {
                $validator->errors()->add('first_name', 'Le prenom et le nom sont obligatoires.');
            }

            if (! $consentEmail && ! $consentSms) {
                $validator->errors()->add('consent_email', 'Au moins un consentement de contact est requis.');
            }

            if ($consentSms && $phone === '') {
                $validator->errors()->add('phone', 'Le telephone est obligatoire si vous acceptez les alertes SMS.');
            }

            if ($phone !== '' && ! preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
                $validator->errors()->add('phone', 'Le telephone doit etre au format international, avec un indicatif pays et uniquement des chiffres.');
            }

            if (! VehicleCatalog::isValidMake($make)) {
                $validator->errors()->add('criteria.make', 'La marque selectionnee est invalide.');
            }

            if ($model !== null && $model !== '' && ! VehicleCatalog::isValidModelForMake($make, $model)) {
                $validator->errors()->add('criteria.model', 'Le modele ne correspond pas a la marque selectionnee.');
            }
        });
    }
}
