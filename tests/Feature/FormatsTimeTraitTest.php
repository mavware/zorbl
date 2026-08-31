<?php

use App\Support\Concerns\FormatsTime;

beforeEach(function () {
    $this->formatter = new class
    {
        use FormatsTime;
    };
});

test('formats seconds under one hour as M:SS', function () {
    expect($this->formatter->formatTime(332))->toBe('5:32');
});

test('formats seconds over one hour as H:MM:SS', function () {
    expect($this->formatter->formatTime(3735))->toBe('1:02:15');
});

test('formats null as em dash', function () {
    expect($this->formatter->formatTime(null))->toBe("\u{2014}");
});

test('formats zero seconds as 0:00', function () {
    expect($this->formatter->formatTime(0))->toBe('0:00');
});

test('pads minutes and seconds with leading zeros', function () {
    expect($this->formatter->formatTime(61))->toBe('1:01');
    expect($this->formatter->formatTime(3601))->toBe('1:00:01');
});

test('formats exactly one hour', function () {
    expect($this->formatter->formatTime(3600))->toBe('1:00:00');
});

test('formats large values', function () {
    expect($this->formatter->formatTime(86399))->toBe('23:59:59');
});
