<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign', $this->route('courrier')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'division_id' => ['required', Rule::exists('divisions', 'id')->where('is_active', true)],
            'division_assigned_at' => ['required', 'date', 'after_or_equal:'.$this->route('courrier')->arrival_date->toDateString()],
            'assigned_agent_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'agent_assigned_at' => ['nullable', 'required_with:assigned_agent_id', 'date', 'after_or_equal:division_assigned_at'],
            'contributing_division_ids' => ['nullable', 'array', 'max:10'],
            'contributing_division_ids.*' => ['integer', 'distinct', Rule::exists('divisions', 'id')->where('is_active', true)],
            'co_agent_ids' => ['nullable', 'array', 'max:10', 'prohibited_if:assigned_agent_id,null'],
            'co_agent_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'co_agent_ids.prohibited_if' => "Désignez d'abord l'agent responsable avant d'ajouter des co-agents.",
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'contributing_division_ids' => 'divisions contributrices',
            'contributing_division_ids.*' => 'division contributrice',
            'co_agent_ids' => 'co-agents',
            'co_agent_ids.*' => 'co-agent',
        ];
    }
}
