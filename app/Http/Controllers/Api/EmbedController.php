<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Crossword;
use Illuminate\Http\JsonResponse;

class EmbedController extends Controller
{
    /**
     * Return puzzle data for embedding on external sites.
     */
    public function show(Crossword $crossword): JsonResponse
    {
        abort_unless($crossword->is_published, 404);

        $data = [
            'id' => $crossword->id,
            'title' => $crossword->title,
            'author' => $crossword->author,
            'width' => $crossword->width,
            'height' => $crossword->height,
            'grid' => $crossword->grid,
            'clues_across' => $crossword->clues_across,
            'clues_down' => $crossword->clues_down,
            'styles' => $crossword->styles,
            'prefilled' => $crossword->prefilled,
            'solution' => $crossword->obfuscateSolution(),
            'solution_encoding' => 'xor_b64',
        ];

        return response()->json($data)->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Handle CORS preflight requests.
     */
    public function preflight(): JsonResponse
    {
        return response()->json(null, 204)->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]);
    }
}
