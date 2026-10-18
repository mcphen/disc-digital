<?php

namespace App\Services\Disc;

use App\Models\Courrier;
use App\Models\CourrierEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Indicateurs de performance du circuit de traitement des courriers.
 *
 * Toutes les durées sont en jours calendaires, comme dans CourrierDeadlineService.
 * Une étape n'est mesurée que si ses deux dates sont connues : les courriers importés
 * sans date intermédiaire sont exclus de l'étape concernée au lieu d'être comptés à zéro.
 * Un courrier imputé à plusieurs divisions ou agents compte pour chacun d'eux dans les
 * tableaux par division et par agent, une seule fois dans les totaux.
 */
class CourrierPerformanceService
{
    private const CLOSED_STATUSES = [Courrier::STATUS_SIGNED, Courrier::STATUS_CLOSED_NO_RESPONSE];

    /** Étapes du circuit, dans l'ordre, avec les dates qui les bornent. */
    private const STAGES = [
        'division_assignment' => ['label' => 'Imputation à une division', 'actor' => 'Secrétariat / Direction'],
        'agent_assignment' => ['label' => 'Attribution à un agent', 'actor' => 'Chef de Division'],
        'agent_processing' => ['label' => 'Rédaction de la réponse', 'actor' => 'Agent'],
        'division_review' => ['label' => 'Validation de division', 'actor' => 'Chef de Division (corrections comprises)'],
        'signature' => ['label' => 'Signature', 'actor' => 'Directeur'],
    ];

    /** Étape en cours pour chaque statut ouvert. */
    private const WAITING_STAGES = [
        Courrier::STATUS_REGISTERED => ['label' => 'À imputer à une division', 'actor' => 'Secrétariat / Direction'],
        Courrier::STATUS_ASSIGNED_DIVISION => ['label' => 'À attribuer à un agent', 'actor' => 'Chef de Division'],
        Courrier::STATUS_ASSIGNED_AGENT => ['label' => 'En rédaction', 'actor' => 'Agent'],
        Courrier::STATUS_CORRECTION_REQUESTED => ['label' => 'En correction', 'actor' => 'Agent'],
        Courrier::STATUS_PENDING_DIVISION_REVIEW => ['label' => 'En attente de validation', 'actor' => 'Chef de Division'],
        Courrier::STATUS_DIVISION_VALIDATED => ['label' => 'En attente de signature', 'actor' => 'Directeur'],
        Courrier::STATUS_SUBMITTED => ['label' => 'En attente de signature (ancien flux)', 'actor' => 'Directeur'],
    ];

    /** @var array<int, array<string, int|null>> */
    private array $dayCache = [];

    private const AGE_BUCKETS = [
        ['label' => '0–7 j', 'max' => 7],
        ['label' => '8–15 j', 'max' => 15],
        ['label' => '16–30 j', 'max' => 30],
        ['label' => '+30 j', 'max' => null],
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(User $user, array $filters, ?CarbonInterface $today = null): array
    {
        $this->dayCache = [];
        $today = $this->dayNumber(($today ?? now())->toDateString());
        $courriers = $this->query($user, $filters)->get();
        $withResponse = $courriers->filter(fn (Courrier $courrier) => (bool) $courrier->type?->requires_response);
        $open = $withResponse->reject(fn (Courrier $courrier) => in_array($courrier->status, self::CLOSED_STATUSES, true));
        $rejections = $this->rejectionCounts($withResponse);
        $waitingSince = $this->waitingSince($open);

        $backlog = $this->backlog($open, $waitingSince, $today);

        return [
            'summary' => $this->summary($withResponse, $open, $rejections, $backlog),
            'stages' => $this->stages($withResponse),
            'backlog' => $backlog,
            'compliance' => $this->compliance($withResponse),
            'corrections' => $this->corrections($withResponse, $rejections),
            'divisions' => $this->byDivision($withResponse, $rejections, $waitingSince, $today),
            'agents' => $this->canSeeAgents($user) ? $this->byAgent($withResponse, $waitingSince, $today) : null,
            'trend' => $this->trend($withResponse),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function query(User $user, array $filters): Builder
    {
        return Courrier::query()
            ->visibleTo($user)
            ->with(['type', 'division', 'assignedAgent', 'contributingDivisions', 'coAgents'])
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('arrival_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('arrival_date', '<=', $date))
            ->when($filters['division'] ?? null, fn (Builder $query, int $id) => $query->involvingDivision($id))
            ->when($filters['type'] ?? null, fn (Builder $query, int $id) => $query->where('courrier_type_id', $id));
    }

    private function canSeeAgents(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'director', 'division_head']);
    }

    /**
     * @param  Collection<int, Courrier>  $all
     * @param  Collection<int, Courrier>  $open
     * @param  array<int, int>  $rejections
     * @param  array<string, mixed>  $backlog
     * @return array<string, mixed>
     */
    private function summary(Collection $all, Collection $open, array $rejections, array $backlog): array
    {
        $signed = $all->filter(fn (Courrier $courrier) => $this->day($courrier, 'signed_at') !== null);
        $totals = $signed->map(fn (Courrier $courrier) => $this->span($courrier, 'arrival_date', 'signed_at'))->filter(fn ($days) => $days !== null);
        $onTime = $signed->filter(fn (Courrier $courrier) => $this->isOnTime($courrier))->count();
        $measurable = $signed->filter(fn (Courrier $courrier) => $this->isOnTime($courrier) !== null)->count();
        $bottleneck = collect($backlog['stages'])->sortByDesc(fn (array $stage) => [$stage['over_30'], $stage['count']])->first();

        return [
            'with_response' => $all->count(),
            'signed' => $signed->count(),
            'open' => $open->count(),
            'median_total_days' => $this->percentile($totals, 0.5),
            'on_time_rate' => $measurable ? round($onTime / $measurable * 100) : null,
            'stuck_over_30' => collect($backlog['stages'])->sum('over_30'),
            'bottleneck' => $bottleneck && $bottleneck['count'] > 0 ? $bottleneck['label'] : null,
            'correction_rate' => $this->correctionRate($all, $rejections),
        ];
    }

    /**
     * Durée de chaque étape terminée.
     *
     * @param  Collection<int, Courrier>  $courriers
     * @return array<int, array<string, mixed>>
     */
    private function stages(Collection $courriers): array
    {
        $durations = array_fill_keys(array_keys(self::STAGES), []);

        foreach ($courriers as $courrier) {
            foreach ($this->stageDurations($courrier) as $key => $days) {
                if ($days !== null) {
                    $durations[$key][] = $days;
                }
            }
        }

        $targets = [
            'agent_processing' => $this->percentile($courriers->pluck('agent_delay_days')->filter(), 0.5),
            'signature' => $this->percentile($courriers->pluck('director_delay_days')->filter(), 0.5),
        ];

        $stages = collect(self::STAGES)->map(function (array $stage, string $key) use ($durations, $targets) {
            $values = collect($durations[$key]);

            return $stage + [
                'key' => $key,
                'count' => $values->count(),
                'median' => $this->percentile($values, 0.5),
                'p90' => $this->percentile($values, 0.9),
                'average' => $values->isEmpty() ? null : round($values->avg(), 1),
                'target' => $targets[$key] ?? null,
            ];
        })->values();

        $longest = $stages->filter(fn (array $stage) => $stage['median'] !== null)->sortByDesc('median')->first();

        return $stages->map(fn (array $stage) => $stage + [
            'is_longest' => $longest !== null && $stage['key'] === $longest['key'] && $longest['median'] > 0,
        ])->all();
    }

    /** @return array<string, int|null> */
    private function stageDurations(Courrier $courrier): array
    {
        return [
            'division_assignment' => $this->span($courrier, 'arrival_date', 'division_assigned_at'),
            'agent_assignment' => $this->span($courrier, 'division_assigned_at', 'agent_assigned_at'),
            'agent_processing' => $this->span($courrier, 'agent_assigned_at', 'submitted_at'),
            'division_review' => $this->span($courrier, 'submitted_at', 'division_validated_at'),
            'signature' => $this->span($courrier, ['division_validated_at', 'submitted_at'], 'signed_at'),
        ];
    }

    /**
     * Courriers ouverts : où ils attendent, et depuis combien de temps.
     *
     * @param  Collection<int, Courrier>  $open
     * @param  array<int, int>  $waitingSince
     * @return array<string, mixed>
     */
    private function backlog(Collection $open, array $waitingSince, int $today): array
    {
        $ages = $open->mapWithKeys(fn (Courrier $courrier) => [
            $courrier->id => max(0, $this->days($waitingSince[$courrier->id], $today) ?? 0),
        ]);

        $stages = collect(self::WAITING_STAGES)->map(function (array $stage, string $status) use ($open, $ages) {
            $items = $open->where('status', $status);
            $stageAges = $items->map(fn (Courrier $courrier) => $ages[$courrier->id]);

            return $stage + [
                'status' => $status,
                'count' => $items->count(),
                'median_age' => $this->percentile($stageAges, 0.5),
                'max_age' => $stageAges->max(),
                'over_30' => $stageAges->filter(fn (int $age) => $age > 30)->count(),
                'buckets' => $this->buckets($stageAges),
            ];
        })
            // L'ancien flux n'apparaît que s'il reste des dossiers.
            ->reject(fn (array $stage) => $stage['status'] === Courrier::STATUS_SUBMITTED && $stage['count'] === 0)
            ->values();

        $oldest = $open
            ->sortByDesc(fn (Courrier $courrier) => $ages[$courrier->id])
            ->take(15)
            ->map(fn (Courrier $courrier) => [
                'id' => $courrier->id,
                'registration_number' => $courrier->registration_number,
                'subject' => $courrier->subject,
                'stage' => self::WAITING_STAGES[$courrier->status]['label'] ?? $courrier->status,
                'division' => $courrier->division?->code,
                'agent' => $courrier->assignedAgent?->name ?? $courrier->legacy_agent_name,
                'waiting_since' => gmdate('Y-m-d', $waitingSince[$courrier->id] * 86400),
                'age_days' => $ages[$courrier->id],
            ])
            ->values();

        return [
            'stages' => $stages->all(),
            'oldest' => $oldest->all(),
        ];
    }

    /**
     * Date d'entrée dans le statut actuel. On privilégie l'historique (qui couvre les
     * retours en correction et les re-soumissions), puis la date métier de l'étape.
     *
     * @param  Collection<int, Courrier>  $open
     * @return array<int, int>
     */
    private function waitingSince(Collection $open): array
    {
        $lastTransitions = $open->isEmpty() ? collect() : CourrierEvent::query()
            ->whereIn('courrier_id', $open->modelKeys())
            ->whereNotNull('to_status')
            ->selectRaw('courrier_id, to_status, MAX(created_at) as entered_at')
            ->groupBy('courrier_id', 'to_status')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->courrier_id}:{$row->to_status}" => $this->dayNumber((string) $row->entered_at)]);

        return $open->mapWithKeys(function (Courrier $courrier) use ($lastTransitions) {
            $field = match ($courrier->status) {
                Courrier::STATUS_ASSIGNED_DIVISION => 'division_assigned_at',
                Courrier::STATUS_ASSIGNED_AGENT => 'agent_assigned_at',
                Courrier::STATUS_PENDING_DIVISION_REVIEW, Courrier::STATUS_SUBMITTED => 'submitted_at',
                Courrier::STATUS_DIVISION_VALIDATED => 'division_validated_at',
                Courrier::STATUS_CORRECTION_REQUESTED => 'updated_at',
                default => 'arrival_date',
            };

            return [$courrier->id => $lastTransitions["{$courrier->id}:{$courrier->status}"]
                ?? $this->day($courrier, $field)
                ?? $this->day($courrier, 'arrival_date')];
        })->all();
    }

    /**
     * Respect des délais réglementaires, par acteur.
     *
     * @param  Collection<int, Courrier>  $courriers
     * @return array<int, array<string, mixed>>
     */
    private function compliance(Collection $courriers): array
    {
        $agent = $courriers->map(function (Courrier $courrier) {
            $days = $this->span($courrier, 'agent_assigned_at', 'submitted_at');

            return $days === null || ! $courrier->agent_delay_days ? null : $days <= $courrier->agent_delay_days;
        });
        $director = $courriers->map(function (Courrier $courrier) {
            $days = $this->span($courrier, ['division_validated_at', 'submitted_at'], 'signed_at');

            return $days === null || ! $courrier->director_delay_days ? null : $days <= $courrier->director_delay_days;
        });
        $global = $courriers->map(fn (Courrier $courrier) => $this->day($courrier, 'signed_at') !== null ? $this->isOnTime($courrier) : null);

        return [
            $this->rate('Agent', 'Rédaction dans le délai agent', $agent),
            $this->rate('Directeur', 'Signature dans le délai Directeur', $director),
            $this->rate('Global', 'Courrier signé dans le délai total', $global),
        ];
    }

    /**
     * @param  Collection<int, bool|null>  $results
     * @return array<string, mixed>
     */
    private function rate(string $label, string $description, Collection $results): array
    {
        $measured = $results->filter(fn ($result) => $result !== null);
        $onTime = $measured->filter()->count();

        return [
            'label' => $label,
            'description' => $description,
            'measured' => $measured->count(),
            'on_time' => $onTime,
            'late' => $measured->count() - $onTime,
            'rate' => $measured->isEmpty() ? null : round($onTime / $measured->count() * 100),
        ];
    }

    /**
     * @param  Collection<int, Courrier>  $courriers
     * @param  array<int, int>  $rejections
     * @return array<string, mixed>
     */
    private function corrections(Collection $courriers, array $rejections): array
    {
        $reviewed = $this->reviewed($courriers, $rejections);
        $counts = $reviewed->map(fn (Courrier $courrier) => $rejections[$courrier->id] ?? 0);

        return [
            'reviewed' => $reviewed->count(),
            'with_correction' => $counts->filter(fn (int $count) => $count > 0)->count(),
            'total_corrections' => $counts->sum(),
            'rate' => $this->correctionRate($courriers, $rejections),
            'average' => $reviewed->isEmpty() ? null : round($counts->avg(), 2),
            'max' => $counts->max(),
        ];
    }

    /**
     * Courriers passés au moins une fois par la validation de division.
     *
     * @param  Collection<int, Courrier>  $courriers
     * @param  array<int, int>  $rejections
     * @return Collection<int, Courrier>
     */
    private function reviewed(Collection $courriers, array $rejections): Collection
    {
        return $courriers->filter(fn (Courrier $courrier) => $this->day($courrier, 'division_validated_at') !== null
            || ($rejections[$courrier->id] ?? 0) > 0);
    }

    /**
     * @param  Collection<int, Courrier>  $courriers
     * @param  array<int, int>  $rejections
     */
    private function correctionRate(Collection $courriers, array $rejections): ?int
    {
        $reviewed = $this->reviewed($courriers, $rejections);

        if ($reviewed->isEmpty()) {
            return null;
        }

        return (int) round($reviewed->filter(fn (Courrier $courrier) => ($rejections[$courrier->id] ?? 0) > 0)->count() / $reviewed->count() * 100);
    }

    /**
     * @param  Collection<int, Courrier>  $courriers
     * @return array<int, int>
     */
    private function rejectionCounts(Collection $courriers): array
    {
        if ($courriers->isEmpty()) {
            return [];
        }

        return CourrierEvent::query()
            ->whereIn('courrier_id', $courriers->modelKeys())
            ->where('event', 'division_rejected')
            ->selectRaw('courrier_id, COUNT(*) as total')
            ->groupBy('courrier_id')
            ->pluck('total', 'courrier_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @param  Collection<int, Courrier>  $courriers
     * @param  array<int, int>  $rejections
     * @param  array<int, int>  $waitingSince
     * @return array<int, array<string, mixed>>
     */
    private function byDivision(Collection $courriers, array $rejections, array $waitingSince, int $today): array
    {
        $names = [];
        $groups = [];
        foreach ($courriers as $courrier) {
            $divisions = collect([$courrier->division])->concat($courrier->contributingDivisions)->filter()->unique('id');
            if ($divisions->isEmpty()) {
                $groups['Non affectée'][] = $courrier;
            }
            foreach ($divisions as $division) {
                $groups[$division->code][] = $courrier;
                $names[$division->code] = $division->name;
            }
        }

        return collect($groups)
            ->map(fn (array $group) => collect($group))
            ->map(function (Collection $group, string $label) use ($rejections, $waitingSince, $today, $names) {
                $signed = $group->filter(fn (Courrier $courrier) => $this->day($courrier, 'signed_at') !== null);
                $measurable = $signed->filter(fn (Courrier $courrier) => $this->isOnTime($courrier) !== null);
                $open = $group->filter(fn (Courrier $courrier) => isset($waitingSince[$courrier->id]));

                return [
                    'label' => $label,
                    'name' => $names[$label] ?? null,
                    'total' => $group->count(),
                    'open' => $open->count(),
                    'stuck_over_30' => $open->filter(fn (Courrier $courrier) => $this->days($waitingSince[$courrier->id], $today) > 30)->count(),
                    'signed' => $signed->count(),
                    'median_total_days' => $this->percentile(
                        $signed->map(fn (Courrier $courrier) => $this->span($courrier, 'arrival_date', 'signed_at'))->filter(fn ($days) => $days !== null),
                        0.5,
                    ),
                    'median_processing_days' => $this->percentile(
                        $group->map(fn (Courrier $courrier) => $this->span($courrier, 'agent_assigned_at', 'submitted_at'))->filter(fn ($days) => $days !== null),
                        0.5,
                    ),
                    'on_time_rate' => $measurable->isEmpty() ? null : (int) round($measurable->filter(fn (Courrier $courrier) => $this->isOnTime($courrier))->count() / $measurable->count() * 100),
                    'correction_rate' => $this->correctionRate($group, $rejections),
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['stuck_over_30'], $row['open']])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Courrier>  $courriers
     * @param  array<int, int>  $waitingSince
     * @return array<int, array<string, mixed>>
     */
    private function byAgent(Collection $courriers, array $waitingSince, int $today): array
    {
        $agents = [];
        $groups = [];
        foreach ($courriers as $courrier) {
            foreach (collect([$courrier->assignedAgent])->concat($courrier->coAgents)->filter()->unique('id') as $agent) {
                $agents[$agent->id] = $agent;
                $groups[$agent->id][] = $courrier;
            }
        }

        return collect($groups)
            ->map(fn (array $group) => collect($group))
            ->map(function (Collection $group, int $agentId) use ($waitingSince, $today, $agents) {
                $agent = $agents[$agentId];
                // Charge de l'agent : uniquement les dossiers qui sont actuellement entre ses mains.
                $inHand = $group->filter(fn (Courrier $courrier) => in_array($courrier->status, [
                    Courrier::STATUS_ASSIGNED_AGENT,
                    Courrier::STATUS_CORRECTION_REQUESTED,
                ], true));
                $processing = $group
                    ->map(fn (Courrier $courrier) => [
                        'days' => $this->span($courrier, 'agent_assigned_at', 'submitted_at'),
                        'delay' => $courrier->agent_delay_days,
                    ])
                    ->filter(fn (array $row) => $row['days'] !== null);
                $measurable = $processing->filter(fn (array $row) => $row['delay'] > 0);

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'division' => $agent->division?->code ?? $group->first()->division?->code,
                    'in_hand' => $inHand->count(),
                    'in_hand_over_delay' => $inHand->filter(function (Courrier $courrier) use ($waitingSince, $today) {
                        $start = $this->day($courrier, 'agent_assigned_at') ?? $waitingSince[$courrier->id] ?? null;

                        return $start && $courrier->agent_delay_days && $this->days($start, $today) > $courrier->agent_delay_days;
                    })->count(),
                    'submitted' => $processing->count(),
                    'median_processing_days' => $this->percentile($processing->pluck('days'), 0.5),
                    'on_time_rate' => $measurable->isEmpty() ? null : (int) round($measurable->filter(fn (array $row) => $row['days'] <= $row['delay'])->count() / $measurable->count() * 100),
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['in_hand_over_delay'], $row['in_hand']])
            ->values()
            ->all();
    }

    /**
     * Évolution mensuelle (12 derniers mois de signature présents dans la sélection).
     *
     * @param  Collection<int, Courrier>  $courriers
     * @return array<int, array<string, mixed>>
     */
    private function trend(Collection $courriers): array
    {
        return $courriers
            ->filter(fn (Courrier $courrier) => $this->day($courrier, 'signed_at') !== null)
            ->groupBy(fn (Courrier $courrier) => gmdate('Y-m', $this->day($courrier, 'signed_at') * 86400))
            ->sortKeys()
            ->take(-12)
            ->map(function (Collection $group, string $month) {
                $measurable = $group->filter(fn (Courrier $courrier) => $this->isOnTime($courrier) !== null);

                return [
                    'month' => $month,
                    'signed' => $group->count(),
                    'median_total_days' => $this->percentile(
                        $group->map(fn (Courrier $courrier) => $this->span($courrier, 'arrival_date', 'signed_at'))->filter(fn ($days) => $days !== null),
                        0.5,
                    ),
                    'on_time_rate' => $measurable->isEmpty() ? null : (int) round($measurable->filter(fn (Courrier $courrier) => $this->isOnTime($courrier))->count() / $measurable->count() * 100),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $ages
     * @return array<int, array{label: string, count: int}>
     */
    private function buckets(Collection $ages): array
    {
        $min = 0;

        return collect(self::AGE_BUCKETS)->map(function (array $bucket) use ($ages, &$min) {
            $count = $ages->filter(fn (int $age) => $age >= $min && ($bucket['max'] === null || $age <= $bucket['max']))->count();
            $min = ($bucket['max'] ?? 0) + 1;

            return ['label' => $bucket['label'], 'count' => $count];
        })->all();
    }

    /** Signé dans le délai total (agent + Directeur) ? null si non mesurable. */
    private function isOnTime(Courrier $courrier): ?bool
    {
        $delay = (int) $courrier->agent_delay_days + (int) $courrier->director_delay_days;
        $total = $this->span($courrier, 'arrival_date', 'signed_at');

        return $delay > 0 && $total !== null ? $total <= $delay : null;
    }

    /** Nombre de jours entre deux dates (première date renseignée parmi $from). */
    private function span(Courrier $courrier, string|array $from, string $to): ?int
    {
        $start = null;
        foreach ((array) $from as $field) {
            $start ??= $this->day($courrier, $field);
        }

        return $this->days($start, $this->day($courrier, $to));
    }

    private function days(?int $start, ?int $end): ?int
    {
        return $start === null || $end === null ? null : max(0, $end - $start);
    }

    /**
     * Date d'un champ en numéro de jour, lue une seule fois par courrier : passer par les
     * casts Eloquent recrée un objet Carbon à chaque accès, ce qui est très lent ici.
     */
    private function day(Courrier $courrier, string $field): ?int
    {
        $cache = &$this->dayCache[$courrier->id];
        if (! isset($cache) || ! array_key_exists($field, $cache)) {
            $raw = $courrier->getRawOriginal($field);
            $cache[$field] = $raw ? $this->dayNumber((string) $raw) : null;
        }

        return $cache[$field];
    }

    private function dayNumber(string $date): int
    {
        return intdiv((int) strtotime(substr($date, 0, 10).' 00:00:00 UTC'), 86400);
    }

    /** @param Collection<int, int|float> $values */
    private function percentile(Collection $values, float $percentile): int|float|null
    {
        $sorted = $values->filter(fn ($value) => $value !== null)->sort()->values();

        if ($sorted->isEmpty()) {
            return null;
        }

        $position = ($sorted->count() - 1) * $percentile;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);
        $value = $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($position - $lower);

        return round($value, 1) == (int) $value ? (int) $value : round($value, 1);
    }
}
