<?php

use App\Services\PdfMetaAnswerBlanks;

/**
 * Each line as a string: `_` per blank, punctuation as itself, a space between words.
 *
 * @return list<string>
 */
function metaAnswerBlankLines(string $pattern, float $width): array
{
    return array_map(
        fn (array $line): string => implode('', array_map(fn (array $cell): string => match ($cell['type']) {
            'slot' => '_',
            'literal' => $cell['char'],
            'gap' => $cell['width'] > 10 ? ' ' : '',
        }, $line['cells'])),
        (new PdfMetaAnswerBlanks)->lines($pattern, $width),
    );
}

it('draws a blank per letter and keeps punctuation and word breaks', function () {
    expect(metaAnswerBlankLines("____ '_' ____", 500.0))->toBe(["____ '_' ____"]);
});

it('breaks lines between words, never wider than the space given', function () {
    $lines = (new PdfMetaAnswerBlanks)->lines('_____ _____ _____ _____', 200.0);

    expect(metaAnswerBlankLines('_____ _____ _____ _____', 200.0))->toBe(['_____ _____', '_____ _____'])
        ->and(max(array_column($lines, 'width')))->toBeLessThanOrEqual(200.0);
});

it('breaks a word too long for one line across several', function () {
    $lines = metaAnswerBlankLines(str_repeat('_', 20), 100.0);

    expect(count($lines))->toBeGreaterThan(1)
        ->and(implode('', $lines))->toBe(str_repeat('_', 20));
});

it('has no lines for a pattern without blanks or punctuation', function () {
    expect((new PdfMetaAnswerBlanks)->lines('   ', 500.0))->toBe([]);
});
