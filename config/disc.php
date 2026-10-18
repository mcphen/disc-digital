<?php

return [
    'alert_thresholds' => [
        'green_max' => 0.50,
        'yellow_max' => 0.80,
    ],

    // Bilan trimestriel : part des courriers échus traités dans le délai.
    'quarterly' => [
        // Objectif à atteindre (en %).
        'target' => 80,
        // En dessous de ce taux, la situation est critique (en %).
        'alert' => 50,
        // En dessous de ce nombre de courriers échus, le taux reste indicatif.
        'min_sample' => 5,
        // Écart avec le trimestre précédent jugé significatif (en points).
        'significant_change' => 10,
    ],
];
