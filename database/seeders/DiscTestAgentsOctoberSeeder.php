<?php

namespace Database\Seeders;

/**
 * Jeu de test du 30 octobre (docs/DISC_fichier_test_agents_30_octobre.xlsx), même structure que
 * le fichier de test agents. À lancer après `php artisan disc:purge-courriers` pour repartir de zéro.
 */
class DiscTestAgentsOctoberSeeder extends DiscTestAgentsSeeder
{
    public const FILE = 'docs/DISC_fichier_test_agents_30_octobre.xlsx';
}
