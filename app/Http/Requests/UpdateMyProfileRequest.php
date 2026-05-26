<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('full_name')) {
            return;
        }

        $fullName = $this->input('full_name');

        if (is_string($fullName)) {
            $fullName = trim($fullName);
            $fullName = $fullName === '' ? null : $fullName;
        }

        $this->merge([
            'full_name' => $fullName,
        ]);
    }
}
