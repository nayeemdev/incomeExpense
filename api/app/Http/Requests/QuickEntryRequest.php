<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Rules\Amount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickEntryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', Amount::positive()],
            'description' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in([TransactionType::Withdrawal->value, TransactionType::Deposit->value])],
            'account_id' => ['nullable', 'integer'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:65000'],
        ];
    }
}
