<?php

namespace App\Services;

use App\Models\ClueEntry;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Picks catalog words that have no clue entries yet and writes clues for them
 * so the clue library keeps growing without manual effort.
 */
class WordClueBackfiller
{
    public const int DEFAULT_LIMIT = 20;

    public const int MIN_WORD_LENGTH = 3;

    public function __construct(private readonly AiWordClueWriter $writer) {}

    /**
     * Words that have no clue entries at all, in random order so a word the
     * writer keeps failing on cannot block every run.
     *
     * @return Collection<int, Word>
     */
    public function candidates(int $count): Collection
    {
        return Word::query()
            ->where('length', '>=', self::MIN_WORD_LENGTH)
            ->whereDoesntHave('clueEntries')
            ->inRandomOrder()
            ->limit(max(1, $count))
            ->get();
    }

    /**
     * Write up to $limit clues for one word and store them as clue entries
     * attributed to the admin, pending review unless $approve is set.
     *
     * @return array{success: bool, inserted: int, message: string}
     *
     * @throws RuntimeException when no Admin user exists to attribute clues to.
     */
    public function backfill(Word $word, int $limit = self::DEFAULT_LIMIT, bool $approve = false): array
    {
        $admin = $this->attributionUser();
        $result = $this->writer->write($word->word, $limit);

        if (! $result['success']) {
            return ['success' => false, 'inserted' => 0, 'message' => $result['message']];
        }

        $now = now();
        $rows = array_map(fn (string $clue): array => [
            'answer' => $word->word,
            'clue' => $clue,
            'crossword_id' => null,
            'user_id' => $admin->id,
            'direction' => null,
            'clue_number' => null,
            'status' => $approve ? ClueEntry::STATUS_APPROVED : ClueEntry::STATUS_PENDING,
            'reviewed_by' => $approve ? $admin->id : null,
            'reviewed_at' => $approve ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $result['clues']);

        ClueEntry::insert($rows);

        return ['success' => true, 'inserted' => count($rows), 'message' => $result['message']];
    }

    private function attributionUser(): User
    {
        $admin = User::role('Admin')->first();

        if ($admin === null) {
            throw new RuntimeException('No Admin role user found to attribute backfilled clues to.');
        }

        return $admin;
    }
}
