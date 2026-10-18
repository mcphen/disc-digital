<?php

namespace App\Services\Disc;

use App\Models\Courrier;
use App\Models\CourrierImport;
use App\Models\CourrierType;
use App\Models\Division;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourrierImportService
{
    public function __construct(private readonly DiscExcelReader $reader) {}

    /** @return array<string, mixed> */
    public function analyze(string $path): array
    {
        $rows = $this->prepareRows($path);

        return [
            'total' => count($rows),
            'valid' => collect($rows)->where('valid', true)->where('duplicate', false)->count(),
            'duplicates' => collect($rows)->where('duplicate', true)->count(),
            'invalid' => collect($rows)->where('valid', false)->count(),
            'errors' => collect($rows)->where('valid', false)->map(fn ($row) => [
                'row' => $row['source_row'],
                'messages' => $row['errors'],
            ])->values()->take(30)->all(),
            'preview' => collect($rows)->where('valid', true)->take(10)->map(fn ($row) => [
                'row' => $row['source_row'],
                'arrival_date' => $row['data']['arrival_date'],
                'reference' => $row['data']['reference'],
                'origin_service' => $row['data']['origin_service'],
                'subject' => Str::limit($row['data']['subject'], 100),
                'type' => $row['type']->name,
                'division' => collect([$row['division']])->concat($row['contributing_divisions'])->filter()->pluck('code')->join(' + ') ?: null,
                'agent' => $row['data']['legacy_agent_name'],
                'duplicate' => $row['duplicate'],
            ])->values()->all(),
        ];
    }

    /** @return array<string, int> */
    public function import(string $path, string $originalName, User $user): array
    {
        $fileHash = hash_file('sha256', $path);
        if (CourrierImport::where('file_hash', $fileHash)->exists()) {
            return ['total' => 0, 'imported' => 0, 'skipped' => 0, 'invalid' => 0, 'already_imported' => 1];
        }

        $rows = $this->prepareRows($path);
        $result = ['total' => count($rows), 'imported' => 0, 'skipped' => 0, 'invalid' => 0, 'already_imported' => 0];

        DB::transaction(function () use ($rows, $fileHash, $originalName, $user, &$result) {
            foreach ($rows as $row) {
                if (! $row['valid']) {
                    $result['invalid']++;

                    continue;
                }

                if ($row['duplicate']) {
                    $result['skipped']++;

                    continue;
                }

                $data = $row['data'];
                $data['courrier_type_id'] = $row['type']->id;
                $data['division_id'] = $row['division']?->id;
                $data['assigned_agent_id'] = $row['agent']?->id;
                $data['created_by_id'] = $user->id;
                $data['updated_by_id'] = $user->id;
                $courrier = Courrier::create($data);
                $courrier->syncContributors(
                    collect($row['contributing_divisions'])->pluck('id')->all(),
                    collect($row['co_agents'])->pluck('id')->all(),
                );
                $courrier->events()->create([
                    'user_id' => $user->id,
                    'event' => 'imported',
                    'to_status' => $data['status'],
                    'comment' => "Importé depuis {$originalName}, ligne {$data['source_row']}.",
                ]);
                $result['imported']++;
            }

            CourrierImport::create([
                'original_name' => $originalName,
                'file_hash' => $fileHash,
                'total_rows' => $result['total'],
                'imported_rows' => $result['imported'],
                'skipped_rows' => $result['skipped'],
                'invalid_rows' => $result['invalid'],
                'imported_by_id' => $user->id,
                'imported_at' => now(),
            ]);
        });

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function prepareRows(string $path): array
    {
        $types = CourrierType::all()->keyBy('code');
        $divisions = Division::all()->keyBy(fn ($division) => Str::upper($division->code));
        $agents = User::whereIn('role', ['agent', 'division_head'])->get()->keyBy(fn ($agent) => $this->normalize($agent->name));
        $existingHashes = Courrier::whereNotNull('source_hash')->pluck('source_hash')->flip();
        $existingNumbers = Courrier::get(['registration_year', 'sequence_number'])
            ->mapWithKeys(fn ($courrier) => [$courrier->registration_year.'-'.$courrier->sequence_number => true]);
        $prepared = [];

        foreach ($this->reader->read($path) as $raw) {
            $errors = [];
            $arrivalDate = $this->excelDate($raw['B'] ?? null);
            $type = $types->get($this->typeCode($raw['E'] ?? ''));
            // « DP/DRI » : DP pilote, DRI contributrice. « Mme DIENG - Mme NGOM » : responsable puis co-agent.
            $divisionCodes = array_map(fn (string $code) => Str::upper($code), SharedAssignment::split($raw['I'] ?? ''));
            $divisionCode = $divisionCodes[0] ?? '';
            $division = $divisionCode ? $divisions->get($divisionCode) : null;
            $contributingDivisions = collect(array_slice($divisionCodes, 1))->map(fn (string $code) => $divisions->get($code))->filter()->values();
            $agentName = trim($raw['M'] ?? '');
            $agentNames = SharedAssignment::split($agentName);
            $agent = $agentNames ? $agents->get($this->normalize($agentNames[0])) : null;
            $coAgents = collect(array_slice($agentNames, 1))->map(fn (string $name) => $agents->get($this->normalize($name)))->filter()->values();
            $sequence = (int) ($raw['A'] ?? 0);
            $year = $arrivalDate ? (int) substr($arrivalDate, 0, 4) : 0;

            if (! $arrivalDate) {
                $errors[] = 'Date d’arrivée absente ou invalide.';
            }
            if (trim($raw['D'] ?? '') === '') {
                $errors[] = 'Service d’origine absent.';
            }
            if (trim($raw['G'] ?? '') === '') {
                $errors[] = 'Objet absent.';
            }
            if (! $type) {
                $errors[] = 'Type de courrier non reconnu : '.($raw['E'] ?? 'vide').'.';
            }
            foreach ($divisionCodes as $code) {
                if (! $divisions->has($code)) {
                    $errors[] = "Division {$code} inconnue.";
                }
            }
            if ($sequence < 1) {
                $errors[] = 'Numéro d’ordre invalide.';
            }

            $sourceHash = hash('sha256', implode('|', [
                $arrivalDate, $sequence, $raw['C'] ?? '', $raw['D'] ?? '', $raw['G'] ?? '', $raw['H'] ?? '',
            ]));
            $numberCollision = $year && $sequence && $existingNumbers->has($year.'-'.$sequence) && ! $existingHashes->has($sourceHash);
            if ($numberCollision) {
                $errors[] = "Le numéro {$sequence}/{$year} existe déjà avec un autre contenu.";
            }

            $submittedAt = $this->excelDate($raw['N'] ?? null);
            $signedAt = $this->excelDate($raw['S'] ?? null);
            $requiresResponse = $type?->requires_response ?? true;
            $status = ! $requiresResponse
                ? Courrier::STATUS_CLOSED_NO_RESPONSE
                : ($signedAt
                    ? Courrier::STATUS_SIGNED
                    : ($submittedAt
                        ? Courrier::STATUS_SUBMITTED
                        : ($agentName
                            ? Courrier::STATUS_ASSIGNED_AGENT
                            : ($division ? Courrier::STATUS_ASSIGNED_DIVISION : Courrier::STATUS_REGISTERED))));

            $prepared[] = [
                'source_row' => (int) $raw['_row'],
                'valid' => count($errors) === 0,
                'duplicate' => $existingHashes->has($sourceHash),
                'errors' => $errors,
                'type' => $type,
                'division' => $division,
                'contributing_divisions' => $contributingDivisions,
                'agent' => $agent,
                'co_agents' => $coAgents,
                'data' => [
                    'sequence_number' => $sequence,
                    'registration_year' => $year,
                    'arrival_date' => $arrivalDate,
                    'reference' => $this->nullable($raw['C'] ?? null),
                    'origin_service' => trim($raw['D'] ?? ''),
                    'subject' => trim($raw['G'] ?? ''),
                    'arrival_number' => $this->nullable($raw['H'] ?? null),
                    'division_assigned_at' => $this->excelDate($raw['J'] ?? null),
                    'agent_assigned_at' => $this->excelDate($raw['K'] ?? null),
                    'instructions' => $this->nullable($raw['L'] ?? null),
                    'legacy_agent_name' => $this->nullable($agentName),
                    'submitted_at' => $submittedAt,
                    'signed_at' => $signedAt,
                    'status' => $status,
                    'agent_delay_days' => $requiresResponse ? $this->positiveInt($raw['F'] ?? null, $type?->agent_delay_days) : null,
                    'director_delay_days' => $requiresResponse ? $this->positiveInt($raw['Q'] ?? null, $type?->director_delay_days) : null,
                    'source_sheet' => 'Suivi Courrier',
                    'source_row' => (int) $raw['_row'],
                    'source_hash' => $sourceHash,
                ],
            ];
        }

        return $prepared;
    }

    private function excelDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, trim($value));
                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                // Try the next known format.
            }
        }

        return null;
    }

    private function typeCode(string $value): string
    {
        return match ($this->normalize($value)) {
            'ordinaire' => 'ordinary',
            'confidentiel' => 'confidential',
            'avis' => 'advice',
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

    private function positiveInt(?string $value, ?int $fallback): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $fallback;
    }
}
