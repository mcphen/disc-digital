<?php

namespace App\Console\Commands;

use App\Models\Courrier;
use App\Models\User;
use App\Services\Disc\SharedAssignment;
use Database\Seeders\DiscUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Corrige les comptes créés par d'anciens imports à partir d'une cellule désignant plusieurs
 * agents (« Mme DIENG - Mme NGOM ») : un compte par personne, le premier devient responsable
 * des courriers, les autres co-agents, et le compte combiné est désactivé (jamais supprimé).
 */
class SplitSharedAgentAccounts extends Command
{
    protected $signature = 'disc:split-shared-agents {--apply : Enregistrer les changements (sinon simple simulation)}';

    protected $description = 'Sépare les comptes agents combinés (« A - B ») en comptes individuels';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $combined = User::query()
            ->whereIn('role', ['agent', 'division_head'])
            ->get()
            ->filter(fn (User $user) => count(SharedAssignment::split($user->name)) > 1);

        if ($combined->isEmpty()) {
            $this->info('Aucun compte combiné.');

            return self::SUCCESS;
        }

        foreach ($combined as $account) {
            $names = SharedAssignment::split($account->name);
            $lead = Courrier::where('assigned_agent_id', $account->id)->count();
            $co = DB::table('courrier_co_agents')->where('user_id', $account->id)->count();
            $this->line("« {$account->name} » → ".implode(' (responsable), ', [$names[0], implode(', ', array_slice($names, 1))])
                ." (co-agent) · {$lead} courrier(s) en responsable, {$co} en co-agent");

            if ($apply) {
                DB::transaction(fn () => $this->split($account, $names));
            }
        }

        $this->newLine();
        $apply
            ? $this->info('Comptes séparés. Les comptes combinés sont désactivés.')
            : $this->warn('Simulation : rien n’a été modifié. Relancez avec --apply pour appliquer.');

        return self::SUCCESS;
    }

    /** @param array<int, string> $names */
    private function split(User $account, array $names): void
    {
        $people = collect($names)->map(fn (string $name) => $this->person($name, $account));
        [$first, $others] = [$people->first(), $people->slice(1)->pluck('id')];

        Courrier::with('coAgents:id')->where('assigned_agent_id', $account->id)->get()
            ->each(function (Courrier $courrier) use ($first, $others, $account) {
                $courrier->update(['assigned_agent_id' => $first->id]);
                $courrier->syncContributors(
                    $courrier->contributingDivisions()->pluck('divisions.id')->all(),
                    $courrier->coAgents->pluck('id')->reject(fn (int $id) => $id === $account->id)->concat($others)->all(),
                );
                $this->log($courrier, $account);
            });

        Courrier::whereHas('coAgents', fn ($query) => $query->whereKey($account->id))->get()
            ->each(function (Courrier $courrier) use ($people, $account) {
                $courrier->syncContributors(
                    $courrier->contributingDivisions()->pluck('divisions.id')->all(),
                    $courrier->coAgents()->pluck('users.id')->reject(fn (int $id) => $id === $account->id)->concat($people->pluck('id'))->all(),
                );
                $this->log($courrier, $account);
            });

        $account->update(['is_active' => false]);
    }

    /** Compte existant portant ce nom, sinon nouveau compte agent dans la même division. */
    private function person(string $name, User $account): User
    {
        $normalized = Str::lower(Str::ascii($name));
        $existing = User::whereIn('role', ['agent', 'division_head'])->get()
            ->first(fn (User $user) => Str::lower(Str::ascii($user->name)) === $normalized);

        if ($existing) {
            return $existing;
        }

        $user = User::firstOrNew(['email' => Str::slug(Str::ascii($name), '.').'@disc.local']);
        $user->fill(['name' => $name, 'role' => 'agent', 'division_id' => $account->division_id, 'is_active' => true]);
        $user->email_verified_at ??= now();
        $user->password ??= Hash::make(DiscUserSeeder::DEFAULT_PASSWORD);
        $user->save();
        $this->line("  compte créé : {$user->name} <{$user->email}>");

        return $user;
    }

    private function log(Courrier $courrier, User $account): void
    {
        $courrier->events()->create([
            'event' => 'assigned',
            'from_status' => $courrier->status,
            'to_status' => $courrier->status,
            'comment' => "Compte combiné « {$account->name} » séparé en agents individuels.",
            'metadata' => [
                'assigned_agent_id' => $courrier->assigned_agent_id,
                'co_agent_ids' => $courrier->coAgents()->pluck('users.id')->all(),
            ],
        ]);
    }
}
