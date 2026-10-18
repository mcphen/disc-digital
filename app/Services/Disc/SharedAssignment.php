<?php

namespace App\Services\Disc;

/**
 * Lecture des imputations multiples saisies dans une seule cellule du registre Excel :
 * « DP/DRI », « DP, DRI », « Mme DIENG - Mme NGOM », « Awa et Fatou »...
 * Le premier élément est le pilote (division ou agent responsable), les suivants les contributeurs.
 */
class SharedAssignment
{
    /**
     * Le tiret n'est un séparateur qu'entouré d'espaces : « Jean-Pierre » reste un seul nom.
     *
     * @return array<int, string>
     */
    public static function split(?string $value): array
    {
        $parts = preg_split('/\s*[\/,;+&]\s*|\s+-\s+|\s+et\s+/iu', trim((string) $value)) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn (string $part) => $part !== '')));
    }
}
