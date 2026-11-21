<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsignmentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        return [
            'origin_branch_id' => ['required', 'exists:branches,id'],
            'destination_branch_id' => ['required', 'different:origin_branch_id', 'exists:branches,id'],
            'sender.name' => ['nullable', 'string', 'max:100'],
            'sender.phone' => ['nullable', 'string', 'max:30'],
            'sender.email' => ['nullable', 'email', 'max:255'],
            'receiver.name' => ['required', 'string', 'max:100'],
            'receiver.phone' => ['required', 'string', 'max:30'],
            'receiver.email' => ['nullable', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
