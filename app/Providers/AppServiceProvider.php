<?php

namespace App\Providers;

use App\Models\Courrier;
use App\Models\Division;
use App\Models\User;
use App\Policies\CourrierPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Courrier::class, CourrierPolicy::class);
        Gate::define('manage-disc', fn (User $user) => $user->is_active && $user->role === 'admin');
        Gate::define('view-disc-reports', fn (User $user) => $user->is_active && $user->role !== 'agent');
        Gate::define('bulk-assign-courriers', fn (User $user) => $user->is_active && $user->hasAnyRole(['admin', 'director', 'division_head']));

        // Bilans trimestriels : Direction > division > agent.
        Gate::define('view-quarterly-direction', fn (User $user) => $user->is_active
            && $user->hasAnyRole(['admin', 'director', 'secretariat', 'auditor']));
        Gate::define('view-quarterly-division', fn (User $user, Division $division) => $user->is_active
            && ($user->hasAnyRole(['admin', 'director', 'secretariat', 'auditor'])
                || ($user->role === 'division_head' && $user->division_id === $division->id)));
        Gate::define('view-quarterly-agent', fn (User $user, User $agent) => $user->is_active
            && ($user->hasAnyRole(['admin', 'director'])
                || $user->is($agent)
                || ($user->role === 'division_head' && $user->division_id !== null && $user->division_id === $agent->division_id)));
    }
}
