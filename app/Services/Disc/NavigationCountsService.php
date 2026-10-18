<?php

namespace App\Services\Disc;

use App\Models\Courrier;
use App\Models\User;

class NavigationCountsService
{
    public function __construct(private readonly CourrierDeadlineService $deadlines) {}

    /** @return array<string, int> */
    public function for(User $user): array
    {
        $courriers = Courrier::query()
            ->visibleTo($user)
            ->with(['type', 'coAgents:id'])
            ->get();
        // Les files d'action d'un Chef de Division ne portent que sur sa division pilote.
        $piloted = $user->role === 'division_head' ? $courriers->where('division_id', $user->division_id) : $courriers;

        return [
            'unassigned' => $courriers->filter(fn (Courrier $courrier) => $courrier->type->requires_response && $courrier->division_id === null)->count(),
            'division_only' => $piloted->filter(fn (Courrier $courrier) => $courrier->division_id !== null && $courrier->assigned_agent_id === null && ! in_array($courrier->status, [Courrier::STATUS_SIGNED, Courrier::STATUS_CLOSED_NO_RESPONSE], true))->count(),
            'mine' => $courriers->filter(fn (Courrier $courrier) => $courrier->involvesAgent($user->id))->whereNotIn('status', [Courrier::STATUS_SIGNED, Courrier::STATUS_CLOSED_NO_RESPONSE])->count(),
            'review' => $piloted->where('status', Courrier::STATUS_PENDING_DIVISION_REVIEW)->count(),
            'corrections' => $courriers->where('status', Courrier::STATUS_CORRECTION_REQUESTED)
                ->when($user->role === 'agent', fn ($courriers) => $courriers->where('assigned_agent_id', $user->id))
                ->count(),
            'signature' => $courriers->whereIn('status', [Courrier::STATUS_DIVISION_VALIDATED, Courrier::STATUS_SUBMITTED])->count(),
            'priority' => $courriers->filter(fn (Courrier $courrier) => $this->deadlines->calculate($courrier)['current_alert'] === 'ROUGE')->count(),
        ];
    }

    /** @return array<int, int> */
    public function priorityIds(User $user): array
    {
        return Courrier::query()
            ->visibleTo($user)
            ->with('type')
            ->get()
            ->filter(fn (Courrier $courrier) => $this->deadlines->calculate($courrier)['current_alert'] === 'ROUGE')
            ->pluck('id')
            ->all();
    }
}
