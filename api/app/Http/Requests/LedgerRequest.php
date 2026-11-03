<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LedgerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'primary_currency_id' => [$required, 'integer', Rule::exists('currencies', 'id')],
            'fiscal_year_start' => ['sometimes', 'date_format:m-d'],
        ];
    }
}
