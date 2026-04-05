<?php

use App\Services\GridTemplateProvider;

test('returns templates for all supported sizes', function () {
    $provider = app(GridTemplateProvider::class);

    $expectedCounts = [
        2 => 0,
        3 => 1,
        4 => 3,
        5 => 4,
        6 => 4,
    ];

    foreach ($expectedCounts as $size => $count) {
        expect($provider->getTemplates($size, $size))
            ->toHaveCount($count, "Expected $count templates for {$size}x{$size}");
    }

    // All sizes 7-27 should have 5 templates
    for ($size = 7; $size <= 27; $size++) {
        expect($provider->getTemplates($size, $size))
            ->toHaveCount(5, "Expected 5 templates for {$size}x{$size}");
    }
});

test('returns empty array for non-standard sizes', function () {
    $provider = app(GridTemplateProvider::class);

    expect($provider->getTemplates(2, 2))->toBe([])
        ->and($provider->getTemplates(28, 28))->toBe([])
        ->and($provider->getTemplates(15, 21))->toBe([]);
});

test('each template has correct dimensions', function () {
    $provider = app(GridTemplateProvider::class);

    for ($n = 3; $n <= 27; $n++) {
        foreach ($provider->getTemplates($n, $n) as $template) {
            expect($template['grid'])->toHaveCount($n, "Template '{$template['name']}' has wrong height for {$n}x{$n}");

            foreach ($template['grid'] as $row) {
                expect($row)->toHaveCount($n, "Template '{$template['name']}' has wrong width for {$n}x{$n}");
            }
        }
    }
});

test('each template has 180-degree rotational symmetry', function () {
    $provider = app(GridTemplateProvider::class);

    for ($n = 3; $n <= 27; $n++) {
        foreach ($provider->getTemplates($n, $n) as $template) {
            $grid = $template['grid'];

            for ($r = 0; $r < $n; $r++) {
                for ($c = 0; $c < $n; $c++) {
                    $mr = $n - 1 - $r;
                    $mc = $n - 1 - $c;
                    $isBlock = $grid[$r][$c] === '#';
                    $mirrorBlock = $grid[$mr][$mc] === '#';

                    expect($isBlock)->toBe($mirrorBlock, "Template '{$template['name']}' ({$n}x{$n}) symmetry fails at ($r,$c) vs ($mr,$mc)");
                }
            }
        }
    }
});

test('all words are at least 3 letters long', function () {
    $provider = app(GridTemplateProvider::class);

    for ($n = 3; $n <= 27; $n++) {
        foreach ($provider->getTemplates($n, $n) as $template) {
            $grid = $template['grid'];
            $name = $template['name'];

            // Check across words
            for ($r = 0; $r < $n; $r++) {
                $len = 0;

                for ($c = 0; $c <= $n; $c++) {
                    if ($c < $n && $grid[$r][$c] !== '#') {
                        $len++;
                    } else {
                        expect($len === 0 || $len >= 3)->toBeTrue("Template '$name' ({$n}x{$n}) has across word of length $len at row $r");
                        $len = 0;
                    }
                }
            }

            // Check down words
            for ($c = 0; $c < $n; $c++) {
                $len = 0;

                for ($r = 0; $r <= $n; $r++) {
                    if ($r < $n && $grid[$r][$c] !== '#') {
                        $len++;
                    } else {
                        expect($len === 0 || $len >= 3)->toBeTrue("Template '$name' ({$n}x{$n}) has down word of length $len at col $c");
                        $len = 0;
                    }
                }
            }
        }
    }
});

test('each template has a name', function () {
    $provider = app(GridTemplateProvider::class);

    for ($n = 3; $n <= 27; $n++) {
        foreach ($provider->getTemplates($n, $n) as $template) {
            expect($template)->toHaveKey('name')
                ->and($template['name'])->toBeString()->not->toBeEmpty();
        }
    }
});
