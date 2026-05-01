<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'title'              => ['required', 'string', 'max:255'],
            'short_description'  => ['nullable', 'string', 'max:500'],
            'long_description'   => ['nullable', 'string'],
            'buy_now_price'      => ['nullable', 'numeric', 'min:0'],
            'starting_price'     => ['nullable', 'numeric', 'min:0'],
            'reserve_price'      => ['nullable', 'numeric', 'min:0'],
            'minimum_increment'  => ['nullable', 'numeric', 'min:50'],
            'publication_status' => ['required', 'string'],
            'vat_deductible'     => ['boolean'],
            'is_featured'        => ['boolean'],
            'starts_at'          => ['nullable', 'date'],
            'ends_at'            => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
