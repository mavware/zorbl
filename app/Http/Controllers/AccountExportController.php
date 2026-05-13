<?php

namespace App\Http\Controllers;

use App\Actions\ExportAccount;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountExportController extends Controller
{
    /**
     * Stream the signed-in user's personal data as a JSON download. Satisfies
     * the GDPR Article 20 right to data portability.
     */
    public function download(Request $request, ExportAccount $exportAccount): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $payload = $exportAccount($user);
        $filename = sprintf('%s-data-export-%s.json', str(config('app.name'))->slug(), now()->format('Y-m-d'));

        return response()->streamDownload(
            function () use ($payload) {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            },
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }
}
