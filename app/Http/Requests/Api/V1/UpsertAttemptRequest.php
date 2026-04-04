<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAttemptRequest extends FormRequest
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
            'progress' => ['required', 'array'],
            'pencil_cells' => ['nullable', 'array'],
            'is_completed' => ['sometimes', 'boolean'],
            'solve_time_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
