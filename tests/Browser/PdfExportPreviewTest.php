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
        ->click('Download PDF')
        ->assertSee('Download Puzzle')
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

it('shows the PDF options only while PDF is the chosen format, and names the format on the button', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->withBlocks()->create(['title' => 'Format Puzzle']);

    $this->actingAs($user);

    $page = visit(route('crosswords.editor', $crossword));

    $page->click('[data-test="editor-download-button"]')
        ->assertSee('Download Puzzle')
        ->assertSee('PDF (Print-Ready)')
        ->assertVisible('[data-test="download-pdf-options"]')
        ->assertSeeIn('[data-test="download-confirm"]', 'Download PDF');

    $page->click('[data-test="download-format"] [data-format="puz"]')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=\"download-pdf-options\"]')).display", 'none')
        ->assertScript("document.querySelector('[data-test=\"download-confirm\"]').innerText.trim()", 'Download .puz');

    $page->click('[data-test="download-format"] [data-format="pdf"]')
        ->assertVisible('[data-test="download-pdf-options"]')
        ->assertNoJavaScriptErrors();
});
