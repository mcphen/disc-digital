<?php

namespace App\Services\Disc;

use App\Models\Courrier;
use App\Models\CourrierNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class DeadlineNotificationService
{
    public function __construct(private readonly CourrierDeadlineService $deadlines) {}

    /** @return array{created: int, examined: int} */
    public function dispatch(): array
    {
        $created = 0;
        $examined = 0;

        Courrier::query()
            ->with(['type', 'division', 'assignedAgent', 'contributingDivisions:id', 'coAgents:id'])
            ->whereNotIn('status', [Courrier::STATUS_SIGNED, Courrier::STATUS_CLOSED_NO_RESPONSE])
            ->chunkById(100, function (Collection $courriers) use (&$created, &$examined) {
                foreach ($courriers as $courrier) {
                    $examined++;
                    $metrics = $this->deadlines->calculate($courrier);
                    $level = $metrics['current_alert'];
                    if (! in_array($level, ['JAUNE', 'ROUGE'], true)) {
                        continue;
                    }

                    $message = $level === 'ROUGE'
                        ? "Le courrier {$courrier->registration_number} a dépassé son délai de traitement."
                        : "Le courrier {$courrier->registration_number} approche de son échéance.";

                    foreach ($this->recipients($courrier) as $user) {
                        $notification = CourrierNotification::firstOrCreate(
                            ['courrier_id' => $courrier->id, 'user_id' => $user->id, 'level' => $level],
                            ['message' => $message],
                        );
                        $created += $notification->wasRecentlyCreated ? 1 : 0;
                    }
                }
            });

        return compact('created', 'examined');
    }

    /** @return Collection<int, User> */
    private function recipients(Courrier $courrier): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($courrier) {
                $query->whereIn('role', ['admin', 'director', 'secretariat']);
                // Agent responsable et co-agents.
                $agentIds = $courrier->coAgents->pluck('id')->push($courrier->assigned_agent_id)->filter();
                if ($agentIds->isNotEmpty()) {
                    $query->orWhereIn('id', $agentIds);
                }
                // Chefs de la division pilote et des divisions contributrices.
                $divisionIds = $courrier->contributingDivisions->pluck('id')->push($courrier->division_id)->filter();
                if ($divisionIds->isNotEmpty()) {
                    $query->orWhere(function ($query) use ($divisionIds) {
                        $query->where('role', 'division_head')->whereIn('division_id', $divisionIds);
                    });
                }
            })
            ->get()
            ->unique('id')
            ->values();
    }
}
