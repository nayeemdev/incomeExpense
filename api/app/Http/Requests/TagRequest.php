<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Ledger $ledger */
        $ledger = $this->route('ledger');
        $tag = $this->route('tag');

        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('tags')->where('ledger_id', $ledger->id)->ignore($tag instanceof Tag ? $tag->id : null),
            ],
            'description' => ['nullable', 'string', 'max:65000'],
            'date' => ['nullable', 'date'],
        ];
    }
}
