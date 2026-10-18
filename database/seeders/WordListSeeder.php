<?php

namespace Database\Seeders;

use App\Services\WordListImporter;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class WordListSeeder extends Seeder
{
    /**
     * Load the words table from the bundled word list, the single source the
     * grid filler's dictionary and scores come from.
     */
    public function run(): void
    {
        $this->command->info('Seeding words table from the bundled word list...');

        try {
            $result = app(WordListImporter::class)->import();
        } catch (InvalidArgumentException $e) {
            $this->command->error($e->getMessage());

            return;
        }

        $this->command->info("Read {$result['entries']} entries: scored {$result['imported']} word(s), skipped {$result['skipped']} (unusable or outside 3-21 letters).");
        $this->command->line("{$result['unlisted']} word(s) not in the list are kept at the unvetted score.");
    }
}
