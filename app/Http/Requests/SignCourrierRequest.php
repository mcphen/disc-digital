<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sign', $this->route('courrier')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $submittedAt = ($this->route('courrier')->division_validated_at
            ?? $this->route('courrier')->submitted_at)?->toDateString();

        return [
            'signed_at' => array_values(array_filter([
                'required',
                'date',
                $submittedAt ? 'after_or_equal:'.$submittedAt : null,
            ])),
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
