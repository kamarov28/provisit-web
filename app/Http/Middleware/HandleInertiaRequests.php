<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initial' => $user->initial,
                    'role' => $user->role,
                    'team' => $user->team,
                    'position' => $user->position,
                    'phone' => $user->phone,
                    'notes' => $user->notes,
                    'avatar_url' => $user->avatar_url,
                    'can_view_all_visits' => (bool) $user->can_view_all_visits,
                    'can_edit_all_visits' => (bool) $user->can_edit_all_visits,
                    'can_archive_visit' => (bool) $user->can_archive_visit,
                    'can_delete_visit' => (bool) $user->can_delete_visit,
                ] : null,
            ],
        ];
    }
}