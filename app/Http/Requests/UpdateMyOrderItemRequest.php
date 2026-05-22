<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMyOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'Укажите количество.',
            'quantity.integer' => 'Количество должно быть целым числом.',
            'quantity.min' => 'Минимальное количество — 1.',
            'quantity.max' => 'Максимальное количество — 20.',
        ];
    }
}
