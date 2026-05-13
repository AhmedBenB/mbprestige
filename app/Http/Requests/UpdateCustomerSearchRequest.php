<?php

namespace App\Http\Requests;

use App\Models\CustomerSearch;
use App\Support\VehicleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'client_first_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'client_last_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'client_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'client_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'client_comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'make' => ['sometimes', 'required', 'string', 'max:80'],
            'model' => ['sometimes', 'required', 'string', 'max:120'],
            'budget_max' => ['sometimes', 'required', 'numeric', 'min:1'],
            'year_min' => ['sometimes', 'required', 'integer', 'min:1990', 'max:' . date('Y')],
            'fuel' => ['sometimes', 'nullable', 'string', Rule::in(['diesel', 'essence', 'hybride', 'electrique'])],
            'transmission' => ['sometimes', 'nullable', 'string', Rule::in(['automatic', 'manual'])],
            'mileage_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'mileage_tolerance' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50000'],
            'color' => ['sometimes', 'nullable', 'string', 'max:60'],
            'source_zone' => ['sometimes', 'nullable', 'string', Rule::in(['all_cars'])],
            'status' => ['sometimes', 'required', 'string', Rule::in([
                CustomerSearch::STATUS_ACTIVE,
                CustomerSearch::STATUS_PAUSED,
                CustomerSearch::STATUS_COMPLETED,
            ])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CustomerSearch|null $search */
            $search = $this->route('search');
            $clientName = trim((string) $this->input('client_name', $search?->client_name));
            $firstName = trim((string) $this->input('client_first_name', $search?->client_first_name));
            $lastName = trim((string) $this->input('client_last_name', $search?->client_last_name));

            if ($clientName === '' && ($firstName === '' || $lastName === '')) {
                $validator->errors()->add('client_first_name', 'Le prenom et le nom du client sont obligatoires.');
            }

            $make = $this->input('make', $search?->make);
            $model = $this->input('model', $search?->model);

            if (!VehicleCatalog::isValidMake($make)) {
                $validator->errors()->add('make', 'La marque selectionnee est invalide.');
            }

            if (!VehicleCatalog::isValidModelForMake($make, $model)) {
                $validator->errors()->add('model', 'Le modele ne correspond pas a la marque selectionnee.');
            }
        });
    }
}
