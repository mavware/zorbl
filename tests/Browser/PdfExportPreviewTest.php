<?php

use App\Models\Crossword;
use App\Models\User;

it('shows a preview of the PDF in the export modal and re-renders it when a setting changes', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->withBlocks()->create(['title' => 'Preview Puzzle']);

    $this->actingAs($user);

    $previewData = "Alpine.\$data(document.querySelector('[data-testid=\"pdf-preview\"]'))";
    $frameSource = "document.querySelector('[data-testid=\"pdf-preview\"] iframe').src";

    $page = visit(route('crosswords.index'));

    $page->click('[aria-label="More actions"]')
        ->click('Export PDF')
        ->assertSee('PDF Export Settings')
        ->wait(3)
        ->assertScript("{$frameSource}.startsWith('blob:')", true)
        ->assertScript("{$previewData}.failed", false);

    $firstPreview = $page->script($frameSource);

    $page->click('Landscape')
        ->wait(3)
        ->assertScript("{$frameSource}.startsWith('blob:')", true)
        ->assertScript("{$frameSource} !== ".json_encode($firstPreview), true)
        ->assertNoJavaScriptErrors();
});
