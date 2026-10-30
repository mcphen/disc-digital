<?php

namespace App\Services\Disc;

use App\Models\Courrier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CourrierReportService
{
    public function __construct(private readonly CourrierDeadlineService $deadlines) {}

    /** @param array<string, mixed> $filters */
    public function rows(User $user, array $filters): Collection
    {
        return $this->query($user, $filters)->get()->map(function (Courrier $courrier) {
            $metrics = $this->deadlines->calculate($courrier);

            return [
                'id' => $courrier->id,
                'registration_number' => $courrier->registration_number,
                'arrival_date' => $courrier->arrival_date->toDateString(),
                'origin_service' => $courrier->origin_service,
                'subject' => $courrier->subject,
                'type' => $courrier->type->name,
                'division' => $courrier->division?->code,
                'agent' => $courrier->assignedAgent?->name ?? $courrier->legacy_agent_name,
                'contributing_divisions' => $courrier->contributingDivisions->pluck('code')->join(', ') ?: null,
                'co_agents' => $courrier->coAgents->pluck('name')->join(', ') ?: null,
                'produced_at' => $courrier->submitted_at?->toDateString(),
                'division_validated_at' => $courrier->division_validated_at?->toDateString(),
                'division_validated_by' => $courrier->divisionValidatedBy?->name,
                'status' => $courrier->status,
                'alert' => $metrics['current_alert'],
                'due_date' => $metrics['due_date'],
                'observation' => $metrics['observation'],
                'total_duration_days' => $metrics['total_duration_days'],
            ];
        });
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function summary(Collection $rows): array
    {
        $closedStatuses = [Courrier::STATUS_SIGNED, Courrier::STATUS_CLOSED_NO_RESPONSE];

        return [
            'total' => $rows->count(),
            'completed' => $rows->whereIn('status', $closedStatuses)->count(),
            'in_progress' => $rows->whereNotIn('status', $closedStatuses)->count(),
            'overdue' => $rows->where('alert', 'ROUGE')->count(),
            'by_division' => $this->breakdown($rows, 'division', 'Non imputée'),
            'by_type' => $this->breakdown($rows, 'type', 'Non défini'),
            'by_status' => $this->breakdown($rows, 'status', 'Non défini'),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function query(User $user, array $filters): Builder
    {
        return Courrier::query()
            ->visibleTo($user)
            ->with(['type', 'division', 'assignedAgent', 'divisionValidatedBy', 'contributingDivisions', 'coAgents'])
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('arrival_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('arrival_date', '<=', $date))
            ->when($filters['division'] ?? null, fn (Builder $query, int $id) => $query->involvingDivision($id))
            ->when($filters['type'] ?? null, fn (Builder $query, int $id) => $query->where('courrier_type_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('arrival_date')
            ->latest('sequence_number');
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function breakdown(Collection $rows, string $key, string $fallback): array
    {
        return $rows->groupBy(fn (array $row) => $row[$key] ?: $fallback)
            ->map(fn (Collection $group, string $label) => ['label' => $label, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values()
            ->all();
    }
}
