<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'occurred_on' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'external_id.regex' => 'O external_id deve começar com letra ou número e usar apenas letras, números, ponto, hífen, sublinhado ou dois-pontos.',
            'amount.decimal' => 'O valor deve ter no máximo duas casas decimais.',
            'occurred_on.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
        ];
    }
}
