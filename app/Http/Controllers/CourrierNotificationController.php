<?php

namespace App\Http\Controllers;

use App\Models\CourrierNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourrierNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(['unread', 'actions', 'deadlines', 'validations', 'read', 'all'])],
        ]);
        $category = $filters['category'] ?? 'unread';
        $baseQuery = $request->user()->courrierNotifications();
        $counts = [
            'unread' => (clone $baseQuery)->whereNull('read_at')->count(),
            'actions' => (clone $baseQuery)->whereIn('level', ['REVIEW', 'CORRECT'])->count(),
            'deadlines' => (clone $baseQuery)->whereIn('level', ['JAUNE', 'ROUGE'])->count(),
            'validations' => (clone $baseQuery)->where('level', 'APPROVED')->count(),
            'read' => (clone $baseQuery)->whereNotNull('read_at')->count(),
            'all' => (clone $baseQuery)->count(),
        ];

        $notifications = $baseQuery
            ->with('courrier:id,sequence_number,registration_year')
            ->when($category === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($category === 'actions', fn ($query) => $query->whereIn('level', ['REVIEW', 'CORRECT']))
            ->when($category === 'deadlines', fn ($query) => $query->whereIn('level', ['JAUNE', 'ROUGE']))
            ->when($category === 'validations', fn ($query) => $query->where('level', 'APPROVED'))
            ->when($category === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->latest()
            ->paginate(20)
            ->through(fn (CourrierNotification $notification) => [
                'id' => $notification->id,
                'level' => $notification->level,
                'message' => $notification->message,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
                'courrier' => [
                    'id' => $notification->courrier->id,
                    'registration_number' => $notification->courrier->registration_number,
                ],
            ]);

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
            'filters' => ['category' => $category],
            'counts' => $counts,
        ]);
    }

    public function read(Request $request, CourrierNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return to_route('courriers.show', $notification->courrier_id);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->courrierNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }
}
