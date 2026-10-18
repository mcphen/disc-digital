<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit', $this->route('courrier')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'submitted_at' => ['required', 'date', 'after_or_equal:'.$this->route('courrier')->arrival_date->toDateString()],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
