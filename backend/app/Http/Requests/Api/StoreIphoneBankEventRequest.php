<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIphoneBankEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'text' => ['required', 'string', 'max:2000'],
            'source' => ['required', Rule::in(['sms', 'email', 'wallet', 'manual'])],
            'sender' => ['nullable', 'string', 'max:120'],
            'received_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_id.regex' => 'O event_id deve começar com letra ou número e usar apenas letras, números, ponto, hífen, sublinhado ou dois-pontos.',
            'source.in' => 'A origem deve ser sms, email, wallet ou manual.',
        ];
    }
}
