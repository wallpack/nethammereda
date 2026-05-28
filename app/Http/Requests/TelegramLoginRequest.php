<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TelegramLoginRequest extends FormRequest
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
            'id' => ['required', 'integer', 'min:1'],
            'auth_date' => ['required', 'integer', 'min:1'],
            'hash' => ['required', 'string', 'size:64'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'photo_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required' => 'Не передан Telegram user id.',
            'auth_date.required' => 'Не передан auth_date.',
            'hash.required' => 'Не передан hash.',
            'hash.size' => 'Некорректный hash Telegram авторизации.',
        ];
    }
}
