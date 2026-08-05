<?php

namespace App\Http\Requests\Admin\CommissionTiers;

use App\Rules\CommissionTiersRule;
use Illuminate\Foundation\Http\FormRequest;

class CommissionTiersUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Amounts in manats: the order line amount picks the percent.
            // Empty array = drop the tiers, the default_commission_percent
            // setting applies again.
            'commission_tiers' => ['present', 'nullable', 'array', new CommissionTiersRule()],
            'commission_tiers.*.min_amount' => ['required', 'numeric', 'min:0'],
            'commission_tiers.*.max_amount' => ['nullable', 'numeric', 'min:0'],
            'commission_tiers.*.percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
