<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'consignment_ids' => ['required', 'array', 'min:1'],
            'consignment_ids.*' => ['integer', 'distinct', 'exists:consignments,id'],
        ];
    }
}
