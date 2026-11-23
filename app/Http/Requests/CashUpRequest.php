<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CashUpRequest extends FormRequest
{
    public const MAX_DAYS = 31;

    /** A missing date means today; a missing end date means a single day. */
    protected function prepareForValidation(): void
    {
        $from = $this->input('from') ?: today()->toDateString();
        $this->merge(['from' => $from, 'to' => $this->input('to') ?: $from]);
    }

    public function authorize(): bool
    {
        return true; // The route is limited to managers and owners.
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            [$from, $to] = $this->period();
            if ($from->diffInDays($to) >= self::MAX_DAYS) {
                $validator->errors()->add('to', 'Choose a period of at most '.self::MAX_DAYS.' days.');
            }
        }];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function period(): array
    {
        return [
            CarbonImmutable::createFromFormat('!Y-m-d', $this->input('from')),
            CarbonImmutable::createFromFormat('!Y-m-d', $this->input('to')),
        ];
    }

    public function branchId(): ?int
    {
        return $this->filled('branch_id') ? (int) $this->input('branch_id') : null;
    }
}
