<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TelegramAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'init_data' => ['required', 'string', 'min:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'init_data.required' => 'Отсутствуют данные Telegram WebApp.',
            'init_data.min' => 'Некорректные данные Telegram WebApp.',
        ];
    }
}
