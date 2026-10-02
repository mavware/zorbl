<?php

use App\Enums\TemplateStyle;
use App\Models\Template;
use App\Services\TemplateTagger;
use Database\Factories\TemplateFactory;

test('an open 15x15 is tagged as a wide-open, themeless-friendly triple stack', function () {
    $template = Template::factory()->square(15)->create();

    app(TemplateTagger::class)->tag($template);

    expect($template->templateTags()->pluck('tag')->all())->toEqualCanonicalizing([
        TemplateStyle::RotationalSymmetric,
        TemplateStyle::WideOpen,
        TemplateStyle::ThemelessFriendly,
        TemplateStyle::TripleStack,
    ]);
});

test('a dense asymmetric mini is tagged accordingly', function () {
    $grid = TemplateFactory::openGrid(5, 5);
    $grid[0][0] = '#';
    $grid[0][4] = '#';
    $grid[4][0] = '#';
    $grid[4][4] = '#';
    $grid[2][0] = '#';

    $template = Template::factory()->square(5)->create(['grid' => $grid]);

    // The block at (2, 0) leaves (1, 0) checked in one direction only, so the rules count as relaxed.
    expect(app(TemplateTagger::class)->deriveTags($template))->toEqualCanonicalizing([
        TemplateStyle::MiniStyle,
        TemplateStyle::Asymmetric,
        TemplateStyle::Blocky,
        TemplateStyle::RelaxedRules,
    ]);
});

test('existing tags are left alone', function () {
    $template = Template::factory()->square(15)->create();
    $template->templateTags()->create(['tag' => TemplateStyle::ClosedCorners]);

    app(TemplateTagger::class)->tag($template);

    expect($template->templateTags()->pluck('tag')->all())->toBe([TemplateStyle::ClosedCorners]);
});
