<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\CourrierImport;
use App\Services\Disc\CourrierImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CourrierImportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-disc');

        return Inertia::render('administration/imports/Index', [
            'preview' => $request->session()->get('disc_import_preview'),
            'imports' => CourrierImport::with('importedBy:id,name')->latest('imported_at')->take(20)->get(),
        ]);
    }

    public function preview(Request $request, CourrierImportService $importer): RedirectResponse
    {
        Gate::authorize('manage-disc');
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:20480']]);
        $token = (string) Str::uuid();
        $file = $request->file('file');
        $path = $file->storeAs('imports/pending', $token.'.xlsx', 'local');

        try {
            $report = $importer->analyze(Storage::disk('local')->path($path));
        } catch (RuntimeException $exception) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $preview = $report + ['token' => $token, 'original_name' => $file->getClientOriginalName()];

        $request->session()->put('disc_import_preview', $preview);
        $request->session()->put('disc_import_files.'.$token, [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ]);

        return to_route('administration.imports.index')->with('success', 'Analyse terminée. Vérifiez le rapport avant de confirmer.');
    }

    public function store(Request $request, CourrierImportService $importer): RedirectResponse
    {
        Gate::authorize('manage-disc');
        $data = $request->validate(['token' => ['required', 'uuid']]);
        $stored = $request->session()->get('disc_import_files.'.$data['token']);

        abort_unless($stored && Storage::disk('local')->exists($stored['path']), 422, 'Le fichier temporaire a expiré.');

        $result = $importer->import(
            Storage::disk('local')->path($stored['path']),
            $stored['original_name'],
            $request->user(),
        );

        Storage::disk('local')->delete($stored['path']);
        $request->session()->forget(['disc_import_preview', 'disc_import_files.'.$data['token']]);

        if ($result['already_imported']) {
            return to_route('administration.imports.index')->with('error', 'Ce fichier a déjà été importé.');
        }

        return to_route('administration.imports.index')->with(
            'success',
            "Import terminé : {$result['imported']} courrier(s) importé(s), {$result['skipped']} doublon(s), {$result['invalid']} ligne(s) invalide(s).",
        );
    }
}
