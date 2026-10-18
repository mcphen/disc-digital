<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-disc');
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('administration/users/Index', [
            'users' => User::query()
                ->with('division:id,code,name')
                ->when($search, fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'roleLabels' => $this->roles(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manage-disc');

        return $this->form();
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-disc');
        $data = $this->validateUser($request);
        $data['password'] = Hash::make($data['password']);
        $data['email_verified_at'] = now();
        $data['division_id'] = $this->divisionForRole($data);
        $user = User::create($data);

        return to_route('administration.users.edit', $user)->with('success', 'Utilisateur créé.');
    }

    public function edit(User $user): Response
    {
        Gate::authorize('manage-disc');

        return $this->form($user->load('division'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-disc');
        $data = $this->validateUser($request, $user);

        if ($request->user()->is($user) && (! $data['is_active'] || $data['role'] !== 'admin')) {
            throw ValidationException::withMessages([
                'role' => 'Vous ne pouvez pas retirer votre propre accès administrateur.',
            ]);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $data['division_id'] = $this->divisionForRole($data);
        $user->update($data);

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    private function form(?User $user = null): Response
    {
        return Inertia::render('administration/users/Form', [
            'editedUser' => $user,
            'divisions' => Division::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'roleLabels' => $this->roles(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'division_id' => ['nullable', 'required_if:role,agent,division_head', 'exists:divisions,id'],
            'is_active' => ['required', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function divisionForRole(array $data): ?int
    {
        return in_array($data['role'], ['agent', 'division_head'], true)
            ? (int) $data['division_id']
            : null;
    }

    /** @return array<string, string> */
    private function roles(): array
    {
        return [
            'admin' => 'Administrateur',
            'director' => 'Directeur DSP',
            'secretariat' => 'Secrétariat',
            'division_head' => 'Chef de division',
            'agent' => 'Agent',
            'auditor' => 'Auditeur / Consultation',
        ];
    }
}
