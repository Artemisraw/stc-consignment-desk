<?php

namespace App\Http\Requests;

/** Same fields as intake, except payments: those are recorded or reversed separately. */
class UpdateConsignmentRequest extends StoreConsignmentRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['paid_amount']);

        return $rules;
    }
}
