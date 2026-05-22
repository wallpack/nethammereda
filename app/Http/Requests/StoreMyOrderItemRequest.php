<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMyOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_item_id' => [
                'required',
                'integer',
                Rule::exists('menu_items', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'menu_item_id.required' => 'Выберите блюдо.',
            'menu_item_id.exists' => 'Блюдо недоступно для заказа.',
            'quantity.integer' => 'Количество должно быть целым числом.',
            'quantity.min' => 'Минимальное количество — 1.',
            'quantity.max' => 'Максимальное количество — 20.',
        ];
    }
}
