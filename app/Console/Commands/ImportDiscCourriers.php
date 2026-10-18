<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Disc\CourrierImportService;
use App\Services\Disc\DiscExcelUserProvisioner;
use Illuminate\Console\Command;

class ImportDiscCourriers extends Command
{
    protected $signature = 'disc:import-courriers
        {file : Chemin du classeur XLSX}
        {--user=admin@disc.sn : Compte responsable de l’import}
        {--provision-agents : Créer les agents présents dans le classeur}
        {--dry-run : Analyser sans écrire les courriers}
        {--force : Importer sans demander de confirmation}';

    protected $description = 'Analyse et importe le classeur historique des courriers DISC';

    public function handle(CourrierImportService $importer, DiscExcelUserProvisioner $provisioner): int
    {
        $path = realpath($this->argument('file')) ?: $this->argument('file');
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        if ($this->option('provision-agents')) {
            $result = $provisioner->provision($path);
            $this->info("Agents du classeur : {$result['created']} créé(s), {$result['updated']} mis à jour.");
            $this->table(['Nom', 'Email', 'Division'], $result['accounts']);
        }

        $report = $importer->analyze($path);
        $this->table(['Total', 'Valides', 'Doublons', 'Invalides'], [[
            $report['total'], $report['valid'], $report['duplicates'], $report['invalid'],
        ]]);

        if ($report['invalid'] > 0) {
            $this->table(['Ligne', 'Erreurs'], collect($report['errors'])->map(fn (array $error) => [
                $error['row'], implode(' ', $error['messages']),
            ]));
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Confirmer l’import dans MySQL ?')) {
            $this->warn('Import annulé.');

            return self::SUCCESS;
        }

        $user = User::where('email', $this->option('user'))->first();
        if (! $user) {
            $this->error('Le compte responsable de l’import est introuvable.');

            return self::FAILURE;
        }

        $result = $importer->import($path, basename($path), $user);
        if ($result['already_imported']) {
            $this->warn('Ce fichier a déjà été importé.');

            return self::SUCCESS;
        }

        $this->info("Import terminé : {$result['imported']} importé(s), {$result['skipped']} doublon(s), {$result['invalid']} invalide(s).");

        return self::SUCCESS;
    }
}
