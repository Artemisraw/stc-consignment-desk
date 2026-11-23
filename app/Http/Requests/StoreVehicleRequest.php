<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'registration_number' => ['required', 'string', 'max:20', 'unique:vehicles,registration_number'],
            'make' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'capacity_kg' => ['nullable', 'integer', 'min:1'],
            'default_driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
        ];
    }
}
