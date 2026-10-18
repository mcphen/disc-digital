<?php

namespace App\Services\Disc;

use App\Models\Courrier;
use App\Models\Division;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Bilans trimestriels : Directeur (Direction), Chef de Division (sa division), agent (lui-même).
 *
 * L'unité de mesure est le trimestre civil (T1 = janvier–mars, T2 = avril–juin, ...).
 * Un courrier compte dans le trimestre où tombe son échéance, et dans celui-là seulement.
 * À la fin du trimestre, chaque courrier échu est soit traité dans le délai, soit traité en
 * retard, soit toujours en attente : le pourcentage est alors définitif. Un courrier rendu
 * après coup passe de « non traité » à « en retard », il ne redevient jamais « dans le délai ».
 *
 * Trois échéances, en jours calendaires comme dans CourrierDeadlineService :
 * - rédaction : attribution à l'agent + délai agent, tenue si le projet est soumis à temps
 *   (date de première soumission, une correction demandée ne la repousse pas) ;
 * - signature : validation de division (ou soumission) + délai Directeur ;
 * - globale : arrivée + délai agent + délai Directeur, tenue si le courrier est signé à temps.
 *
 * Un courrier imputé à plusieurs divisions ou agents compte, avec la même échéance, pour chaque
 * division (pilote et contributrices) et chaque agent (responsable et co-agents). Le total de la
 * Direction le compte une seule fois.
 */
class QuarterlyScorecardService
{
    private const MONTHS = [1 => 'janvier – mars', 2 => 'avril – juin', 3 => 'juillet – septembre', 4 => 'octobre – décembre'];

    /** Échéance et date de réalisation de chaque étape mesurée. */
    private const STAGES = [
        'drafting' => ['deadline' => 'drafting_deadline', 'done' => 'submitted', 'label' => 'Rédaction dans le délai agent'],
        'signature' => ['deadline' => 'signature_deadline', 'done' => 'signed', 'label' => 'Signature dans le délai Directeur'],
        'global' => ['deadline' => 'global_deadline', 'done' => 'signed', 'label' => 'Courriers signés dans le délai total'],
    ];

    private int $today;

    /** @var Collection<int, array<string, mixed>> */
    private Collection $rows;

    /** Période demandée (« 2026-T3 »), ou trimestre en cours si absente, invalide ou future. */
    public function period(?string $key, ?CarbonInterface $today = null): array
    {
        $today ??= now();
        $current = [(int) $today->year, (int) $today->quarter];

        if ($key && preg_match('/^(\d{4})-T([1-4])$/', $key, $matches)) {
            $requested = [(int) $matches[1], (int) $matches[2]];

            if ($requested[0] >= 2000 && $requested <= $current) {
                return $this->makePeriod(...$requested);
            }
        }

        return $this->makePeriod(...$current);
    }

    /**
     * Trimestres proposés dans le sélecteur, du plus récent au plus ancien.
     *
     * @return array<int, array<string, mixed>>
     */
    public function periods(?CarbonInterface $today = null): array
    {
        $today ??= now();
        $firstYear = min((int) $today->year, (int) (Courrier::min('registration_year') ?? $today->year));
        $periods = [];

        for ($year = (int) $today->year; $year >= $firstYear; $year--) {
            for ($quarter = 4; $quarter >= 1; $quarter--) {
                if ([$year, $quarter] <= [(int) $today->year, (int) $today->quarter]) {
                    $period = $this->makePeriod($year, $quarter);
                    $periods[] = ['key' => $period['key'], 'label' => $period['label'], 'is_current' => $period['is_current']];
                }
            }
        }

        return $periods;
    }

    /**
     * Vue du Directeur : toute la Direction, chaque division, chaque agent.
     *
     * @return array<string, mixed>
     */
    public function direction(User $viewer, array $period, bool $withAgents): array
    {
        $this->load($period);
        $previous = $this->previous($period);
        $rows = $this->rows;

        $card = $this->card($rows, $period, 'global', ['global', 'drafting', 'signature']);
        $divisions = $this->divisionRows($period, $previous);
        $agents = $withAgents ? $this->agentRows($viewer, $rows, $this->agentsFor($rows, null), $period, $previous) : null;

        return $card + [
            'divisions' => $divisions,
            'agents' => $agents,
            'late' => $this->lateItems($rows, $period, 'global', $viewer),
            'highlights' => $this->directionHighlights($card, $divisions, $agents, $period),
        ];
    }

    /**
     * Vue du Chef de Division : sa division et ses agents.
     *
     * @return array<string, mixed>
     */
    public function division(User $viewer, Division $division, array $period, bool $withAgents): array
    {
        $this->load($period);
        $previous = $this->previous($period);
        $rows = $this->forDivision($this->rows, $division->id);

        $card = $this->card($rows, $period, 'division', ['drafting', 'global']);
        $direction = $this->score($this->rows, $period, 'drafting');
        $agents = $withAgents ? $this->agentRows($viewer, $rows, $this->agentsFor($rows, $division->id), $period, $previous) : null;

        return $card + [
            'direction_rate' => $direction['rate'],
            'agents' => $agents,
            'late' => $this->lateItems($rows, $period, 'drafting', $viewer),
            'highlights' => $this->divisionHighlights($division, $card, $direction, $agents, $period),
        ];
    }

    /**
     * Vue individuelle : les courriers attribués à l'agent.
     *
     * @return array<string, mixed>
     */
    public function agent(User $viewer, User $agent, array $period): array
    {
        $this->load($period);
        $rows = $this->forAgent($this->rows, $agent->id);

        $card = $this->card($rows, $period, 'agent', ['drafting']);
        $division = $agent->division_id
            ? $this->score($this->forDivision($this->rows, $agent->division_id), $period, 'drafting')
            : null;

        return $card + [
            'division_rate' => $division['rate'] ?? null,
            'late' => $this->lateItems($rows, $period, 'drafting', $viewer),
            'highlights' => $this->agentHighlights($card, $division, $period),
        ];
    }

    /**
     * Résumé affiché sur la page d'accueil : trimestre en cours et trimestre précédent.
     *
     * @return array<string, mixed>|null
     */
    public function headline(User $user): ?array
    {
        $period = $this->period(null);
        $previous = $this->previous($period);
        $this->load($period);

        [$rows, $stage, $scope] = match (true) {
            $user->role === 'agent' => [$this->forAgent($this->rows, $user->id), 'drafting', 'Vos courriers'],
            $user->role === 'division_head' && $user->division_id !== null => [
                $this->forDivision($this->rows, $user->division_id), 'drafting', 'Votre division',
            ],
            $user->role !== 'division_head' => [$this->rows, 'global', 'Direction'],
            default => [null, null, null],
        };

        if ($rows === null) {
            return null;
        }

        $current = $this->score($rows, $period, $stage);
        $last = $this->score($rows, $previous, $stage);

        return [
            'scope' => $scope,
            'stage' => self::STAGES[$stage]['label'],
            'period' => $period['label'],
            'current' => $current,
            'previous' => ['label' => $previous['label']] + $last,
            'remaining' => $current['not_due'],
            'end_date' => $period['end_date'],
        ];
    }

    /** @return array<string, mixed> */
    public function thresholds(): array
    {
        return [
            'target' => $this->target(),
            'alert' => $this->alertThreshold(),
            'min_sample' => $this->minSample(),
            'significant_change' => $this->significantChange(),
        ];
    }

    /** @return array<string, mixed> */
    private function makePeriod(int $year, int $quarter): array
    {
        $start = sprintf('%d-%02d-01', $year, $quarter * 3 - 2);
        $end = date('Y-m-t', strtotime(sprintf('%d-%02d-01', $year, $quarter * 3)));
        $today = now()->toDateString();

        return [
            'key' => "{$year}-T{$quarter}",
            'year' => $year,
            'quarter' => $quarter,
            'label' => "T{$quarter} {$year}",
            'months' => self::MONTHS[$quarter],
            'start_date' => $start,
            'end_date' => $end,
            'start' => $this->dayNumber($start),
            'end' => $this->dayNumber($end),
            'is_current' => $today >= $start && $today <= $end,
            'is_future' => $today < $start,
        ];
    }

    /**
     * Courriers où la division est pilote ou contributrice.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function forDivision(Collection $rows, int $divisionId): Collection
    {
        return $rows->filter(fn (array $row) => in_array($divisionId, $row['division_ids'], true));
    }

    /**
     * Courriers où l'agent est responsable ou co-agent.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function forAgent(Collection $rows, int $agentId): Collection
    {
        return $rows->filter(fn (array $row) => in_array($agentId, $row['agent_ids'], true));
    }

    /** @return array<string, mixed> */
    private function previous(array $period): array
    {
        return $period['quarter'] === 1
            ? $this->makePeriod($period['year'] - 1, 4)
            : $this->makePeriod($period['year'], $period['quarter'] - 1);
    }

    /**
     * Charge, une seule fois, les courriers à réponse utiles à l'année affichée et à la
     * précédente (un courrier peut arriver une année et être échu l'année suivante).
     */
    private function load(array $period): void
    {
        $this->today = $this->dayNumber(now()->toDateString());

        $this->rows = Courrier::query()
            ->select([
                'id', 'sequence_number', 'registration_year', 'subject', 'courrier_type_id', 'division_id',
                'assigned_agent_id', 'status', 'arrival_date', 'division_assigned_at', 'agent_assigned_at',
                'submitted_at', 'division_validated_at', 'signed_at', 'agent_delay_days', 'director_delay_days',
            ])
            ->whereHas('type', fn (Builder $query) => $query->where('requires_response', true))
            ->where('status', '!=', Courrier::STATUS_CLOSED_NO_RESPONSE)
            ->whereDate('arrival_date', '>=', ($period['year'] - 1).'-01-01')
            ->whereDate('arrival_date', '<=', $period['year'].'-12-31')
            ->with(['type:id,code', 'contributingDivisions:id', 'coAgents:id'])
            ->withCount(['events as rejections' => fn (Builder $query) => $query->reorder()->where('event', 'division_rejected')])
            ->get()
            ->map(function (Courrier $courrier) {
                $agentDelay = (int) $courrier->agent_delay_days;
                $directorDelay = (int) $courrier->director_delay_days;
                $row = [
                    'id' => $courrier->id,
                    'number' => $courrier->registration_number,
                    'subject' => $courrier->subject,
                    'confidential' => $courrier->type?->code === 'confidential',
                    // Pilote / responsable en premier, puis contributeurs.
                    'division_ids' => collect([$courrier->division_id])->concat($courrier->contributingDivisions->pluck('id'))->filter()->unique()->values()->all(),
                    'agent_ids' => collect([$courrier->assigned_agent_id])->concat($courrier->coAgents->pluck('id'))->filter()->unique()->values()->all(),
                    'status' => $courrier->status,
                    'rejections' => (int) $courrier->rejections,
                ];

                foreach ([
                    'arrival' => 'arrival_date',
                    'division_assigned' => 'division_assigned_at',
                    'agent_assigned' => 'agent_assigned_at',
                    'submitted' => 'submitted_at',
                    'validated' => 'division_validated_at',
                    'signed' => 'signed_at',
                ] as $key => $field) {
                    $raw = $courrier->getRawOriginal($field);
                    $row[$key] = $raw ? $this->dayNumber((string) $raw) : null;
                }

                $signatureStart = $row['validated'] ?? $row['submitted'];

                return $row + [
                    'drafting_deadline' => $row['agent_assigned'] !== null && $agentDelay > 0 ? $row['agent_assigned'] + $agentDelay : null,
                    'signature_deadline' => $signatureStart !== null && $directorDelay > 0 ? $signatureStart + $directorDelay : null,
                    'global_deadline' => $agentDelay + $directorDelay > 0 ? $row['arrival'] + $agentDelay + $directorDelay : null,
                ];
            });
    }

    /**
     * Taux principal(aux), comparaison au trimestre précédent, activité et année en cours.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $stages  la première est le taux mis en avant
     * @return array<string, mixed>
     */
    private function card(Collection $rows, array $period, string $mode, array $stages): array
    {
        $previous = $this->previous($period);
        $scores = [];

        foreach ($stages as $stage) {
            $scores[$stage] = $this->withTrend(
                $this->score($rows, $period, $stage),
                $this->score($rows, $previous, $stage),
            ) + ['key' => $stage, 'label' => self::STAGES[$stage]['label']];
        }

        return [
            'scores' => array_values($scores),
            'activity' => $this->activity($rows, $period, $mode),
            'quarters' => collect([1, 2, 3, 4])->map(function (int $quarter) use ($rows, $period, $stages) {
                $item = $this->makePeriod($period['year'], $quarter);
                $score = $item['is_future'] ? null : $this->score($rows, $item, $stages[0]);

                return [
                    'key' => $item['key'],
                    'label' => "T{$quarter}",
                    'months' => $item['months'],
                    'is_current' => $item['is_current'],
                    'is_future' => $item['is_future'],
                    'rate' => $score['rate'] ?? null,
                    'measured' => $score['measured'] ?? 0,
                    'standing' => $score['standing'] ?? 'none',
                ];
            })->all(),
        ];
    }

    /**
     * Courriers dont l'échéance de l'étape tombe dans la période, classés par résultat.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function score(Collection $rows, array $period, string $stage): array
    {
        $counts = ['on_time' => 0, 'late' => 0, 'overdue' => 0, 'not_due' => 0];
        $deadlineKey = self::STAGES[$stage]['deadline'];

        foreach ($rows as $row) {
            $deadline = $row[$deadlineKey];

            if ($deadline !== null && $deadline >= $period['start'] && $deadline <= $period['end']) {
                $counts[$this->outcome($row, $stage)]++;
            }
        }

        $measured = $counts['on_time'] + $counts['late'] + $counts['overdue'];
        $rate = $measured > 0 ? (int) round($counts['on_time'] / $measured * 100) : null;

        return $counts + [
            'measured' => $measured,
            'rate' => $rate,
            'standing' => $this->standing($rate, $measured),
        ];
    }

    private function outcome(array $row, string $stage): string
    {
        $deadline = $row[self::STAGES[$stage]['deadline']];
        $done = $row[self::STAGES[$stage]['done']];

        if ($done !== null) {
            return $done <= $deadline ? 'on_time' : 'late';
        }

        return $deadline < $this->today ? 'overdue' : 'not_due';
    }

    /** @return array<string, mixed> */
    private function withTrend(array $current, array $previous): array
    {
        $delta = $current['rate'] !== null && $previous['rate'] !== null ? $current['rate'] - $previous['rate'] : null;

        return $current + [
            'previous_rate' => $previous['rate'],
            'previous_measured' => $previous['measured'],
            'delta' => $delta,
            'significant_change' => $delta !== null
                && abs($delta) >= $this->significantChange()
                && $current['measured'] >= $this->minSample()
                && $previous['measured'] >= $this->minSample(),
        ];
    }

    /**
     * good : objectif atteint · watch : sous l'objectif · critical : sous le seuil d'alerte ·
     * limited : trop peu de courriers pour conclure · none : aucun courrier échu.
     */
    private function standing(?int $rate, int $measured): string
    {
        return match (true) {
            $measured === 0 || $rate === null => 'none',
            $measured < $this->minSample() => 'limited',
            $rate >= $this->target() => 'good',
            $rate >= $this->alertThreshold() => 'watch',
            default => 'critical',
        };
    }

    /**
     * Volume traité dans la période et stock restant à sa fin (ou aujourd'hui si elle est en cours).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function activity(Collection $rows, array $period, string $mode): array
    {
        $in = fn (?int $day) => $day !== null && $day >= $period['start'] && $day <= $period['end'];
        $reference = min($period['end'], $this->today);
        $entry = fn (array $row) => match ($mode) {
            'agent' => $row['agent_assigned'],
            'division' => $row['division_assigned'] ?? $row['arrival'],
            default => $row['arrival'],
        };
        // Pour un agent, le dossier quitte son stock à la soumission ; sinon à la signature.
        $exit = $mode === 'agent' ? 'submitted' : 'signed';

        $produced = $rows->filter(fn (array $row) => $in($row['submitted']));
        $drafting = $produced
            ->filter(fn (array $row) => $row['agent_assigned'] !== null)
            ->map(fn (array $row) => max(0, $row['submitted'] - $row['agent_assigned']));

        return [
            'received' => $rows->filter(fn (array $row) => $in($entry($row)))->count(),
            'produced' => $produced->count(),
            'signed' => $rows->filter(fn (array $row) => $in($row['signed']))->count(),
            'backlog' => $rows->filter(function (array $row) use ($entry, $exit, $reference) {
                $start = $entry($row);

                return $start !== null && $start <= $reference && ($row[$exit] === null || $row[$exit] > $reference);
            })->count(),
            'backlog_date' => gmdate('Y-m-d', $reference * 86400),
            'median_drafting_days' => $this->median($drafting),
            'correction_rate' => $produced->isEmpty()
                ? null
                : (int) round($produced->filter(fn (array $row) => $row['rejections'] > 0)->count() / $produced->count() * 100),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function divisionRows(array $period, array $previous): array
    {
        $divisions = Division::query()
            ->where('is_active', true)
            ->orWhereIn('id', $this->rows->pluck('division_ids')->flatten()->unique())
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return $divisions->map(function (Division $division) use ($period, $previous) {
            $rows = $this->forDivision($this->rows, $division->id);
            $global = $this->score($rows, $period, 'global');
            $activity = $this->activity($rows, $period, 'division');

            return [
                'id' => $division->id,
                'code' => $division->code,
                'name' => $division->name,
                'drafting' => $this->withTrend($this->score($rows, $period, 'drafting'), $this->score($rows, $previous, 'drafting')),
                'global_rate' => $global['rate'],
                'global_measured' => $global['measured'],
                'received' => $activity['received'],
                'backlog' => $activity['backlog'],
            ];
        })->values()->all();
    }

    /**
     * Agents à afficher : ceux qui ont reçu des courriers, plus les agents actifs sans dossier.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, User>
     */
    private function agentsFor(Collection $rows, ?int $divisionId): Collection
    {
        return User::query()
            ->with('division:id,code')
            ->where(fn (Builder $query) => $query
                ->whereIn('id', $rows->pluck('agent_ids')->flatten()->unique())
                ->orWhere(fn (Builder $query) => $query
                    ->where('role', 'agent')
                    ->where('is_active', true)
                    ->when($divisionId, fn (Builder $query, int $id) => $query->where('division_id', $id))))
            ->get(['id', 'name', 'role', 'division_id', 'is_active']);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, User>  $agents
     * @return array<int, array<string, mixed>>
     */
    private function agentRows(User $viewer, Collection $rows, Collection $agents, array $period, array $previous): array
    {
        return $agents->map(function (User $agent) use ($viewer, $rows, $period, $previous) {
            $own = $this->forAgent($rows, $agent->id);
            $activity = $this->activity($own, $period, 'agent');

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'division' => $agent->division?->code,
                'is_active' => $agent->is_active,
                // Un Chef de Division ne peut ouvrir que les agents de sa division.
                'can_open' => Gate::forUser($viewer)->allows('view-quarterly-agent', $agent),
                'drafting' => $this->withTrend($this->score($own, $period, 'drafting'), $this->score($own, $previous, 'drafting')),
                'received' => $activity['received'],
                'produced' => $activity['produced'],
                'in_hand' => $activity['backlog'],
                'median_drafting_days' => $activity['median_drafting_days'],
            ];
        })
            // Les situations à examiner d'abord : taux le plus bas, puis nom.
            ->sortBy(fn (array $row) => [$row['drafting']['rate'] === null ? 1 : 0, $row['drafting']['rate'] ?? 0, $row['name']])
            ->values()
            ->all();
    }

    /**
     * Courriers échus dans la période et non traités à temps, les plus en retard d'abord.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function lateItems(Collection $rows, array $period, string $stage, User $viewer, int $limit = 25): array
    {
        $deadlineKey = self::STAGES[$stage]['deadline'];
        $doneKey = self::STAGES[$stage]['done'];

        $late = $rows
            ->filter(fn (array $row) => $row[$deadlineKey] !== null
                && $row[$deadlineKey] >= $period['start']
                && $row[$deadlineKey] <= $period['end']
                && in_array($this->outcome($row, $stage), ['late', 'overdue'], true))
            ->map(fn (array $row) => $row + [
                'outcome' => $this->outcome($row, $stage),
                'days_late' => ($row[$doneKey] ?? $this->today) - $row[$deadlineKey],
            ])
            ->sortBy(fn (array $row) => [$row['outcome'] === 'overdue' ? 0 : 1, -$row['days_late']])
            ->values();

        $shown = $late->take($limit);
        $visible = Courrier::query()->visibleTo($viewer)->whereIn('id', $shown->pluck('id'))->pluck('id')->flip();
        $divisions = Division::whereIn('id', $shown->pluck('division_ids')->flatten()->unique())->pluck('code', 'id');
        $agents = User::whereIn('id', $shown->pluck('agent_ids')->flatten()->unique())->pluck('name', 'id');

        return [
            'total' => $late->count(),
            'overdue' => $late->where('outcome', 'overdue')->count(),
            'items' => $shown->map(fn (array $row) => [
                'id' => $row['id'],
                'registration_number' => $row['number'],
                // Le détail d'un courrier confidentiel reste réservé à ceux qui peuvent l'ouvrir.
                'subject' => $visible->has($row['id']) ? $row['subject'] : 'Courrier confidentiel',
                'can_open' => $visible->has($row['id']),
                'division' => collect($row['division_ids'])->map(fn (int $id) => $divisions[$id] ?? null)->filter()->join(' + ') ?: null,
                'agent' => collect($row['agent_ids'])->map(fn (int $id) => $agents[$id] ?? null)->filter()->join(' + ') ?: null,
                'deadline' => gmdate('Y-m-d', $row[$deadlineKey] * 86400),
                'done_at' => $row[$doneKey] !== null ? gmdate('Y-m-d', $row[$doneKey] * 86400) : null,
                'days_late' => $row['days_late'],
                'outcome' => $row['outcome'],
            ])->all(),
        ];
    }

    /** @return array<int, array{tone: string, text: string}> */
    private function directionHighlights(array $card, array $divisions, ?array $agents, array $period): array
    {
        [$global, $drafting, $signature] = $card['scores'];
        $notes = [$this->headlineNote($global, $period, 'des courriers échus ont été signés dans le délai')];

        if ($drafting['measured'] >= $this->minSample() && $signature['measured'] >= $this->minSample()
            && abs($drafting['rate'] - $signature['rate']) >= $this->significantChange()) {
            $weak = $drafting['rate'] < $signature['rate'] ? 'à la rédaction' : 'à la signature';
            $notes[] = [
                'tone' => 'info',
                'text' => "Le retard se forme surtout {$weak} : {$drafting['rate']} % des projets rendus dans le délai agent, "
                    ."{$signature['rate']} % des signatures dans le délai Directeur.",
            ];
        }

        if ($global['overdue'] > 0) {
            $notes[] = ['tone' => 'critical', 'text' => $this->plural($global['overdue'], 'courrier échu', 'courriers échus')." au {$period['label']} "
                .($global['overdue'] > 1 ? 'ne sont toujours pas signés.' : "n'est toujours pas signé.")];
        }

        foreach ($divisions as $division) {
            $score = $division['drafting'];

            if ($score['significant_change']) {
                $notes[] = $this->changeNote("La {$division['code']}", $score);
            } elseif ($score['standing'] === 'critical') {
                $notes[] = ['tone' => 'critical', 'text' => "La {$division['code']} est sous le seuil d'alerte : {$score['rate']} % des rédactions dans le délai ({$score['on_time']} sur {$score['measured']})."];
            }
        }

        if ($agents !== null) {
            $notes = array_merge($notes, $this->agentNotes($agents));
        }

        return array_merge($notes, $this->provisionalNote($global, $period));
    }

    /** @return array<int, array{tone: string, text: string}> */
    private function divisionHighlights(Division $division, array $card, array $direction, ?array $agents, array $period): array
    {
        $drafting = $card['scores'][0];
        $notes = [$this->headlineNote($drafting, $period, 'des projets de réponse ont été rendus dans le délai agent')];

        if ($drafting['measured'] >= $this->minSample() && $direction['rate'] !== null && $drafting['rate'] !== $direction['rate']) {
            $gap = abs($drafting['rate'] - $direction['rate']);
            $notes[] = [
                'tone' => $drafting['rate'] > $direction['rate'] ? 'good' : 'info',
                'text' => "La {$division->code} est ".($drafting['rate'] > $direction['rate'] ? 'au-dessus' : 'en dessous')
                    ." de la moyenne de la Direction ({$direction['rate']} %), avec un écart de {$gap} points.",
            ];
        }

        if ($drafting['significant_change']) {
            $notes[] = $this->changeNote('La division', $drafting);
        }

        if ($drafting['overdue'] > 0) {
            $notes[] = ['tone' => 'critical', 'text' => $this->plural($drafting['overdue'], 'projet échu', 'projets échus')." au {$period['label']} "
                .($drafting['overdue'] > 1 ? 'ne sont toujours pas rendus.' : "n'est toujours pas rendu.")];
        }

        if ($agents !== null) {
            $notes = array_merge($notes, $this->agentNotes($agents));
        }

        return array_merge($notes, $this->provisionalNote($drafting, $period));
    }

    /** @return array<int, array{tone: string, text: string}> */
    private function agentHighlights(array $card, ?array $division, array $period): array
    {
        $drafting = $card['scores'][0];
        $notes = [$this->headlineNote($drafting, $period, 'de vos projets de réponse ont été rendus dans le délai')];

        if ($drafting['measured'] > 0 && ($division['rate'] ?? null) !== null) {
            $notes[] = [
                'tone' => 'info',
                'text' => "Moyenne de votre division sur la même période : {$division['rate']} %.",
            ];
        }

        if ($drafting['significant_change']) {
            $notes[] = $this->changeNote('Votre taux', $drafting);
        }

        if ($drafting['overdue'] > 0) {
            $notes[] = ['tone' => 'critical', 'text' => 'Vous avez '.$this->plural($drafting['overdue'], 'projet échu', 'projets échus')
                .' non encore rendu'.($drafting['overdue'] > 1 ? 's' : '').' : ils figurent en tête de la liste ci-dessous.'];
        }

        if ($card['activity']['correction_rate'] !== null && $card['activity']['produced'] >= $this->minSample()) {
            $notes[] = ['tone' => 'info', 'text' => "{$card['activity']['correction_rate']} % des projets rendus ce trimestre ont fait l'objet d'au moins une demande de correction."];
        }

        return array_merge($notes, $this->provisionalNote($drafting, $period));
    }

    /** @return array{tone: string, text: string} */
    private function headlineNote(array $score, array $period, string $sentence): array
    {
        if ($score['measured'] === 0) {
            return ['tone' => 'info', 'text' => "Aucun courrier n'est arrivé à échéance au {$period['label']}."];
        }

        $text = "{$score['rate']} % {$sentence} ({$score['on_time']} sur {$score['measured']}) — objectif {$this->target()} %.";

        if ($score['standing'] === 'limited') {
            $text .= " Moins de {$this->minSample()} courriers échus : taux indicatif.";
        }

        return ['tone' => in_array($score['standing'], ['good', 'watch', 'critical'], true) ? $score['standing'] : 'info', 'text' => $text];
    }

    /** @return array{tone: string, text: string} */
    private function changeNote(string $subject, array $score): array
    {
        $up = $score['delta'] > 0;

        return [
            'tone' => $up ? 'good' : 'critical',
            'text' => "{$subject} ".($up ? 'progresse' : 'recule').' de '.abs($score['delta'])
                ." points par rapport au trimestre précédent ({$score['previous_rate']} % → {$score['rate']} %).",
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $agents
     * @return array<int, array{tone: string, text: string}>
     */
    private function agentNotes(array $agents): array
    {
        $notes = [];
        $critical = collect($agents)->where('drafting.standing', 'critical');

        if ($critical->isNotEmpty()) {
            $others = $critical->count() - 3;
            $names = $critical->take(3)->pluck('name')->join(', ').($others > 0 ? ' et '.$this->plural($others, 'autre', 'autres') : '');
            $notes[] = [
                'tone' => 'watch',
                'text' => $this->plural($critical->count(), 'agent est', 'agents sont')." sous {$this->alertThreshold()} % avec au moins {$this->minSample()} courriers échus : {$names}.",
            ];
        }

        $mostOverdue = collect($agents)->sortByDesc('drafting.overdue')->first();

        if ($mostOverdue && $mostOverdue['drafting']['overdue'] >= $this->minSample()) {
            $notes[] = [
                'tone' => 'watch',
                'text' => "Le plus grand nombre de projets échus non rendus est chez {$mostOverdue['name']} ({$mostOverdue['drafting']['overdue']}) : une redistribution peut être envisagée.",
            ];
        }

        $improved = collect($agents)->filter(fn (array $row) => $row['drafting']['significant_change'] && $row['drafting']['delta'] > 0);

        if ($improved->isNotEmpty()) {
            $notes[] = [
                'tone' => 'good',
                'text' => 'Nette progression depuis le trimestre précédent : '.$improved->take(3)->map(fn (array $row) => "{$row['name']} (+{$row['drafting']['delta']} pts)")->join(', ').'.',
            ];
        }

        return $notes;
    }

    /** @return array<int, array{tone: string, text: string}> */
    private function provisionalNote(array $score, array $period): array
    {
        if (! $period['is_current']) {
            return [];
        }

        $end = date('d/m/Y', strtotime($period['end_date']));

        return [[
            'tone' => 'info',
            'text' => 'Trimestre en cours : résultat provisoire, définitif au '.$end.'.'
                .($score['not_due'] > 0 ? ' '.$this->plural($score['not_due'], 'courrier arrive', 'courriers arrivent').' encore à échéance d\'ici là.' : ''),
        ]];
    }

    private function plural(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count > 1 ? $plural : $singular);
    }

    /** @param Collection<int, int> $values */
    private function median(Collection $values): int|float|null
    {
        $sorted = $values->sort()->values();

        if ($sorted->isEmpty()) {
            return null;
        }

        $middle = intdiv($sorted->count(), 2);
        $value = $sorted->count() % 2 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2;

        return $value == (int) $value ? (int) $value : round($value, 1);
    }

    private function dayNumber(string $date): int
    {
        return intdiv((int) strtotime(substr($date, 0, 10).' 00:00:00 UTC'), 86400);
    }

    private function target(): int
    {
        return (int) config('disc.quarterly.target', 80);
    }

    private function alertThreshold(): int
    {
        return (int) config('disc.quarterly.alert', 50);
    }

    private function minSample(): int
    {
        return (int) config('disc.quarterly.min_sample', 5);
    }

    private function significantChange(): int
    {
        return (int) config('disc.quarterly.significant_change', 10);
    }
}
