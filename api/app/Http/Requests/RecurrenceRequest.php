<?php

namespace App\Http\Requests;

use App\Enums\RepetitionType;
use App\Enums\TransactionType;
use App\Enums\WeekendHandling;
use App\Rules\Amount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecurrenceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'type' => [$required, Rule::in(TransactionType::userManaged())],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65000'],
            'first_date' => [$required, 'date'],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:first_date'],
            'max_occurrences' => ['nullable', 'integer', 'min:1'],
            'apply_rules' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'repetition' => [$required, 'array'],
            'repetition.type' => ['required_with:repetition', Rule::enum(RepetitionType::class)],
            'repetition.moment' => ['nullable', 'string', 'max:20'],
            'repetition.skip' => ['sometimes', 'integer', 'min:0', 'max:31'],
            'repetition.weekend' => ['sometimes', Rule::enum(WeekendHandling::class)],
            'transactions' => [$required, 'array', 'min:1', 'max:20'],
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
                $repetition = (array) $this->input('repetition', []);
                $type = RepetitionType::tryFrom((string) ($repetition['type'] ?? ''));
                $moment = (string) ($repetition['moment'] ?? '');

                $valid = match ($type) {
                    RepetitionType::Daily, null => true,
                    RepetitionType::Weekly => preg_match('/^[1-7]$/', $moment) === 1,
                    RepetitionType::Monthly => preg_match('/^([1-9]|[12]\d|3[01])$/', $moment) === 1,
                    RepetitionType::NthWeekday => preg_match('/^[1-5],[1-7]$/', $moment) === 1,
                    RepetitionType::Yearly => preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $moment) === 1,
                };

                if (! $valid) {
                    $validator->errors()->add('repetition.moment', __('Choose when in the period it repeats.'));
                }
            },
        ];
    }
}
