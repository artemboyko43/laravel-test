<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRevenueReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date' => 'The start date must be a valid date.',
            'start_date.date_format' => 'The start date must be in format Y-m-d (e.g., 2024-01-01).',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.date_format' => 'The end date must be in format Y-m-d (e.g., 2024-01-31).',
            'end_date.after_or_equal' => 'The end date must be equal to or after the start date.',
            'page.integer' => 'The page must be an integer.',
            'page.min' => 'The page must be at least 1.',
            'per_page.integer' => 'The per page must be an integer.',
            'per_page.min' => 'The per page must be at least 1.',
            'per_page.max' => 'The per page may not be greater than 100.',
        ];
    }
}
