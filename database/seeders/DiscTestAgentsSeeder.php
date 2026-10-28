<?php

namespace Database\Seeders;

use App\Models\Courrier;
use App\Models\CourrierType;
use App\Models\Division;
use App\Models\User;
use App\Services\Disc\DiscExcelReader;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Courriers du fichier de test docs/DISC_fichier_test_agents_final.xlsx (onglet « Suivi Courrier »),
 * avec les comptes « Agent DRI n°1 »… qu'il référence. Les lignes sont repérées par source_hash,
 * ce qui permet de relancer le seeder sans créer de doublons.
 *
 * Colonnes : A n° d'ordre, B arrivée, C réf. expéditeur, D service d'origine, E réf. MEFP,
 * F réf. DGCPT, G type, H délai agent, I objet, J division, K imputation division,
 * L imputation agent, M instructions, N agent, O soumission, R délai Directeur, T signature,
 * W observation.
 */
class DiscTestAgentsSeeder extends Seeder
{
    public const FILE = 'docs/DISC_fichier_test_agents_final.xlsx';

    public function run(): void
    {
        $rows = app(DiscExcelReader::class)->read(base_path(self::FILE));
        $types = CourrierType::all()->keyBy('code');
        $divisions = Division::all()->keyBy('code');
        $secretariat = User::where('role', 'secretariat')->firstOrFail();
        $director = User::where('role', 'director')->firstOrFail();
        $heads = User::where('role', 'division_head')->get()->keyBy('division_id');

        DB::transaction(function () use ($rows, $types, $divisions, $secretariat, $director, $heads) {
            $agents = $this->seedAgents($rows, $divisions);
            $seeded = 0;

            foreach ($rows as $raw) {
                $type = $types->get($this->typeCode($raw['G'] ?? ''));
                $division = $divisions->get(Str::upper($raw['J'] ?? ''));
                $arrival = $this->excelDate($raw['B'] ?? null);

                if (! $type || ! $division || ! $arrival || (int) ($raw['A'] ?? 0) < 1) {
                    $this->command?->warn("Ligne {$raw['_row']} ignorée : type, division, date ou numéro invalide.");

                    continue;
                }

                $this->seedCourrier($raw, $type, $division, $arrival, [
                    'secretariat' => $secretariat,
                    'director' => $director,
                    'head' => $heads->get($division->id) ?? $director,
                    'agent' => $agents->get($this->normalize($raw['N'] ?? '')),
                ]);
                $seeded++;
            }

            $this->command?->info("{$seeded} courrier(s) de test, {$agents->count()} agent(s).");
        });
    }

    /**
     * Un compte par agent cité (« Agent DRI n°1 » → agent.dri.1@disc.sn), rattaché à sa division.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return Collection<string, User>
     */
    private function seedAgents(array $rows, Collection $divisions): Collection
    {
        return collect($rows)
            ->map(fn (array $row) => trim($row['N'] ?? ''))
            ->filter()
            ->unique(fn (string $name) => $this->normalize($name))
            ->mapWithKeys(function (string $name) use ($divisions) {
                preg_match('/^agent\s+(\w+)\s+n\D*(\d+)$/', $this->normalize($name), $matches);
                [$code, $number] = [Str::upper($matches[1] ?? ''), $matches[2] ?? null];

                $user = User::firstOrNew(['email' => $number
                    ? sprintf('agent.%s.%s@disc.sn', Str::lower($code), $number)
                    : Str::slug(Str::ascii($name), '.').'@disc.sn']);
                $user->fill([
                    'name' => $name,
                    'role' => 'agent',
                    'division_id' => $divisions->get($code)?->id,
                    'is_active' => true,
                ]);
                $user->email_verified_at ??= now();
                $user->password ??= Hash::make(DiscUserSeeder::DEFAULT_PASSWORD);
                $user->save();

                return [$this->normalize($name) => $user];
            });
    }

    /**
     * @param  array<string, string>  $raw
     * @param  array{secretariat: User, director: User, head: User, agent: ?User}  $actors
     */
    private function seedCourrier(array $raw, CourrierType $type, Division $division, CarbonImmutable $arrival, array $actors): void
    {
        $sequence = (int) $raw['A'];
        $hash = hash('sha256', 'test-agents:'.implode('|', [$sequence, $raw['B'], $raw['C'] ?? '', $raw['F'] ?? '']));
        $dates = [
            'division' => $this->excelDate($raw['K'] ?? null),
            'agent' => $this->excelDate($raw['L'] ?? null),
            'submitted' => $this->excelDate($raw['O'] ?? null),
            'signed' => $this->excelDate($raw['T'] ?? null),
        ];
        $agent = $actors['agent'];

        $status = match (true) {
            ! $type->requires_response => Courrier::STATUS_CLOSED_NO_RESPONSE,
            $dates['signed'] !== null => Courrier::STATUS_SIGNED,
            // Soumis mais non signé : en attente de signature du Directeur.
            $dates['submitted'] !== null => Courrier::STATUS_DIVISION_VALIDATED,
            $agent !== null => Courrier::STATUS_ASSIGNED_AGENT,
            default => Courrier::STATUS_ASSIGNED_DIVISION,
        };
        $validated = in_array($status, [Courrier::STATUS_DIVISION_VALIDATED, Courrier::STATUS_SIGNED], true);

        $courrier = Courrier::withTrashed()->firstOrNew(['source_hash' => $hash]);
        if (! $courrier->exists) {
            // Le n° d'ordre du fichier est conservé s'il est libre pour l'année.
            $taken = Courrier::withTrashed()->where('registration_year', $arrival->year)->where('sequence_number', $sequence)->exists();
            $courrier->sequence_number = $taken
                ? (int) Courrier::withTrashed()->where('registration_year', $arrival->year)->max('sequence_number') + 1
                : $sequence;
        }

        $notes = collect([
            ($raw['E'] ?? '') !== '' ? 'Réf. MEFP : '.$raw['E'] : null,
            ($raw['W'] ?? '') !== '' ? 'Observation : '.$raw['W'] : null,
        ])->filter()->join("\n");

        $courrier->forceFill([
            'registration_year' => $arrival->year,
            'arrival_date' => $arrival,
            'reference' => $this->nullable($raw['C'] ?? null),
            'origin_service' => trim($raw['D'] ?? ''),
            'courrier_type_id' => $type->id,
            'subject' => trim($raw['I'] ?? ''),
            'arrival_number' => $this->nullable($raw['F'] ?? null),
            'division_id' => $division->id,
            'division_assigned_at' => $dates['division'],
            'agent_assigned_at' => $agent ? $dates['agent'] : null,
            'instructions' => $this->nullable($raw['M'] ?? null),
            'assigned_agent_id' => $agent?->id,
            'legacy_agent_name' => $this->nullable($raw['N'] ?? null),
            'submitted_at' => $dates['submitted'],
            'division_validated_at' => $validated ? $dates['submitted'] : null,
            'division_validated_by_id' => $validated ? $actors['head']->id : null,
            'signed_at' => $dates['signed'],
            'status' => $status,
            'agent_delay_days' => $type->requires_response ? ((int) ($raw['H'] ?? 0) ?: $type->agent_delay_days) : null,
            'director_delay_days' => $type->requires_response ? ((int) ($raw['R'] ?? 0) ?: $type->director_delay_days) : null,
            'notes' => $notes ?: null,
            'source_sheet' => 'Test agents',
            'source_row' => (int) $raw['_row'],
            'created_by_id' => $actors['secretariat']->id,
            'updated_by_id' => $actors['secretariat']->id,
            'created_at' => $arrival,
            'deleted_at' => null,
        ])->save();

        $courrier->events()->delete();
        if (! $type->requires_response) {
            $events = [[$actors['secretariat'], 'created', null, Courrier::STATUS_CLOSED_NO_RESPONSE, $arrival, 'Courrier enregistré dans DISC.']];
        } else {
            $events = [
                [$actors['secretariat'], 'created', null, Courrier::STATUS_REGISTERED, $arrival, 'Courrier enregistré dans DISC.'],
                [$actors['director'], 'assigned', Courrier::STATUS_REGISTERED, Courrier::STATUS_ASSIGNED_DIVISION, $dates['division'] ?? $arrival, null],
            ];
            if ($agent) {
                $events[] = [$actors['head'], 'assigned', Courrier::STATUS_ASSIGNED_DIVISION, Courrier::STATUS_ASSIGNED_AGENT, $dates['agent'] ?? $arrival, null];
            }
            if ($dates['submitted']) {
                $events[] = [$agent ?? $actors['head'], 'submitted_for_review', Courrier::STATUS_ASSIGNED_AGENT, Courrier::STATUS_PENDING_DIVISION_REVIEW, $dates['submitted'], null];
                $events[] = [$actors['head'], 'division_approved', Courrier::STATUS_PENDING_DIVISION_REVIEW, Courrier::STATUS_DIVISION_VALIDATED, $dates['submitted']->addHours(4), null];
            }
            if ($dates['signed']) {
                $events[] = [$actors['director'], 'signed', Courrier::STATUS_DIVISION_VALIDATED, Courrier::STATUS_SIGNED, $dates['signed']->addHours(8), null];
            }
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

    /** Les dates sont stockées en numéro de série Excel ; le fichier n'a pas d'heure. */
    private function excelDate(?string $value): ?CarbonImmutable
    {
        if (! is_numeric($value)) {
            return null;
        }

        return CarbonImmutable::create(1899, 12, 30, 9)->addDays((int) floor((float) $value));
    }

    private function typeCode(string $value): string
    {
        return match ($this->normalize($value)) {
            'ordinaire' => 'ordinary',
            'confidentiel' => 'confidential',
            'ne necessite pas de reponse' => 'no_response',
            default => '',
        };
    }

    private function normalize(string $value): string
    {
        return Str::lower(trim(Str::ascii($value)));
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
