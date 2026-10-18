<?php

namespace App\Policies;

use App\Models\Courrier;
use App\Models\User;

class CourrierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Courrier $courrier): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'director', 'secretariat'])) {
            return true;
        }

        if ($courrier->type?->code === 'confidential') {
            return $courrier->involvesAgent($user->id);
        }

        if ($user->role === 'auditor') {
            return true;
        }

        return $user->role === 'division_head'
            ? $courrier->involvesDivision($user->division_id)
            : $courrier->involvesAgent($user->id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(['admin', 'director', 'secretariat']);
    }

    public function assign(User $user, Courrier $courrier): bool
    {
        // Seul le Chef de la division pilote répartit le dossier ; une division contributrice le consulte.
        return $this->view($user, $courrier)
            && ($user->hasAnyRole(['admin', 'director'])
                || ($user->role === 'division_head' && $courrier->division_id === $user->division_id));
    }

    public function submit(User $user, Courrier $courrier): bool
    {
        return $user->is_active
            && ($user->role === 'admin' || $courrier->assigned_agent_id === $user->id);
    }

    public function review(User $user, Courrier $courrier): bool
    {
        if (! $user->is_active || ! $this->view($user, $courrier)) {
            return false;
        }

        return $user->role === 'admin'
            || ($user->role === 'division_head' && $user->division_id === $courrier->division_id);
    }

    public function sign(User $user, Courrier $courrier): bool
    {
        return $user->is_active && $user->hasAnyRole(['admin', 'director']);
    }

    public function attach(User $user, Courrier $courrier): bool
    {
        return $this->view($user, $courrier) && $user->role !== 'auditor';
    }
}
