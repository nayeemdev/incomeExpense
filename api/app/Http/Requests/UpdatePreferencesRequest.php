<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', 'max:20'],
            'timezone' => ['sometimes', 'timezone:all'],
            'theme' => ['sometimes', Rule::in(['system', 'light', 'dark'])],
            'first_day_of_week' => ['sometimes', 'integer', 'between:0,6'],
            'view_range' => ['sometimes', Rule::in(['week', 'month', 'quarter', 'year'])],
            'dashboard_hidden' => ['sometimes', 'array'],
            'dashboard_hidden.*' => [Rule::in(config()->array('ledgerfly.dashboard_widgets'))],
        ];
    }
}
