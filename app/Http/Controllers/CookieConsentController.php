<?php

namespace App\Http\Controllers;

use App\Models\CookieConsent;
use App\Support\CookieConsentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CookieConsentController extends Controller
{
    public function store(Request $request, CookieConsentManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'choice' => ['required', 'in:'.CookieConsent::CHOICE_ACCEPT_ALL.','.CookieConsent::CHOICE_REJECT_NON_ESSENTIAL],
        ]);

        $manager->recordChoice($request, $validated['choice']);

        return response()->json(['ok' => true]);
    }
}
