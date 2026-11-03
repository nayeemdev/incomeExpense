<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\AssetRole;
use App\Enums\InterestPeriod;
use App\Enums\LiabilityDirection;
use App\Enums\LiabilityType;
use App\Models\Account;
use App\Models\Ledger;
use App\Rules\Amount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    public function accountType(): AccountType
    {
        $account = $this->route('account');

        return $account instanceof Account ? $account->type : AccountType::from($this->string('type')->value());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';
        /** @var Ledger $ledger */
        $ledger = $this->route('ledger');
        $account = $this->route('account');

        $rules = [
            'type' => $creating ? ['required', Rule::in(AccountType::userManaged())] : ['prohibited'],
        ];

        if ($creating && ! in_array($this->input('type'), array_map(fn ($type) => $type->value, AccountType::userManaged()), true)) {
            return $rules;
        }

        $type = $this->accountType();
        $own = $type->isOwn();

        return [
            ...$rules,
            'name' => [
                $required, 'string', 'max:255',
                Rule::unique('accounts')
                    ->where('ledger_id', $ledger->id)
                    ->where('type', $type->value)
                    ->whereNull('deleted_at')
                    ->ignore($account instanceof Account ? $account->id : null),
            ],
            'currency_id' => $own ? [$required, Rule::exists('currencies', 'id')] : ['nullable', Rule::exists('currencies', 'id')],
            'asset_role' => $type === AccountType::Asset ? [$required, Rule::enum(AssetRole::class)] : ['prohibited'],
            'liability_type' => $type === AccountType::Liability ? [$required, Rule::enum(LiabilityType::class)] : ['prohibited'],
            'liability_direction' => $type === AccountType::Liability ? [$required, Rule::enum(LiabilityDirection::class)] : ['prohibited'],
            'interest' => $type === AccountType::Liability ? ['nullable', 'numeric', 'between:0,100'] : ['prohibited'],
            'interest_period' => $type === AccountType::Liability ? ['nullable', Rule::enum(InterestPeriod::class)] : ['prohibited'],
            'credit_card_payment_day' => ['nullable', 'integer', 'between:1,31'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'virtual_balance' => $own ? ['nullable', Amount::signed()] : ['prohibited'],
            'low_balance_threshold' => $own ? ['nullable', Amount::signed()] : ['prohibited'],
            'active' => ['sometimes', 'boolean'],
            'include_net_worth' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:65000'],
            'object_group' => ['nullable', 'string', 'max:255'],
            'opening_balance' => $own ? ['nullable', Amount::signed()] : ['prohibited'],
            'opening_balance_date' => $own ? ['nullable', 'required_with:opening_balance', 'date'] : ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountAttributes(): array
    {
        return collect($this->validated())
            ->except(['opening_balance', 'opening_balance_date'])
            ->all();
    }
}
