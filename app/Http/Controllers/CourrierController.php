<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourrierRequest;
use App\Models\Courrier;
use App\Models\CourrierType;
use App\Models\Division;
use App\Models\User;
use App\Services\Disc\CourrierPresenter;
use App\Services\Disc\NavigationCountsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourrierController extends Controller
{
    public function index(Request $request, CourrierPresenter $presenter, NavigationCountsService $navigation): Response
    {
        Gate::authorize('viewAny', Courrier::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:40'],
            'division' => ['nullable', 'integer', 'exists:divisions,id'],
            'type' => ['nullable', 'integer', 'exists:courrier_types,id'],
            'queue' => ['nullable', Rule::in(['unassigned', 'division_only', 'mine', 'review', 'corrections', 'signature', 'priority'])],
        ]);

        $priorityIds = ($filters['queue'] ?? null) === 'priority'
            ? $navigation->priorityIds($request->user())
            : [];

        $courriers = Courrier::query()
            ->visibleTo($request->user())
            ->with(['type', 'division', 'assignedAgent', 'divisionValidatedBy', 'contributingDivisions', 'coAgents'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('origin_service', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('arrival_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['division'] ?? null, fn (Builder $query, int $division) => $query->involvingDivision($division))
            ->when($filters['type'] ?? null, fn (Builder $query, int $type) => $query->where('courrier_type_id', $type))
            ->when(($filters['queue'] ?? null) === 'unassigned', fn (Builder $query) => $query
                ->whereNull('division_id')
                ->whereHas('type', fn (Builder $query) => $query->where('requires_response', true)))
            ->when(($filters['queue'] ?? null) === 'division_only', fn (Builder $query) => $query
                ->whereNotNull('division_id')
                ->when($request->user()->role === 'division_head', fn (Builder $query) => $query->where('division_id', $request->user()->division_id))
                ->whereNull('assigned_agent_id')
                ->whereNotIn('status', [Courrier::STATUS_SIGNED, Courrier::STATUS_CLOSED_NO_RESPONSE]))
            ->when(($filters['queue'] ?? null) === 'mine', fn (Builder $query) => $query
                ->involvingAgent($request->user()->id)
                ->whereNotIn('status', [Courrier::STATUS_SIGNED, Courrier::STATUS_CLOSED_NO_RESPONSE]))
            ->when(($filters['queue'] ?? null) === 'review', fn (Builder $query) => $query
                ->where('status', Courrier::STATUS_PENDING_DIVISION_REVIEW)
                ->when($request->user()->role === 'division_head', fn (Builder $query) => $query->where('division_id', $request->user()->division_id)))
            ->when(($filters['queue'] ?? null) === 'corrections', fn (Builder $query) => $query
                ->where('status', Courrier::STATUS_CORRECTION_REQUESTED)
                ->when($request->user()->role === 'agent', fn (Builder $query) => $query->where('assigned_agent_id', $request->user()->id)))
            ->when(($filters['queue'] ?? null) === 'signature', fn (Builder $query) => $query->whereIn('status', [
                Courrier::STATUS_DIVISION_VALIDATED,
                Courrier::STATUS_SUBMITTED,
            ]))
            ->when(($filters['queue'] ?? null) === 'priority', fn (Builder $query) => $query->whereIn('id', $priorityIds))
            ->latest('arrival_date')
            ->latest('sequence_number')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Courrier $courrier) => $presenter->present($courrier));

        return Inertia::render('courriers/Index', [
            'courriers' => $courriers,
            'filters' => $filters,
            'divisions' => $this->divisions(),
            'types' => $this->types(),
            'statuses' => $this->statuses(),
            'canCreate' => $request->user()->can('create', Courrier::class),
            'canBulkAssign' => $request->user()->can('bulk-assign-courriers'),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Courrier::class);

        return Inertia::render('courriers/Create', [
            'divisions' => $this->divisions(),
            'types' => $this->types(),
        ]);
    }

    public function store(StoreCourrierRequest $request): RedirectResponse
    {
        $storedPaths = [];

        try {
            $courrier = DB::transaction(function () use ($request, &$storedPaths) {
                $data = $request->safe()->except(['attachments', 'contributing_division_ids']);
                $type = CourrierType::findOrFail($data['courrier_type_id']);
                $year = (int) date('Y', strtotime($data['arrival_date']));
                $nextSequence = ((int) Courrier::withTrashed()
                    ->where('registration_year', $year)
                    ->lockForUpdate()
                    ->max('sequence_number')) + 1;

                $status = $type->requires_response
                    ? (! empty($data['division_id']) ? Courrier::STATUS_ASSIGNED_DIVISION : Courrier::STATUS_REGISTERED)
                    : Courrier::STATUS_CLOSED_NO_RESPONSE;

                $courrier = Courrier::create($data + [
                    'sequence_number' => $nextSequence,
                    'registration_year' => $year,
                    'status' => $status,
                    'agent_delay_days' => $type->agent_delay_days,
                    'director_delay_days' => $type->director_delay_days,
                    'created_by_id' => $request->user()->id,
                    'updated_by_id' => $request->user()->id,
                ]);

                $courrier->syncContributors($request->validated('contributing_division_ids') ?? [], []);

                $courrier->events()->create([
                    'user_id' => $request->user()->id,
                    'event' => 'created',
                    'to_status' => $status,
                    'comment' => 'Courrier enregistré dans DISC.',
                ]);

                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store("courriers/{$courrier->id}", 'local');
                    $storedPaths[] = $path;

                    $courrier->attachments()->create([
                        'uploaded_by_id' => $request->user()->id,
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'disk' => 'local',
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }

                return $courrier;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($storedPaths);

            throw $e;
        }

        return to_route('courriers.show', $courrier)->with('success', 'Le courrier a été enregistré.');
    }

    public function show(Request $request, Courrier $courrier, CourrierPresenter $presenter): Response
    {
        $courrier->load(['type', 'division', 'assignedAgent', 'divisionValidatedBy', 'contributingDivisions', 'coAgents', 'events.user', 'attachments.uploadedBy']);
        Gate::authorize('view', $courrier);
        $orderedCourriers = Courrier::query()
            ->visibleTo($request->user())
            ->latest('arrival_date')
            ->latest('sequence_number')
            ->get(['id', 'sequence_number', 'registration_year']);
        $currentIndex = $orderedCourriers->search(fn (Courrier $item) => $item->id === $courrier->id);
        $adjacent = ['previous' => null, 'next' => null];
        if ($currentIndex !== false) {
            $previous = $currentIndex > 0 ? $orderedCourriers->get($currentIndex - 1) : null;
            $next = $currentIndex < $orderedCourriers->count() - 1 ? $orderedCourriers->get($currentIndex + 1) : null;
            $adjacent = [
                'previous' => $previous ? ['id' => $previous->id, 'label' => $previous->registration_number] : null,
                'next' => $next ? ['id' => $next->id, 'label' => $next->registration_number] : null,
            ];
        }

        $agents = User::query()
            ->where('is_active', true)
            ->whereIn('role', ['agent', 'division_head'])
            ->orderBy('name')
            ->get(['id', 'name', 'division_id']);

        return Inertia::render('courriers/Show', [
            'courrier' => $presenter->present($courrier, true),
            'divisions' => $this->divisions(),
            'agents' => $agents,
            'backUrl' => $this->safeBackUrl($request),
            'adjacent' => $adjacent,
            'permissions' => [
                'assign' => $request->user()->can('assign', $courrier),
                'submit' => $request->user()->can('submit', $courrier),
                'review' => $request->user()->can('review', $courrier),
                'sign' => $request->user()->can('sign', $courrier),
                'attach' => $request->user()->can('attach', $courrier),
            ],
        ]);
    }

    private function safeBackUrl(Request $request): string
    {
        $backUrl = $request->string('return')->toString();

        if (strlen($backUrl) > 2000 || str_starts_with($backUrl, '//')) {
            return '/courriers';
        }

        return str_starts_with($backUrl, '/courriers') || str_starts_with($backUrl, '/reports')
            ? $backUrl
            : '/courriers';
    }

    /** @return Collection<int, Division> */
    private function divisions()
    {
        return Division::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
    }

    /** @return Collection<int, CourrierType> */
    private function types()
    {
        return CourrierType::where('is_active', true)->orderBy('id')->get([
            'id', 'code', 'name', 'requires_response', 'agent_delay_days', 'director_delay_days',
        ]);
    }

    /** @return array<string, string> */
    private function statuses(): array
    {
        return [
            Courrier::STATUS_REGISTERED => 'Enregistré',
            Courrier::STATUS_ASSIGNED_DIVISION => 'Affecté à une division',
            Courrier::STATUS_ASSIGNED_AGENT => 'En traitement',
            Courrier::STATUS_PENDING_DIVISION_REVIEW => 'En attente du Chef de Division',
            Courrier::STATUS_CORRECTION_REQUESTED => 'Correction demandée',
            Courrier::STATUS_DIVISION_VALIDATED => 'Validé par le Chef de Division',
            Courrier::STATUS_SUBMITTED => 'En attente de signature (ancien flux)',
            Courrier::STATUS_SIGNED => 'Signé / clôturé',
            Courrier::STATUS_CLOSED_NO_RESPONSE => 'Clôturé sans réponse',
        ];
    }
}
