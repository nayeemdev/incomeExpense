<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Rules\Amount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransactionGroupRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'transactions' => ['required', 'array', 'min:1', 'max:100'],
            'transactions.*.type' => ['required', Rule::in(TransactionType::userManaged())],
            'transactions.*.date' => ['required', 'date'],
            'transactions.*.description' => ['required', 'string', 'max:255'],
            'transactions.*.amount' => ['required', Amount::positive()],
            'transactions.*.source_id' => ['nullable', 'integer'],
            'transactions.*.source_name' => ['nullable', 'string', 'max:255'],
            'transactions.*.destination_id' => ['nullable', 'integer'],
            'transactions.*.destination_name' => ['nullable', 'string', 'max:255'],
            'transactions.*.foreign_amount' => ['nullable', Amount::positive()],
            'transactions.*.foreign_currency_id' => ['nullable', 'required_with:transactions.*.foreign_amount', Rule::exists('currencies', 'id')],
            'transactions.*.category_id' => ['nullable', 'integer'],
            'transactions.*.category_name' => ['nullable', 'string', 'max:255'],
            'transactions.*.budget_id' => ['nullable', 'integer'],
            'transactions.*.bill_id' => ['nullable', 'integer'],
            'transactions.*.tags' => ['nullable', 'array'],
            'transactions.*.tags.*' => ['nullable', 'string', 'max:255'],
            'transactions.*.notes' => ['nullable', 'string', 'max:65000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $types = array_unique(array_column((array) $this->input('transactions', []), 'type'));

                if (count($types) > 1) {
                    $validator->errors()->add('transactions', __('All splits in a transaction must have the same type.'));
                }
            },
        ];
    }
}
