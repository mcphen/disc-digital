<?php

namespace App\Services\Disc;

use App\Models\Courrier;

class CourrierPresenter
{
    public function __construct(private readonly CourrierDeadlineService $deadlines) {}

    /** @return array<string, mixed> */
    public function present(Courrier $courrier, bool $withEvents = false): array
    {
        $data = [
            'id' => $courrier->id,
            'registration_number' => $courrier->registration_number,
            'sequence_number' => $courrier->sequence_number,
            'registration_year' => $courrier->registration_year,
            'arrival_date' => $courrier->arrival_date?->toDateString(),
            'reference' => $courrier->reference,
            'origin_service' => $courrier->origin_service,
            'subject' => $courrier->subject,
            'arrival_number' => $courrier->arrival_number,
            'instructions' => $courrier->instructions,
            'notes' => $courrier->notes,
            'legacy_agent_name' => $courrier->legacy_agent_name,
            'division_assigned_at' => $courrier->division_assigned_at?->toDateString(),
            'agent_assigned_at' => $courrier->agent_assigned_at?->toDateString(),
            'submitted_at' => $courrier->submitted_at?->toDateString(),
            'division_validated_at' => $courrier->division_validated_at?->toDateString(),
            'signed_at' => $courrier->signed_at?->toDateString(),
            'status' => $courrier->status,
            'status_label' => $this->statusLabel($courrier->status),
            'agent_delay_days' => $courrier->agent_delay_days,
            'director_delay_days' => $courrier->director_delay_days,
            'type' => $courrier->type ? [
                'id' => $courrier->type->id,
                'code' => $courrier->type->code,
                'name' => $courrier->type->name,
                'requires_response' => $courrier->type->requires_response,
            ] : null,
            'division' => $courrier->division ? [
                'id' => $courrier->division->id,
                'code' => $courrier->division->code,
                'name' => $courrier->division->name,
            ] : null,
            'assigned_agent' => $courrier->assignedAgent ? [
                'id' => $courrier->assignedAgent->id,
                'name' => $courrier->assignedAgent->name,
            ] : null,
            'contributing_divisions' => $courrier->relationLoaded('contributingDivisions')
                ? $courrier->contributingDivisions->map(fn ($division) => $division->only(['id', 'code', 'name']))->values()
                : [],
            'co_agents' => $courrier->relationLoaded('coAgents')
                ? $courrier->coAgents->map(fn ($agent) => $agent->only(['id', 'name', 'division_id']))->values()
                : [],
            'division_validated_by' => $courrier->divisionValidatedBy ? [
                'id' => $courrier->divisionValidatedBy->id,
                'name' => $courrier->divisionValidatedBy->name,
            ] : null,
            'metrics' => $this->deadlines->calculate($courrier),
        ];

        if ($withEvents) {
            $data['events'] = $courrier->events->map(fn ($event) => [
                'id' => $event->id,
                'event' => $event->event,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'comment' => $event->comment,
                'created_at' => $event->created_at?->toIso8601String(),
                'user' => $event->user ? ['id' => $event->user->id, 'name' => $event->user->name] : null,
            ])->values();
            $data['attachments'] = $courrier->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'created_at' => $attachment->created_at?->toIso8601String(),
                'uploaded_by' => $attachment->uploadedBy
                    ? ['id' => $attachment->uploadedBy->id, 'name' => $attachment->uploadedBy->name]
                    : null,
            ])->values();
        }

        return $data;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Courrier::STATUS_REGISTERED => 'Enregistré',
            Courrier::STATUS_ASSIGNED_DIVISION => 'Imputé à une division',
            Courrier::STATUS_ASSIGNED_AGENT => 'En traitement',
            Courrier::STATUS_PENDING_DIVISION_REVIEW => 'En attente du Chef de Division',
            Courrier::STATUS_CORRECTION_REQUESTED => 'Correction demandée',
            Courrier::STATUS_DIVISION_VALIDATED => 'Validé par le Chef de Division',
            Courrier::STATUS_SUBMITTED => 'En attente de signature (ancien flux)',
            Courrier::STATUS_SIGNED => 'Signé / clôturé',
            Courrier::STATUS_CLOSED_NO_RESPONSE => 'Clôturé sans réponse',
            default => $status,
        };
    }
}
