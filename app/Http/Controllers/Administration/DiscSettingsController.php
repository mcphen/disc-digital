<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\CourrierType;
use App\Models\DiscSetting;
use App\Models\Division;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DiscSettingsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-disc');
        $settings = DiscSetting::pluck('value', 'key');

        return Inertia::render('administration/Settings', [
            'thresholds' => [
                'green_max' => round(((float) ($settings['alert_green_max'] ?? 0.5)) * 100),
                'yellow_max' => round(((float) ($settings['alert_yellow_max'] ?? 0.8)) * 100),
            ],
            'divisions' => Division::orderBy('code')->get(),
            'types' => CourrierType::orderBy('id')->get(),
        ]);
    }

    public function updateThresholds(Request $request): RedirectResponse
    {
        Gate::authorize('manage-disc');
        $data = $request->validate([
            'green_max' => ['required', 'numeric', 'min:1', 'max:99'],
            'yellow_max' => ['required', 'numeric', 'gt:green_max', 'max:100'],
        ]);

        DiscSetting::updateOrCreate(['key' => 'alert_green_max'], ['value' => (string) ($data['green_max'] / 100)]);
        DiscSetting::updateOrCreate(['key' => 'alert_yellow_max'], ['value' => (string) ($data['yellow_max'] / 100)]);

        return back()->with('success', 'Seuils d’alerte mis à jour.');
    }

    public function storeDivision(Request $request): RedirectResponse
    {
        Gate::authorize('manage-disc');
        Division::create($request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:divisions,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]));

        return back()->with('success', 'Division ajoutée.');
    }

    public function updateDivision(Request $request, Division $division): RedirectResponse
    {
        Gate::authorize('manage-disc');
        $division->update($request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('divisions')->ignore($division)],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]));

        return back()->with('success', 'Division mise à jour.');
    }

    public function storeType(Request $request): RedirectResponse
    {
        Gate::authorize('manage-disc');
        CourrierType::create($this->validateType($request));

        return back()->with('success', 'Type de courrier ajouté.');
    }

    public function updateType(Request $request, CourrierType $courrierType): RedirectResponse
    {
        Gate::authorize('manage-disc');
        $courrierType->update($this->validateType($request, $courrierType));

        return back()->with('success', 'Type de courrier mis à jour.');
    }

    /** @return array<string, mixed> */
    private function validateType(Request $request, ?CourrierType $type = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('courrier_types')->ignore($type)],
            'name' => ['required', 'string', 'max:255'],
            'requires_response' => ['required', 'boolean'],
            'agent_delay_days' => ['nullable', 'required_if:requires_response,true,1', 'integer', 'min:1', 'max:365'],
            'director_delay_days' => ['nullable', 'required_if:requires_response,true,1', 'integer', 'min:1', 'max:365'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $data['requires_response']) {
            $data['agent_delay_days'] = null;
            $data['director_delay_days'] = null;
        }

        return $data;
    }
}
