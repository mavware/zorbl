<?php

use App\Models\Word;
use App\Services\SpreadWordlistImporter;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Rescores the word list from Spread the Wordlist so Quick Fill prefers
     * lively fill. Words the list doesn't know are kept at the unvetted score.
     *
     * An empty words table has nothing to rescore and the seeder imports the
     * list itself, so fresh installs and the test environment skip this.
     */
    public function up(): void
    {
        if (app()->runningUnitTests() || ! Word::query()->exists()) {
            return;
        }

        app(SpreadWordlistImporter::class)->import();
    }

    /**
     * The scores this replaced were letter-frequency estimates that nothing
     * depends on any more, so there is nothing worth restoring.
     */
    public function down(): void {}
};
