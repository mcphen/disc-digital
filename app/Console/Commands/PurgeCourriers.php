<?php

namespace App\Console\Commands;

use App\Models\CourrierAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Remet à zéro les données de traitement : courriers (y compris supprimés logiquement), leur
 * circuit de validation (historique, notifications, pièces jointes, imputations multiples) et
 * l'historique des imports. Les utilisateurs, rôles, divisions, types et paramètres sont conservés.
 */
class PurgeCourriers extends Command
{
    protected $signature = 'disc:purge-courriers {--force : Ne pas demander de confirmation}';

    protected $description = 'Supprime tous les courriers et leur circuit de validation (conserve utilisateurs et rôles)';

    /** Ordre de suppression : tables dépendantes d'abord. */
    private const TABLES = [
        'courrier_notifications',
        'courrier_events',
        'courrier_attachments',
        'courrier_co_agents',
        'courrier_contributing_divisions',
        'courriers',
        'courrier_imports',
    ];

    public function handle(): int
    {
        foreach (self::TABLES as $table) {
            $this->line(sprintf('%-34s %d ligne(s)', $table, DB::table($table)->count()));
        }

        if (! $this->option('force') && ! $this->confirm('Supprimer définitivement ces données ?')) {
            $this->warn('Annulé : rien n’a été modifié.');

            return self::SUCCESS;
        }

        $files = CourrierAttachment::query()->get(['disk', 'path']);

        DB::transaction(function () {
            foreach (self::TABLES as $table) {
                DB::table($table)->delete();
            }
        });

        $missing = 0;
        foreach ($files as $file) {
            Storage::disk($file->disk)->exists($file->path)
                ? Storage::disk($file->disk)->delete($file->path)
                : $missing++;
        }

        $this->info(sprintf(
            'Purge terminée. %d fichier(s) joint(s) supprimé(s)%s.',
            $files->count() - $missing,
            $missing ? ", {$missing} introuvable(s)" : ''
        ));

        return self::SUCCESS;
    }
}
