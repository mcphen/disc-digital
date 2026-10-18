<?php

namespace App\Services\Disc;

use App\Models\Courrier;
use Carbon\CarbonInterface;

class CourrierDeadlineService
{
    public function __construct(private readonly DiscSettingsService $settings) {}

    /** @return array<string, mixed> */
    public function calculate(Courrier $courrier, ?CarbonInterface $today = null): array
    {
        $today ??= now()->startOfDay();
        $requiresResponse = (bool) $courrier->type->requires_response;

        if (! $requiresResponse) {
            return [
                'agent_duration_days' => null,
                'director_duration_days' => null,
                'total_duration_days' => null,
                'due_date' => null,
                'global_gap_days' => null,
                'agent_alert' => null,
                'director_alert' => null,
                'global_alert' => null,
                'current_alert' => null,
                'observation' => 'Ne nécessite pas de réponse',
            ];
        }

        $agentDuration = $this->duration($courrier->agent_assigned_at, $courrier->submitted_at);
        $directorStart = $courrier->division_validated_at ?? $courrier->submitted_at;
        $directorDuration = $this->duration($directorStart, $courrier->signed_at);
        $totalDuration = $this->duration($courrier->arrival_date, $courrier->signed_at);
        $totalDelay = (int) $courrier->agent_delay_days + (int) $courrier->director_delay_days;
        $dueDate = $totalDelay > 0 ? $courrier->arrival_date->copy()->addDays($totalDelay) : null;
        $gap = $courrier->signed_at && $dueDate
            ? $this->signedDaysBetween($dueDate, $courrier->signed_at)
            : null;

        $agentAlert = $courrier->submitted_at
            ? null
            : $this->alert($courrier->agent_assigned_at, $courrier->agent_delay_days, $today);
        $directorAlert = $courrier->signed_at
            ? null
            : $this->alert($directorStart, $courrier->director_delay_days, $today);
        $globalAlert = $courrier->signed_at
            ? null
            : $this->alert($courrier->arrival_date, $totalDelay, $today);

        return [
            'agent_duration_days' => $agentDuration,
            'director_duration_days' => $directorDuration,
            'total_duration_days' => $totalDuration,
            'due_date' => $dueDate?->toDateString(),
            'global_gap_days' => $gap,
            'agent_alert' => $agentAlert,
            'director_alert' => $directorAlert,
            'global_alert' => $globalAlert,
            'current_alert' => $directorStart ? $directorAlert : $globalAlert,
            'observation' => $courrier->signed_at
                ? ($gap !== null && $gap <= 0 ? 'Dans les délais' : 'Hors délais')
                : null,
        ];
    }

    private function alert(?CarbonInterface $start, ?int $allowedDays, CarbonInterface $today): ?string
    {
        if (! $start || ! $allowedDays || $allowedDays < 1) {
            return null;
        }

        $ratio = max(0, $this->signedDaysBetween($start, $today)) / $allowedDays;

        if ($ratio <= $this->settings->float('alert_green_max', config('disc.alert_thresholds.green_max'))) {
            return 'VERT';
        }

        if ($ratio <= $this->settings->float('alert_yellow_max', config('disc.alert_thresholds.yellow_max'))) {
            return 'JAUNE';
        }

        return 'ROUGE';
    }

    private function duration(?CarbonInterface $start, ?CarbonInterface $end): ?int
    {
        return $start && $end ? max(0, $this->signedDaysBetween($start, $end)) : null;
    }

    private function signedDaysBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) floor($start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay(), false));
    }
}
