<?php

namespace Database\Seeders;

use App\Models\Courrier;
use App\Models\CourrierType;
use App\Models\Division;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Courriers de démonstration pour septembre et novembre : chaque division reçoit des dossiers
 * terminés (signés) et des dossiers en cours à chaque étape du circuit. Les lignes sont repérées
 * par source_hash, ce qui permet de relancer le seeder sans créer de doublons.
 */
class DiscCourrierSeeder extends Seeder
{
    public const YEAR = 2026;

    public const MONTHS = [9, 11];

    /** Statut cible et jour d'arrivée dans le mois, pour chaque courrier d'une division. */
    private const SCENARIOS = [
        ['status' => Courrier::STATUS_SIGNED, 'day' => 2],
        ['status' => Courrier::STATUS_SIGNED, 'day' => 5],
        ['status' => Courrier::STATUS_SIGNED, 'day' => 9],
        ['status' => Courrier::STATUS_DIVISION_VALIDATED, 'day' => 12],
        ['status' => Courrier::STATUS_PENDING_DIVISION_REVIEW, 'day' => 16],
        ['status' => Courrier::STATUS_CORRECTION_REQUESTED, 'day' => 19],
        ['status' => Courrier::STATUS_ASSIGNED_AGENT, 'day' => 23],
        ['status' => Courrier::STATUS_ASSIGNED_DIVISION, 'day' => 26],
    ];

    private const ORIGINS = [
        'Ministère des Finances et du Budget',
        'Primature',
        'Ministère de l’Économie, du Plan et de la Coopération',
        'Cour des Comptes',
        'Direction Générale du Trésor',
        'Assemblée nationale',
        'Ministère de l’Intérieur',
        'Inspection Générale d’État',
    ];

    private const SUBJECTS = [
        'DRI' => [
            'Demande de point sur la convention de partenariat',
            'Invitation à la réunion du comité interministériel',
            'Transmission du projet d’accord de coopération',
            'Demande d’avis sur le protocole d’entente',
            'Suivi des engagements pris lors de la mission conjointe',
            'Organisation de l’atelier de concertation institutionnelle',
            'Demande de désignation d’un point focal',
            'Relance sur le compte rendu de la session de travail',
        ],
        'DP' => [
            'Situation financière des sociétés du portefeuille',
            'Transmission des états financiers de l’exercice',
            'Demande de restructuration du capital social',
            'Rapport du conseil d’administration',
            'Projet de cession de participations de l’État',
            'Demande de garantie sur emprunt',
            'Évaluation des dividendes à reverser',
            'Relance sur les contrats de performance',
        ],
        'DSSE' => [
            'Demande de synthèse trimestrielle des indicateurs',
            'Transmission de l’étude d’impact sectorielle',
            'Demande de données pour le rapport annuel',
            'Note de conjoncture à consolider',
            'Termes de référence de l’étude diagnostique',
            'Suivi des recommandations de l’audit',
            'Tableau de bord des projets prioritaires',
            'Demande de contribution au document de programmation',
        ],
    ];

    /** Ordre du circuit : chaque statut atteint suppose les étapes précédentes. */
    private const STEPS = [
        Courrier::STATUS_ASSIGNED_DIVISION,
        Courrier::STATUS_ASSIGNED_AGENT,
        Courrier::STATUS_PENDING_DIVISION_REVIEW,
        Courrier::STATUS_CORRECTION_REQUESTED,
        Courrier::STATUS_DIVISION_VALIDATED,
        Courrier::STATUS_SIGNED,
    ];

    public function run(): void
    {
        $types = CourrierType::whereIn('code', ['ordinary', 'confidential'])->get()->keyBy('code');
        $secretariat = User::where('role', 'secretariat')->firstOrFail();
        $director = User::where('role', 'director')->firstOrFail();

        DB::transaction(function () use ($types, $secretariat, $director) {
            foreach (Division::where('is_active', true)->orderBy('id')->get() as $divisionIndex => $division) {
                $head = User::where('role', 'division_head')->where('division_id', $division->id)->first();
                $agents = User::where('role', 'agent')->where('division_id', $division->id)
                    ->where('is_active', true)->orderBy('id')->get();

                if (! $head || $agents->isEmpty()) {
                    $this->command?->warn("Division {$division->code} ignorée : chef de division ou agent manquant.");

                    continue;
                }

                foreach (self::MONTHS as $month) {
                    foreach (self::SCENARIOS as $index => $scenario) {
                        $this->seedCourrier($division, $divisionIndex, $month, $index, $scenario, [
                            'type' => $types[$index === 4 ? 'confidential' : 'ordinary'],
                            'secretariat' => $secretariat,
                            'director' => $director,
                            'head' => $head,
                            'agent' => $agents[($index + $month) % $agents->count()],
                        ]);
                    }
                }
            }
        });
    }

    /**
     * @param  array{status: string, day: int}  $scenario
     * @param  array{type: CourrierType, secretariat: User, director: User, head: User, agent: User}  $actors
     */
    private function seedCourrier(Division $division, int $divisionIndex, int $month, int $index, array $scenario, array $actors): void
    {
        $hash = hash('sha256', sprintf('seed:%s:%d-%02d:%d', $division->code, self::YEAR, $month, $index));
        $reached = array_slice(self::STEPS, 0, array_search($scenario['status'], self::STEPS, true) + 1);
        $has = fn (string $status) => in_array($status, $reached, true);

        // Décalage d'un jour par division pour ne pas aligner toutes les arrivées.
        $arrival = CarbonImmutable::create(self::YEAR, $month, $scenario['day'] + $divisionIndex, 9);
        $dates = [
            'division' => $arrival->addDay(),
            'agent' => $arrival->addDays(2),
            'submitted' => $arrival->addDays(5),
            'rejected' => $arrival->addDays(6),
            'validated' => $arrival->addDays(7),
            'signed' => $arrival->addDays(9),
        ];

        $courrier = Courrier::withTrashed()->firstOrNew(['source_hash' => $hash]);
        if (! $courrier->exists) {
            $courrier->sequence_number = (int) Courrier::withTrashed()->where('registration_year', self::YEAR)->max('sequence_number') + 1;
        }

        $courrier->forceFill([
            'registration_year' => self::YEAR,
            'arrival_date' => $arrival,
            'reference' => sprintf('%s/%s/%02d-%03d', $division->code, self::YEAR, $month, $index + 1),
            'origin_service' => self::ORIGINS[($index + $divisionIndex) % count(self::ORIGINS)],
            'courrier_type_id' => $actors['type']->id,
            'subject' => self::SUBJECTS[$division->code][$index] ?? 'Courrier de démonstration '.($index + 1),
            'arrival_number' => sprintf('A-%02d%02d-%s', $month, $index + 1, $division->code),
            'division_id' => $division->id,
            'division_assigned_at' => $dates['division'],
            'agent_assigned_at' => $has(Courrier::STATUS_ASSIGNED_AGENT) ? $dates['agent'] : null,
            'instructions' => 'Préparer un projet de réponse.',
            'assigned_agent_id' => $has(Courrier::STATUS_ASSIGNED_AGENT) ? $actors['agent']->id : null,
            'submitted_at' => $has(Courrier::STATUS_PENDING_DIVISION_REVIEW) ? $dates['submitted'] : null,
            'division_validated_at' => $has(Courrier::STATUS_DIVISION_VALIDATED) ? $dates['validated'] : null,
            'division_validated_by_id' => $has(Courrier::STATUS_DIVISION_VALIDATED) ? $actors['head']->id : null,
            'signed_at' => $has(Courrier::STATUS_SIGNED) ? $dates['signed'] : null,
            'status' => $scenario['status'],
            'agent_delay_days' => $actors['type']->agent_delay_days,
            'director_delay_days' => $actors['type']->director_delay_days,
            'source_sheet' => 'seed',
            'created_by_id' => $actors['secretariat']->id,
            'updated_by_id' => $actors['secretariat']->id,
            'created_at' => $arrival,
            'deleted_at' => null,
        ])->save();

        $courrier->events()->delete();
        $events = [
            [$actors['secretariat'], 'created', null, Courrier::STATUS_REGISTERED, $arrival, 'Courrier enregistré dans DISC.'],
            [$actors['director'], 'assigned', Courrier::STATUS_REGISTERED, Courrier::STATUS_ASSIGNED_DIVISION, $dates['division'], null],
        ];
        if ($has(Courrier::STATUS_ASSIGNED_AGENT)) {
            $events[] = [$actors['head'], 'assigned', Courrier::STATUS_ASSIGNED_DIVISION, Courrier::STATUS_ASSIGNED_AGENT, $dates['agent'], null];
        }
        if ($has(Courrier::STATUS_PENDING_DIVISION_REVIEW)) {
            $events[] = [$actors['agent'], 'submitted_for_review', Courrier::STATUS_ASSIGNED_AGENT, Courrier::STATUS_PENDING_DIVISION_REVIEW, $dates['submitted'], null];
        }
        if ($has(Courrier::STATUS_CORRECTION_REQUESTED)) {
            $events[] = [$actors['head'], 'division_rejected', Courrier::STATUS_PENDING_DIVISION_REVIEW, Courrier::STATUS_CORRECTION_REQUESTED, $dates['rejected'], 'Merci de compléter le projet.'];
        }
        if ($has(Courrier::STATUS_DIVISION_VALIDATED)) {
            $events[] = [$actors['agent'], 'submitted_for_review', Courrier::STATUS_CORRECTION_REQUESTED, Courrier::STATUS_PENDING_DIVISION_REVIEW, $dates['rejected']->addHours(4), null];
            $events[] = [$actors['head'], 'division_approved', Courrier::STATUS_PENDING_DIVISION_REVIEW, Courrier::STATUS_DIVISION_VALIDATED, $dates['validated'], null];
        }
        if ($has(Courrier::STATUS_SIGNED)) {
            $events[] = [$actors['director'], 'signed', Courrier::STATUS_DIVISION_VALIDATED, Courrier::STATUS_SIGNED, $dates['signed'], null];
        }

        foreach ($events as [$user, $event, $from, $to, $at, $comment]) {
            $courrier->events()->make([
                'user_id' => $user->id,
                'event' => $event,
                'from_status' => $from,
                'to_status' => $to,
                'comment' => $comment,
            ])->forceFill(['created_at' => $at])->save();
        }
    }
}
