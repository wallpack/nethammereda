<?php

namespace App\Rules;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Validation\ValidationRule;

class FourDigitYearDateTime implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($value instanceof DateTimeInterface) {
            $this->validateYear($value->format('Y'), $fail);

            return;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return;
        }

        if ($this->hasFiveDigitYear($raw) || ! $this->hasFourDigitYear($raw)) {
            $fail('Укажите год четырьмя цифрами.');
        }
    }

    private function validateYear(string $year, Closure $fail): void
    {
        if (preg_match('/^\d{4}$/', $year) === 1) {
            return;
        }

        $fail('Укажите год четырьмя цифрами.');
    }

    private function hasFiveDigitYear(string $value): bool
    {
        return preg_match('/^\+?\d{5,}(?=[\-.\/])/', $value) === 1
            || preg_match('/(?<=[\-.\/])\d{5,}(?=\s|$)/', $value) === 1;
    }

    private function hasFourDigitYear(string $value): bool
    {
        return preg_match('/^\d{4}(?=[\-.\/])/', $value) === 1
            || preg_match('/(?<=[\-.\/])\d{4}(?=\s|$)/', $value) === 1;
    }
}
