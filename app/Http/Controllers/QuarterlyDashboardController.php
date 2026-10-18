<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use App\Services\Disc\QuarterlyScorecardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bilans trimestriels : chacun arrive sur le niveau qui le concerne (Direction, division ou
 * individuel) et peut descendre vers les niveaux inférieurs que son rôle lui ouvre.
 */
class QuarterlyDashboardController extends Controller
{
    public function index(Request $request, QuarterlyScorecardService $scorecards): Response|RedirectResponse
    {
        $user = $request->user();
        $query = $request->only('periode');

        if ($user->role === 'agent') {
            return redirect()->route('pilotage.agent', ['agent' => $user->id] + $query);
        }

        if ($user->role === 'division_head') {
            abort_if($user->division_id === null, 403, "Votre compte n'est rattaché à aucune division.");

            return redirect()->route('pilotage.division', ['division' => $user->division_id] + $query);
        }

        Gate::authorize('view-quarterly-direction');
        $period = $scorecards->period($request->query('periode'));

        return Inertia::render('pilotage/Direction', [
            'period' => $period,
            'periods' => $scorecards->periods(),
            'thresholds' => $scorecards->thresholds(),
            'scorecard' => $scorecards->direction($user, $period, $this->canSeeAgents($user)),
        ]);
    }

    public function division(Request $request, Division $division, QuarterlyScorecardService $scorecards): Response
    {
        $user = $request->user();
        Gate::authorize('view-quarterly-division', $division);
        $period = $scorecards->period($request->query('periode'));

        return Inertia::render('pilotage/Division', [
            'period' => $period,
            'periods' => $scorecards->periods(),
            'thresholds' => $scorecards->thresholds(),
            'division' => $division->only(['id', 'code', 'name']),
            'canViewDirection' => Gate::forUser($user)->allows('view-quarterly-direction'),
            'scorecard' => $scorecards->division($user, $division, $period, $this->canSeeAgents($user)),
        ]);
    }

    public function agent(Request $request, User $agent, QuarterlyScorecardService $scorecards): Response
    {
        $user = $request->user();
        Gate::authorize('view-quarterly-agent', $agent);
        $period = $scorecards->period($request->query('periode'));
        $agent->loadMissing('division:id,code,name');

        return Inertia::render('pilotage/Agent', [
            'period' => $period,
            'periods' => $scorecards->periods(),
            'thresholds' => $scorecards->thresholds(),
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'division' => $agent->division?->only(['id', 'code', 'name']),
            ],
            'isSelf' => $agent->is($user),
            'canViewDivision' => $agent->division !== null && Gate::forUser($user)->allows('view-quarterly-division', $agent->division),
            'scorecard' => $scorecards->agent($user, $agent, $period),
        ]);
    }

    /** Les taux individuels sont réservés à la hiérarchie (comme le rapport de performance). */
    private function canSeeAgents(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'director', 'division_head']);
    }
}
