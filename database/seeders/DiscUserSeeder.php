<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DiscUserSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'Disc@2026';

    public function run(): void
    {
        $divisionIds = Division::pluck('id', 'code');

        foreach ([
            ['name' => 'Administrateur DISC', 'email' => 'admin@disc.sn', 'role' => 'admin'],
            ['name' => 'Directeur DISC', 'email' => 'directeur@disc.sn', 'role' => 'director'],
            ['name' => 'Secrétariat DISC', 'email' => 'secretariat@disc.sn', 'role' => 'secretariat'],
            ['name' => 'Auditeur DISC', 'email' => 'auditeur@disc.sn', 'role' => 'auditor'],
            ['name' => 'Chef Division DRI', 'email' => 'chef.dri@disc.sn', 'role' => 'division_head', 'division' => 'DRI'],
            ['name' => 'Chef Division DP', 'email' => 'chef.dp@disc.sn', 'role' => 'division_head', 'division' => 'DP'],
            ['name' => 'Chef Division DSSE', 'email' => 'chef.dsse@disc.sn', 'role' => 'division_head', 'division' => 'DSSE'],
            ['name' => 'Agent DRI', 'email' => 'agent.dri@disc.sn', 'role' => 'agent', 'division' => 'DRI'],
            ['name' => 'Agent DP', 'email' => 'agent.dp@disc.sn', 'role' => 'agent', 'division' => 'DP'],
            ['name' => 'Agent DSSE', 'email' => 'agent.dsse@disc.sn', 'role' => 'agent', 'division' => 'DSSE'],
        ] as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'division_id' => isset($account['division']) ? $divisionIds->get($account['division']) : null,
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );
        }
    }
}
