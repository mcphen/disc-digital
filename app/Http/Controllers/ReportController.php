<?php

namespace App\Http\Controllers;

use App\Models\Courrier;
use App\Models\CourrierType;
use App\Models\Division;
use App\Services\Disc\CourrierPerformanceService;
use App\Services\Disc\CourrierReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, CourrierReportService $reports): Response
    {
        Gate::authorize('view-disc-reports');
        $filters = $this->filters($request);
        $rows = $reports->rows($request->user(), $filters);

        return Inertia::render('reports/Index', [
            'filters' => $filters,
            'summary' => $reports->summary($rows),
            'rows' => $rows->take(100)->values(),
            'isTruncated' => $rows->count() > 100,
            'divisions' => Division::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'types' => CourrierType::orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statuses(),
        ]);
    }

    public function performance(Request $request, CourrierPerformanceService $performance): Response
    {
        Gate::authorize('view-disc-reports');
        $filters = collect($this->filters($request))->except('status')->all();

        return Inertia::render('reports/Performance', [
            'filters' => $filters,
            'performance' => $performance->build($request->user(), $filters),
            'divisions' => Division::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'types' => CourrierType::where('requires_response', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, CourrierReportService $reports): StreamedResponse
    {
        Gate::authorize('view-disc-reports');
        $rows = $reports->rows($request->user(), $this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['N° DISC', 'Arrivée', 'Service', 'Objet', 'Type', 'Division pilote', 'Divisions contributrices', 'Agent responsable', 'Co-agents', 'Production', 'Validation division', 'Validé par', 'Statut', 'Alerte', 'Échéance', 'Observation'], ';');
            foreach ($rows as $row) {
                fputcsv($output, array_map([$this, 'safeCsv'], [
                    $row['registration_number'], $row['arrival_date'], $row['origin_service'], $row['subject'],
                    $row['type'], $row['division'], $row['contributing_divisions'], $row['agent'], $row['co_agents'], $row['produced_at'], $row['division_validated_at'],
                    $row['division_validated_by'], $row['status'], $row['alert'],
                    $row['due_date'], $row['observation'],
                ]), ';');
            }
            fclose($output);
        }, 'rapport-courriers-disc-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'division' => ['nullable', 'integer', 'exists:divisions,id'],
            'type' => ['nullable', 'integer', 'exists:courrier_types,id'],
            'status' => ['nullable', Rule::in(array_keys($this->statuses()))],
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

    private function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
