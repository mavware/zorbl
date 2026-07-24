<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    public function start(Request $request, User $user): RedirectResponse
    {
        $this->beginImpersonating($request->user(), $user);

        return redirect('/');
    }

    /**
     * Guard the request and begin impersonating the target as the given actor.
     *
     * Shared by the HTTP route and the Filament table actions so the
     * authorization rules and session handling live in one place.
     */
    public function beginImpersonating(?User $actor, User $target): void
    {
        abort_unless($actor && $actor->hasRole('Admin'), 403);
        abort_if($actor->is($target), 403, 'You cannot impersonate yourself.');
        abort_if($target->hasRole('Admin'), 403, 'You cannot impersonate another admin.');
        abort_if(session()->has(self::SESSION_KEY), 409, 'Already impersonating.');

        Auth::loginUsingId($target->id);
        session()->put(self::SESSION_KEY, $actor->id);
    }

    public function stop(Request $request): RedirectResponse
    {
        $originalId = $request->session()->pull(self::SESSION_KEY);

        abort_unless($originalId, 404, 'Not impersonating.');

        Auth::loginUsingId($originalId);

        return redirect('/admin/users');
    }
}
