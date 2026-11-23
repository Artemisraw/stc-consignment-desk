<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vehicle')) ?? false;
    }

    public function rules(): array
    {
        return [
            'registration_number' => [
                'required', 'string', 'max:20',
                Rule::unique('vehicles', 'registration_number')->ignore($this->route('vehicle')),
            ],
            'make' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'capacity_kg' => ['nullable', 'integer', 'min:1'],
            'default_driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
        ];
    }
}
