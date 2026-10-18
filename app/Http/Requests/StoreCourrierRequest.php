<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Courrier::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'arrival_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'origin_service' => ['required', 'string', 'max:255'],
            'courrier_type_id' => [
                'required',
                Rule::exists('courrier_types', 'id')->where('is_active', true),
            ],
            'subject' => ['required', 'string', 'max:5000'],
            'arrival_number' => ['nullable', 'string', 'max:100'],
            'division_id' => [
                'nullable',
                Rule::exists('divisions', 'id')->where('is_active', true),
            ],
            'division_assigned_at' => ['nullable', 'date', 'after_or_equal:arrival_date'],
            'contributing_division_ids' => ['nullable', 'array', 'max:10', 'prohibited_if:division_id,null'],
            'contributing_division_ids.*' => ['integer', 'distinct', Rule::exists('divisions', 'id')->where('is_active', true)],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'attachments' => 'pièces jointes',
            'attachments.*' => 'pièce jointe',
            'contributing_division_ids' => 'divisions contributrices',
            'contributing_division_ids.*' => 'division contributrice',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contributing_division_ids.prohibited_if' => "Choisissez d'abord la division pilote.",
        ];
    }
}
