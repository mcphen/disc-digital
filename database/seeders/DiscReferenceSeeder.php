<?php

namespace Database\Seeders;

use App\Models\CourrierType;
use App\Models\DiscSetting;
use App\Models\Division;
use Illuminate\Database\Seeder;

class DiscReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'DRI', 'name' => 'Division des Relations Institutionnelles'],
            ['code' => 'DP', 'name' => 'Division Portefeuille'],
            ['code' => 'DSSE', 'name' => 'Division Suivi, Synthèse et des Études'],
        ] as $division) {
            Division::updateOrCreate(['code' => $division['code']], $division + ['is_active' => true]);
        }

        foreach ([
            ['code' => 'ordinary', 'name' => 'Ordinaire', 'requires_response' => true, 'agent_delay_days' => 5, 'director_delay_days' => 2, 'is_active' => true],
            ['code' => 'confidential', 'name' => 'Confidentiel', 'requires_response' => true, 'agent_delay_days' => 5, 'director_delay_days' => 2, 'is_active' => true],
            ['code' => 'advice', 'name' => 'Avis', 'requires_response' => true, 'agent_delay_days' => 19, 'director_delay_days' => 2, 'is_active' => false],
            ['code' => 'no_response', 'name' => 'Ne nécessite pas de réponse', 'requires_response' => false, 'agent_delay_days' => null, 'director_delay_days' => null, 'is_active' => true],
        ] as $type) {
            CourrierType::updateOrCreate(['code' => $type['code']], $type);
        }

        foreach ([
            ['key' => 'alert_green_max', 'value' => '0.50', 'label' => 'Seuil maximal de l’alerte verte'],
            ['key' => 'alert_yellow_max', 'value' => '0.80', 'label' => 'Seuil maximal de l’alerte jaune'],
        ] as $setting) {
            DiscSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
