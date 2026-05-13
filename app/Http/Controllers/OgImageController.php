<?php

namespace App\Http\Controllers;

use App\Models\Crossword;
use App\Support\OgImageGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class OgImageController extends Controller
{
    /**
     * Serve the per-puzzle Open Graph share image. Generated on demand and
     * cached to the local disk keyed by puzzle id + a content hash so the
     * file is automatically invalidated when the puzzle changes. An ETag
     * derived from the same hash lets caches revalidate cheaply with 304s
     * once max-age expires.
     */
    public function crossword(Request $request, Crossword $crossword, OgImageGenerator $generator): Response
    {
        abort_unless($crossword->is_published, 404);

        $crossword->loadMissing('user');

        $hash = $this->versionHash($crossword);
        $etag = '"'.$hash.'"';

        $headers = [
            'Content-Type' => 'image/png',
            // 7 days fresh, 30 days SWR. The URL is stable, so we can't go
            // `immutable`; ETag below makes revalidation cheap.
            'Cache-Control' => 'public, max-age=604800, stale-while-revalidate=2592000',
            'ETag' => $etag,
        ];

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        $cachePath = sprintf('og-images/crossword-%d-%s.png', $crossword->id, $hash);
        $disk = Storage::disk('local');

        if (! $disk->exists($cachePath)) {
            $disk->put($cachePath, $generator->render($crossword));
        }

        return response($disk->get($cachePath), 200, $headers);
    }

    private function versionHash(Crossword $crossword): string
    {
        return substr(
            hash('sha256', implode('|', [
                $crossword->id,
                (string) $crossword->updated_at?->getTimestamp(),
                (string) $crossword->title,
            ])),
            0,
            12,
        );
    }
}
