<?php

namespace App\Http\Controllers;

use App\Models\Courrier;
use App\Services\Disc\CourrierDeadlineService;
use App\Services\Disc\CourrierPresenter;
use App\Services\Disc\QuarterlyScorecardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CourrierDeadlineService $deadlines,
        CourrierPresenter $presenter,
        QuarterlyScorecardService $scorecards,
    ): Response {
        $courriers = Courrier::query()
            ->visibleTo($request->user())
            ->with(['type', 'division', 'assignedAgent', 'divisionValidatedBy'])
            ->get();

        $withMetrics = $courriers->map(fn (Courrier $courrier) => [
            'courrier' => $courrier,
            'metrics' => $deadlines->calculate($courrier),
        ]);
        $requiringResponse = $withMetrics->filter(fn ($item) => $item['courrier']->type->requires_response);
        $signed = $requiringResponse->filter(fn ($item) => $item['courrier']->signed_at !== null);
        $onTime = $signed->filter(fn ($item) => $item['metrics']['observation'] === 'Dans les délais')->count();

        return Inertia::render('Dashboard', [
            'permissions' => [
                'create' => $request->user()->can('create', Courrier::class),
                'bulkAssign' => Gate::forUser($request->user())->allows('bulk-assign-courriers'),
                'reports' => Gate::forUser($request->user())->allows('view-disc-reports'),
                'review' => $request->user()->hasAnyRole(['admin', 'division_head']),
                'sign' => $request->user()->hasAnyRole(['admin', 'director']),
            ],
            'stats' => [
                'total' => $courriers->count(),
                'without_response' => $courriers->filter(fn ($courrier) => ! $courrier->type->requires_response)->count(),
                'requiring_response' => $requiringResponse->count(),
                'signed' => $signed->count(),
                'on_time' => $onTime,
                'yellow' => $withMetrics->where('metrics.current_alert', 'JAUNE')->count(),
                'red' => $withMetrics->where('metrics.current_alert', 'ROUGE')->count(),
                'pending_review' => $courriers->where('status', Courrier::STATUS_PENDING_DIVISION_REVIEW)->count(),
                'corrections_requested' => $courriers->where('status', Courrier::STATUS_CORRECTION_REQUESTED)->count(),
                'pending_signature' => $courriers->whereIn('status', [
                    Courrier::STATUS_DIVISION_VALIDATED,
                    Courrier::STATUS_SUBMITTED,
                ])->count(),
                'completion_rate' => $requiringResponse->count() > 0
                    ? round($signed->count() / $requiringResponse->count() * 100, 1)
                    : 0,
                'compliance_rate' => $signed->count() > 0
                    ? round($onTime / $signed->count() * 100, 1)
                    : 0,
            ],
            'quarter' => $scorecards->headline($request->user()),
            'thresholds' => $scorecards->thresholds(),
            'recentCourriers' => $courriers
                ->sortByDesc('arrival_date')
                ->take(8)
                ->map(fn ($courrier) => $presenter->present($courrier))
                ->values(),
        ]);
    }
}
