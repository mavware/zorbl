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
        $actor = $request->user();

        abort_unless($actor && $actor->hasRole('Admin'), 403);
        abort_if($actor->is($user), 403, 'You cannot impersonate yourself.');
        abort_if($user->hasRole('Admin'), 403, 'You cannot impersonate another admin.');
        abort_if($request->session()->has(self::SESSION_KEY), 409, 'Already impersonating.');

        Auth::loginUsingId($user->id);
        $request->session()->put(self::SESSION_KEY, $actor->id);

        return redirect('/');
    }

    public function stop(Request $request): RedirectResponse
    {
        $originalId = $request->session()->pull(self::SESSION_KEY);

        abort_unless($originalId, 404, 'Not impersonating.');

        Auth::loginUsingId($originalId);

        return redirect('/admin/users');
    }
}
