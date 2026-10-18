<?php

namespace App\Http\Middleware;

use App\Services\Disc\NavigationCountsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'notifications' => [
                'unread_count' => $request->user()
                    ? $request->user()->courrierNotifications()->whereNull('read_at')->count()
                    : 0,
            ],
            'navigation' => $request->user()
                ? app(NavigationCountsService::class)->for($request->user())
                : [
                    'unassigned' => 0,
                    'division_only' => 0,
                    'mine' => 0,
                    'review' => 0,
                    'corrections' => 0,
                    'signature' => 0,
                    'priority' => 0,
                ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'bulk_undo' => fn () => $request->session()->get('bulk_undo'),
            ],
        ]);
    }
}
