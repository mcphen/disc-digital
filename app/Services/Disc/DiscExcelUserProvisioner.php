<?php

namespace App\Services\Disc;

use App\Models\Division;
use App\Models\User;
use Database\Seeders\DiscUserSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DiscExcelUserProvisioner
{
    public function __construct(private readonly DiscExcelReader $reader) {}

    /** @return array{created: int, updated: int, accounts: array<int, array{name: string, email: string, division: string}>} */
    public function provision(string $path): array
    {
        $divisions = Division::all()->keyBy(fn (Division $division) => Str::upper($division->code));
        $created = 0;
        $updated = 0;
        $accounts = [];

        // Une cellule « Mme DIENG - Mme NGOM » désigne deux personnes : un compte chacune.
        $people = collect($this->reader->read($path))
            ->flatMap(fn (array $row) => collect(SharedAssignment::split($row['M'] ?? ''))->map(fn (string $name) => [
                'name' => $name,
                'division' => Str::upper(SharedAssignment::split($row['I'] ?? '')[0] ?? ''),
            ]))
            ->filter(fn (array $person) => $person['name'] !== '')
            ->unique(fn (array $person) => Str::lower(Str::ascii($person['name'])))
            ->values();

        foreach ($people as $person) {
            $email = Str::slug(Str::ascii($person['name']), '.').'@disc.local';
            $user = User::firstOrNew(['email' => $email]);
            $isNew = ! $user->exists;
            $user->fill([
                'name' => $person['name'],
                'role' => 'agent',
                'division_id' => $divisions->get($person['division'])?->id,
                'is_active' => true,
            ]);
            $user->email_verified_at ??= now();
            if ($isNew) {
                $user->password = Hash::make(DiscUserSeeder::DEFAULT_PASSWORD);
            }
            $user->save();

            $isNew ? $created++ : $updated++;
            $accounts[] = ['name' => $user->name, 'email' => $user->email, 'division' => $person['division']];
        }

        return compact('created', 'updated', 'accounts');
    }
}
