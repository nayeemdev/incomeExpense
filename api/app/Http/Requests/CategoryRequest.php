<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Ledger $ledger */
        $ledger = $this->route('ledger');
        $category = $this->route('category');

        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('categories')->where('ledger_id', $ledger->id)->ignore($category instanceof Category ? $category->id : null),
            ],
            'icon' => ['nullable', Rule::in(config()->array('ledgerfly.categories.icons'))],
            'color' => ['nullable', Rule::in(config()->array('ledgerfly.categories.colors'))],
            'notes' => ['nullable', 'string', 'max:65000'],
        ];
    }
}
