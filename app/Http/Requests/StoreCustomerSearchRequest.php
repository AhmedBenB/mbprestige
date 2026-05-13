<?php

namespace App\Http\Requests;

use App\Support\VehicleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_name' => ['nullable', 'string', 'max:120', 'required_without_all:client_first_name,client_last_name'],
            'client_first_name' => ['nullable', 'string', 'max:80', 'required_without:client_name'],
            'client_last_name' => ['nullable', 'string', 'max:80', 'required_without:client_name'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:40'],
            'client_comment' => ['nullable', 'string', 'max:2000'],
            'make' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:120'],
            'budget_max' => ['required', 'numeric', 'min:1'],
            'year_min' => ['required', 'integer', 'min:1990', 'max:' . date('Y')],
            'fuel' => ['nullable', 'string', Rule::in(['diesel', 'essence', 'hybride', 'electrique'])],
            'transmission' => ['nullable', 'string', Rule::in(['automatic', 'manual'])],
            'mileage_max' => ['nullable', 'integer', 'min:0'],
            'mileage_tolerance' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'color' => ['nullable', 'string', 'max:60'],
            'source_zone' => ['nullable', 'string', Rule::in(['all_cars'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'paused', 'completed'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $clientName = trim((string) $this->input('client_name'));
            $firstName = trim((string) $this->input('client_first_name'));
            $lastName = trim((string) $this->input('client_last_name'));

            if ($clientName === '' && ($firstName === '' || $lastName === '')) {
                $validator->errors()->add('client_first_name', 'Le prenom et le nom du client sont obligatoires.');
            }

            $make = $this->input('make');
            $model = $this->input('model');

            if (!VehicleCatalog::isValidMake($make)) {
                $validator->errors()->add('make', 'La marque selectionnee est invalide.');
            }

            if (!VehicleCatalog::isValidModelForMake($make, $model)) {
                $validator->errors()->add('model', 'Le modele ne correspond pas a la marque selectionnee.');
            }
        });
    }
}
