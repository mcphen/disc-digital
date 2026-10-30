<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkAssignCourriersRequest;
use App\Models\Courrier;
use App\Models\Division;
use App\Models\User;
use App\Services\Disc\CourrierPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BulkCourrierAssignmentController extends Controller
{
    private const ASSIGNABLE_STATUSES = [
        Courrier::STATUS_REGISTERED,
        Courrier::STATUS_ASSIGNED_DIVISION,
        Courrier::STATUS_ASSIGNED_AGENT,
        Courrier::STATUS_CORRECTION_REQUESTED,
    ];

    public function index(Request $request, CourrierPresenter $presenter): Response
    {
        Gate::authorize('bulk-assign-courriers');

        $defaultScope = $request->user()->role === 'division_head' ? 'division_only' : 'unassigned';
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'scope' => ['nullable', Rule::in(['unassigned', 'division_only', 'assigned', 'all'])],
            'division' => ['nullable', 'integer', 'exists:divisions,id'],
        ]);
        $scope = $filters['scope'] ?? $defaultScope;

        $courriers = Courrier::query()
            ->visibleTo($request->user())
            ->with(['type', 'division', 'assignedAgent', 'divisionValidatedBy'])
            ->whereHas('type', fn (Builder $query) => $query->where('requires_response', true))
            ->whereIn('status', self::ASSIGNABLE_STATUSES)
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('origin_service', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('arrival_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['division'] ?? null, fn (Builder $query, int $division) => $query->where('division_id', $division))
            ->when($scope === 'unassigned', fn (Builder $query) => $query->whereNull('division_id'))
            ->when($scope === 'division_only', fn (Builder $query) => $query->whereNotNull('division_id')->whereNull('assigned_agent_id'))
            ->when($scope === 'assigned', fn (Builder $query) => $query->whereNotNull('assigned_agent_id'))
            ->latest('arrival_date')
            ->latest('sequence_number')
            ->limit(250)
            ->get()
            ->map(fn (Courrier $courrier) => $presenter->present($courrier))
            ->values();

        return Inertia::render('courriers/BulkAssignment', [
            'courriers' => $courriers,
            'filters' => $filters + ['scope' => $scope],
            'divisions' => Division::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'agents' => User::query()
                ->where('is_active', true)
                ->whereIn('role', ['agent', 'division_head'])
                ->orderBy('name')
                ->get(['id', 'name', 'division_id']),
            'lockedDivisionId' => $request->user()->role === 'division_head' ? $request->user()->division_id : null,
        ]);
    }

    public function store(BulkAssignCourriersRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $ids = collect($data['courrier_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $user = $request->user();

        if ($user->role === 'division_head' && $user->division_id !== (int) $data['division_id']) {
            throw ValidationException::withMessages([
                'division_id' => 'Vous ne pouvez imputer des courriers que dans votre division.',
            ]);
        }

        $agent = null;
        if (! empty($data['assigned_agent_id'])) {
            $agent = User::query()
                ->where('is_active', true)
                ->whereIn('role', ['agent', 'division_head'])
                ->find($data['assigned_agent_id']);

            if (! $agent || $agent->division_id !== (int) $data['division_id']) {
                throw ValidationException::withMessages([
                    'assigned_agent_id' => "L'agent sélectionné n'appartient pas à cette division.",
                ]);
            }
        }

        $batchId = (string) Str::uuid();
        [$count, $snapshots] = DB::transaction(function () use ($data, $ids, $request, $user, $batchId) {
            $courriers = Courrier::query()
                ->with('type')
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($courriers->count() !== $ids->count()) {
                throw ValidationException::withMessages(['courrier_ids' => 'Un ou plusieurs courriers sont introuvables.']);
            }

            $snapshots = [];
            foreach ($courriers as $courrier) {
                if (! $user->can('assign', $courrier)) {
                    abort(403);
                }

                if (! $courrier->type->requires_response || ! in_array($courrier->status, self::ASSIGNABLE_STATUSES, true)) {
                    throw ValidationException::withMessages([
                        'courrier_ids' => "Le courrier {$courrier->registration_number} ne peut plus être imputé.",
                    ]);
                }

                if ($courrier->arrival_date->isAfter($data['division_assigned_at'])) {
                    throw ValidationException::withMessages([
                        'division_assigned_at' => "La date est antérieure à l'arrivée du courrier {$courrier->registration_number}.",
                    ]);
                }

                $from = $courrier->status;
                $snapshot = [
                    'courrier_id' => $courrier->id,
                    'division_id' => $courrier->getRawOriginal('division_id'),
                    'division_assigned_at' => $courrier->getRawOriginal('division_assigned_at'),
                    'assigned_agent_id' => $courrier->getRawOriginal('assigned_agent_id'),
                    'agent_assigned_at' => $courrier->getRawOriginal('agent_assigned_at'),
                    'instructions' => $courrier->getRawOriginal('instructions'),
                    'status' => $courrier->getRawOriginal('status'),
                    'updated_by_id' => $courrier->getRawOriginal('updated_by_id'),
                ];
                $to = ! empty($data['assigned_agent_id'])
                    ? Courrier::STATUS_ASSIGNED_AGENT
                    : Courrier::STATUS_ASSIGNED_DIVISION;
                $attributes = [
                    'division_id' => $data['division_id'],
                    'division_assigned_at' => $data['division_assigned_at'],
                    'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
                    'agent_assigned_at' => ! empty($data['assigned_agent_id']) ? $data['agent_assigned_at'] : null,
                    'status' => $to,
                    'updated_by_id' => $request->user()->id,
                ];
                if (array_key_exists('instructions', $data) && $data['instructions'] !== null) {
                    $attributes['instructions'] = $data['instructions'];
                }

                $courrier->update($attributes);
                $event = $courrier->events()->create([
                    'user_id' => $request->user()->id,
                    'event' => 'bulk_assigned',
                    'from_status' => $from,
                    'to_status' => $to,
                    'comment' => $data['comment'] ?? null,
                    'metadata' => [
                        'bulk' => true,
                        'batch_id' => $batchId,
                        'division_id' => (int) $data['division_id'],
                        'assigned_agent_id' => isset($data['assigned_agent_id']) ? (int) $data['assigned_agent_id'] : null,
                    ],
                ]);
                $snapshot['event_id'] = $event->id;
                $snapshots[] = $snapshot;
            }

            return [$courriers->count(), $snapshots];
        });

        $undo = [
            'token' => $batchId,
            'user_id' => $user->id,
            'count' => $count,
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
            'snapshots' => $snapshots,
        ];
        $request->session()->put('bulk_assignment_undo', $undo);

        return to_route('courriers.bulk-assignment.index')
            ->with('success', "{$count} courrier(s) ont été imputés avec succès.")
            ->with('bulk_undo', [
                'token' => $batchId,
                'count' => $count,
                'expires_at' => $undo['expires_at'],
            ]);
    }

    public function undo(Request $request): RedirectResponse
    {
        Gate::authorize('bulk-assign-courriers');

        $data = $request->validate(['token' => ['required', 'uuid']]);
        $undo = $request->session()->get('bulk_assignment_undo');

        if (! is_array($undo)
            || ($undo['user_id'] ?? null) !== $request->user()->id
            || ! hash_equals((string) ($undo['token'] ?? ''), $data['token'])
            || now()->isAfter($undo['expires_at'] ?? now()->subSecond())) {
            return back()->with('error', "Cette annulation n'est plus disponible.");
        }

        DB::transaction(function () use ($undo, $request) {
            foreach ($undo['snapshots'] as $snapshot) {
                $courrier = Courrier::query()->lockForUpdate()->findOrFail($snapshot['courrier_id']);
                Gate::authorize('assign', $courrier);

                $latestEvent = $courrier->events()->orderByDesc('id')->first();
                if (! $latestEvent
                    || $latestEvent->id !== $snapshot['event_id']
                    || $latestEvent->event !== 'bulk_assigned'
                    || ($latestEvent->metadata['batch_id'] ?? null) !== $undo['token']) {
                    throw ValidationException::withMessages([
                        'token' => "L'un des courriers a été modifié depuis l'imputation. L'annulation automatique est impossible.",
                    ]);
                }

                $currentStatus = $courrier->status;
                $courrier->update([
                    'division_id' => $snapshot['division_id'],
                    'division_assigned_at' => $snapshot['division_assigned_at'],
                    'assigned_agent_id' => $snapshot['assigned_agent_id'],
                    'agent_assigned_at' => $snapshot['agent_assigned_at'],
                    'instructions' => $snapshot['instructions'],
                    'status' => $snapshot['status'],
                    'updated_by_id' => $snapshot['updated_by_id'],
                ]);
                $courrier->events()->create([
                    'user_id' => $request->user()->id,
                    'event' => 'bulk_assignment_undone',
                    'from_status' => $currentStatus,
                    'to_status' => $snapshot['status'],
                    'comment' => 'Imputation en masse annulée immédiatement.',
                    'metadata' => ['bulk' => true, 'batch_id' => $undo['token']],
                ]);
            }
        });

        $request->session()->forget('bulk_assignment_undo');

        return to_route('courriers.bulk-assignment.index')
            ->with('success', "L'imputation de {$undo['count']} courrier(s) a été annulée.");
    }
}
