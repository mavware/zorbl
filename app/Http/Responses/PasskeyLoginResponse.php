<?php

namespace App\Http\Responses;

use App\Services\AnonymousUserManager;
use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function __construct(private readonly AnonymousUserManager $anonManager) {}

    /**
     * Passkey sign-ins arrive as XHR from the browser's WebAuthn client, so the
     * redirect is handed back as JSON for the client to follow. The guest-puzzle
     * merge mirrors the password login response so both paths behave alike.
     */
    public function toResponse($request): Response
    {
        $this->anonManager->mergeAnonymousPuzzlesIntoLoggedInUser($request);

        $redirect = redirect()->intended(config('fortify.home'));

        if ($request->wantsJson()) {
            return new JsonResponse(['redirect' => $redirect->getTargetUrl()]);
        }

        return $redirect;
    }
}
