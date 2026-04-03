<?php

use App\Services\GridTemplateProvider;

test('returns templates for standard sizes', function () {
    $provider = app(GridTemplateProvider::class);

    expect($provider->getTemplates(5, 5))->toHaveCount(2)
        ->and($provider->getTemplates(11, 11))->toHaveCount(2)
        ->and($provider->getTemplates(13, 13))->toHaveCount(2)
        ->and($provider->getTemplates(15, 15))->toHaveCount(5)
        ->and($provider->getTemplates(21, 21))->toHaveCount(3);
});

test('returns empty array for non-standard sizes', function () {
    $provider = app(GridTemplateProvider::class);

    expect($provider->getTemplates(7, 7))->toBe([])
        ->and($provider->getTemplates(9, 9))->toBe([])
        ->and($provider->getTemplates(15, 21))->toBe([]);
});

test('each template has correct dimensions', function () {
    $provider = app(GridTemplateProvider::class);
    $sizes = [[5, 5], [11, 11], [13, 13], [15, 15], [21, 21]];

    foreach ($sizes as [$w, $h]) {
        foreach ($provider->getTemplates($w, $h) as $template) {
            expect($template['grid'])->toHaveCount($h, "Template '{$template['name']}' has wrong height for {$w}x{$h}");

            foreach ($template['grid'] as $row) {
                expect($row)->toHaveCount($w, "Template '{$template['name']}' has wrong width for {$w}x{$h}");
            }
        }
    }
});

test('each template has 180-degree rotational symmetry', function () {
    $provider = app(GridTemplateProvider::class);
    $sizes = [[5, 5], [11, 11], [13, 13], [15, 15], [21, 21]];

    foreach ($sizes as [$w, $h]) {
        foreach ($provider->getTemplates($w, $h) as $template) {
            $grid = $template['grid'];

            for ($r = 0; $r < $h; $r++) {
                for ($c = 0; $c < $w; $c++) {
                    $mr = $h - 1 - $r;
                    $mc = $w - 1 - $c;
                    $isBlock = $grid[$r][$c] === '#';
                    $mirrorBlock = $grid[$mr][$mc] === '#';

                    expect($isBlock)->toBe($mirrorBlock, "Template '{$template['name']}' symmetry fails at ($r,$c) vs ($mr,$mc)");
                }
            }
        }
    }
});

test('all words are at least 3 letters long', function () {
    $provider = app(GridTemplateProvider::class);
    $sizes = [[5, 5], [11, 11], [13, 13], [15, 15], [21, 21]];

    foreach ($sizes as [$w, $h]) {
        foreach ($provider->getTemplates($w, $h) as $template) {
            $grid = $template['grid'];
            $name = $template['name'];

            // Check across words
            for ($r = 0; $r < $h; $r++) {
                $len = 0;

                for ($c = 0; $c <= $w; $c++) {
                    if ($c < $w && $grid[$r][$c] !== '#') {
                        $len++;
                    } else {
                        expect($len === 0 || $len >= 3)->toBeTrue("Template '$name' has across word of length $len at row $r");
                        $len = 0;
                    }
                }
            }

            // Check down words
            for ($c = 0; $c < $w; $c++) {
                $len = 0;

                for ($r = 0; $r <= $h; $r++) {
                    if ($r < $h && $grid[$r][$c] !== '#') {
                        $len++;
                    } else {
                        expect($len === 0 || $len >= 3)->toBeTrue("Template '$name' has down word of length $len at col $c");
                        $len = 0;
                    }
                }
            }
        }
    }
});

test('each template has a name', function () {
    $provider = app(GridTemplateProvider::class);
    $sizes = [[5, 5], [11, 11], [13, 13], [15, 15], [21, 21]];

    foreach ($sizes as [$w, $h]) {
        foreach ($provider->getTemplates($w, $h) as $template) {
            expect($template)->toHaveKey('name')
                ->and($template['name'])->toBeString()->not->toBeEmpty();
        }
    }
});
