<?php

use App\Enums\PuzzleType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Zorbl\CrosswordIO\GridNumberer;

return new class extends Migration
{
    public function up(): void
    {
        $diamondCells = function (int $width, int $height): array {
            $centerR = intdiv($height, 2);
            $set = [];
            for ($r = 0; $r < $height; $r++) {
                for ($c = 0; $c < $width; $c++) {
                    $distance = abs($r - $centerR) + abs($c - intdiv($width, 2));
                    if ($distance > $centerR) {
                        $set[$r.','.$c] = true;
                    }
                }
            }

            return $set;
        };

        $numberer = app(GridNumberer::class);

        DB::table('crosswords')
            ->where('puzzle_type', PuzzleType::Diamond->value)
            ->orderBy('id')
            ->each(function (object $row) use ($diamondCells, $numberer): void {
                $grid = json_decode($row->grid, true);
                $solution = json_decode($row->solution, true);
                if (! is_array($grid) || ! is_array($solution)) {
                    return;
                }

                $diamondMask = $diamondCells((int) $row->width, (int) $row->height);
                $changed = false;

                foreach ($grid as $r => $rowCells) {
                    foreach ($rowCells as $c => $cell) {
                        if (isset($diamondMask[$r.','.$c]) && $cell === '#') {
                            $grid[$r][$c] = null;
                            $solution[$r][$c] = null;
                            $changed = true;
                        }
                    }
                }

                if (! $changed) {
                    return;
                }

                $result = $numberer->number($grid, (int) $row->width, (int) $row->height, json_decode($row->styles ?? 'null', true) ?? []);

                DB::table('crosswords')
                    ->where('id', $row->id)
                    ->update([
                        'grid' => json_encode($result['grid']),
                        'solution' => json_encode($solution),
                    ]);
            });
    }

    public function down(): void
    {
        // Not reversible — replacing voids with blocks would change puzzle semantics.
    }
};
