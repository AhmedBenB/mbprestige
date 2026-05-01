<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class PlaceBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Veuillez saisir un montant.',
            'amount.numeric'  => 'Le montant doit être un nombre.',
            'amount.min'      => 'Le montant doit être supérieur à 0.',
        ];
    }
}
