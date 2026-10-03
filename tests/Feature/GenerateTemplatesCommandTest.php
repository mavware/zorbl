<?php

use App\Enums\TemplateStyle;
use App\Models\Template;

test('it saves generated templates as inactive drafts with an annotation', function () {
    $this->artisan('templates:generate', ['size' => 9, '--count' => 2, '--seed' => 5])
        ->assertSuccessful();

    $templates = Template::with(['annotation', 'templateTags'])->get();

    expect($templates)->not->toBeEmpty()
        ->and($templates->count())->toBeLessThanOrEqual(2);

    foreach ($templates as $template) {
        expect($template->is_active)->toBeFalse()
            ->and($template->width)->toBe(9)
            ->and($template->height)->toBe(9)
            ->and($template->annotation)->not->toBeNull()
            ->and($template->annotation->philosophy)->toContain('Procedurally generated standard grid')
            ->and($template->templateTags->pluck('tag')->all())->toContain(TemplateStyle::RotationalSymmetric);
    }
});

test('the activate flag saves live templates', function () {
    $this->artisan('templates:generate', ['size' => 9, '--count' => 1, '--seed' => 5, '--activate' => true])
        ->expectsOutputToContain('active template(s)')
        ->assertSuccessful();

    expect(Template::where('is_active', true)->count())->toBe(1)
        ->and(Template::where('is_active', false)->count())->toBe(0);
});

test('a dry run prints the templates without saving them', function () {
    $this->artisan('templates:generate', ['size' => 9, '--count' => 1, '--seed' => 5, '--dry-run' => true])
        ->expectsOutputToContain('Dry run: nothing saved.')
        ->assertSuccessful();

    expect(Template::count())->toBe(0);
});

test('it rejects an unknown style', function () {
    $this->artisan('templates:generate', ['size' => 9, '--style' => 'cryptic'])
        ->expectsOutputToContain('Unknown style')
        ->assertFailed();

    expect(Template::count())->toBe(0);
});

test('it rejects a size the style does not support', function () {
    $this->artisan('templates:generate', ['size' => 9, '--style' => 'themed'])
        ->expectsOutputToContain('Themed templates need a grid of at least 11×11.')
        ->assertFailed();
});
