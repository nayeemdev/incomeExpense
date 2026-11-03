<?php

namespace App\Http\Requests;

use App\Enums\AutoBudgetType;
use App\Enums\RepeatFrequency;
use App\Models\Budget;
use App\Models\Ledger;
use App\Rules\Amount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Ledger $ledger */
        $ledger = $this->route('ledger');
        $budget = $this->route('budget');

        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255',
                Rule::unique('budgets')->where('ledger_id', $ledger->id)->ignore($budget instanceof Budget ? $budget->id : null),
            ],
            'active' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:65000'],
            'auto_budget_type' => ['nullable', Rule::enum(AutoBudgetType::class)],
            'auto_budget_currency_id' => ['nullable', 'required_with:auto_budget_type', Rule::exists('currencies', 'id')],
            'auto_budget_amount' => ['nullable', 'required_with:auto_budget_type', Amount::positive()],
            'auto_budget_period' => ['nullable', 'required_with:auto_budget_type', Rule::enum(RepeatFrequency::class)],
        ];
    }
}
