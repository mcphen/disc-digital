<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('courrier')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $submittedAt = $this->route('courrier')->submitted_at?->toDateString();

        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reviewed_at' => array_values(array_filter([
                'required',
                'date',
                $submittedAt ? 'after_or_equal:'.$submittedAt : null,
            ])),
            'comment' => ['nullable', 'required_if:decision,reject', 'string', 'max:2000'],
        ];
    }
}
