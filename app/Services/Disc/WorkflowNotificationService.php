<?php

namespace App\Services\Disc;

use App\Models\Courrier;
use App\Models\CourrierNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class WorkflowNotificationService
{
    public function reviewRequested(Courrier $courrier): void
    {
        $this->notify(
            $this->divisionReviewers($courrier),
            $courrier,
            'REVIEW',
            "Le courrier {$courrier->registration_number} attend votre validation.",
        );
    }

    public function correctionRequested(Courrier $courrier, ?string $comment = null): void
    {
        $this->notify(
            $this->agents($courrier),
            $courrier,
            'CORRECT',
            "Une correction est demandée sur le courrier {$courrier->registration_number}."
                .($comment ? ' Motif : '.str($comment)->squish()->limit(150) : ''),
        );
    }

    public function approved(Courrier $courrier): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->whereIn('role', ['admin', 'director'])
            ->get();

        $this->notify(
            $recipients->concat($this->agents($courrier))->concat($this->contributingHeads($courrier))->unique('id')->values(),
            $courrier,
            'APPROVED',
            "Le courrier {$courrier->registration_number} a été validé par le Chef de Division.",
        );
    }

    /**
     * Agent responsable et co-agents actifs.
     *
     * @return Collection<int, User>
     */
    private function agents(Courrier $courrier): Collection
    {
        return collect([$courrier->assignedAgent])
            ->concat($courrier->coAgents)
            ->filter(fn (?User $user) => $user?->is_active)
            ->unique('id')
            ->values();
    }

    /**
     * Chefs des divisions contributrices, informés de la validation du dossier qu'ils suivent.
     *
     * @return Collection<int, User>
     */
    private function contributingHeads(Courrier $courrier): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where('role', 'division_head')
            ->whereIn('division_id', $courrier->contributingDivisions()->pluck('divisions.id'))
            ->get();
    }

    /** @return Collection<int, User> */
    private function divisionReviewers(Courrier $courrier): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($courrier) {
                $query->where('role', 'admin');
                $query->orWhere(function ($query) use ($courrier) {
                    $query->where('role', 'division_head')
                        ->where('division_id', $courrier->division_id);
                });
            })
            ->get();
    }

    /** @param Collection<int, User> $users */
    private function notify(Collection $users, Courrier $courrier, string $level, string $message): void
    {
        foreach ($users as $user) {
            CourrierNotification::updateOrCreate(
                ['courrier_id' => $courrier->id, 'user_id' => $user->id, 'level' => $level],
                ['message' => $message, 'read_at' => null],
            );
        }
    }
}
