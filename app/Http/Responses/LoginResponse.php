<?php

namespace App\Http\Responses;

use App\Models\Crossword;
use App\Models\User;
use App\Services\AnonymousUserManager;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function __construct(private readonly AnonymousUserManager $anonManager) {}

    public function toResponse($request): Response
    {
        $this->mergeAnonymousPuzzlesIntoLoggedInUser($request);

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * If the user logged in while a guest-builder cookie is still attached
     * to the request, hand any puzzles owned by that anonymous user over to
     * the real account they just signed into and remove the anonymous row.
     */
    private function mergeAnonymousPuzzlesIntoLoggedInUser(Request $request): void
    {
        $loggedIn = $request->user();
        if (! $loggedIn instanceof User || $loggedIn->isAnonymous()) {
            return;
        }

        $anon = $this->anonManager->findForRequest($request);
        if ($anon === null || $anon->id === $loggedIn->id) {
            return;
        }

        Crossword::query()
            ->where('user_id', $anon->id)
            ->update(['user_id' => $loggedIn->id]);

        $anon->delete();
        $this->anonManager->forgetCookie();
    }
}
