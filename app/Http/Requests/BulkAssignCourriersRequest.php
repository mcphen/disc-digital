<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkAssignCourriersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('bulk-assign-courriers');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'courrier_ids' => ['required', 'array', 'min:1', 'max:250'],
            'courrier_ids.*' => ['required', 'integer', 'distinct', 'exists:courriers,id'],
            'division_id' => ['required', Rule::exists('divisions', 'id')->where('is_active', true)],
            'division_assigned_at' => ['required', 'date'],
            'assigned_agent_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'agent_assigned_at' => ['nullable', 'required_with:assigned_agent_id', 'date', 'after_or_equal:division_assigned_at'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
