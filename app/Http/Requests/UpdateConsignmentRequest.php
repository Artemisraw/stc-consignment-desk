<?php

namespace App\Http\Requests;

/** Same fields as intake, except payments: those are recorded or reversed separately. */
class UpdateConsignmentRequest extends StoreConsignmentRequest
{
    /** Check the policy before validating, so another branch's consignment is refused outright. */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('consignment')) ?? false;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['paid_amount']);

        return $rules;
    }
}
