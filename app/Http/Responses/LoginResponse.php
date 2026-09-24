<?php

namespace App\Http\Responses;

use App\Services\AnonymousUserManager;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function __construct(private readonly AnonymousUserManager $anonManager) {}

    public function toResponse($request): Response
    {
        $this->anonManager->mergeAnonymousPuzzlesIntoLoggedInUser($request);

        return redirect()->intended(config('fortify.home'));
    }
}
