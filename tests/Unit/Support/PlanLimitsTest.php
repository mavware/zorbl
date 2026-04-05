<?php

use App\Support\PlanLimits;

describe('Free tier', function () {
    it('limits puzzles to 5', function () {
        $limits = new PlanLimits(isPro: false);

        expect($limits->maxPuzzles())->toBe(5);
    });

    it('gives grandfathered users 10 puzzles', function () {
        $limits = new PlanLimits(isPro: false, isGrandfathered: true);

        expect($limits->maxPuzzles())->toBe(10);
    });

    it('blocks AI features', function () {
        $limits = new PlanLimits(isPro: false);

        expect($limits->monthlyAiFills())->toBe(0)
            ->and($limits->monthlyAiClues())->toBe(0);
    });

    it('limits favorite lists to 3', function () {
        $limits = new PlanLimits(isPro: false);

        expect($limits->maxFavoriteLists())->toBe(3);
    });

    it('blocks premium export formats', function () {
        $limits = new PlanLimits(isPro: false);

        expect($limits->canExportPuz())->toBeFalse()
            ->and($limits->canExportJpz())->toBeFalse()
            ->and($limits->canExportPdf())->toBeFalse();
    });

    it('has 60 req/min API rate limit', function () {
        $limits = new PlanLimits(isPro: false);

        expect($limits->apiRateLimit())->toBe(60);
    });

    it('reports not pro', function () {
        $limits = new PlanLimits(isPro: false);

        expect($limits->isPro())->toBeFalse();
    });
});

describe('Pro tier', function () {
    it('allows unlimited puzzles', function () {
        $limits = new PlanLimits(isPro: true);

        expect($limits->maxPuzzles())->toBe(PHP_INT_MAX);
    });

    it('allows 50 AI fills per month', function () {
        $limits = new PlanLimits(isPro: true);

        expect($limits->monthlyAiFills())->toBe(50);
    });

    it('allows 50 AI clue generations per month', function () {
        $limits = new PlanLimits(isPro: true);

        expect($limits->monthlyAiClues())->toBe(50);
    });

    it('allows unlimited favorite lists', function () {
        $limits = new PlanLimits(isPro: true);

        expect($limits->maxFavoriteLists())->toBe(PHP_INT_MAX);
    });

    it('allows all export formats', function () {
        $limits = new PlanLimits(isPro: true);

        expect($limits->canExportPuz())->toBeTrue()
            ->and($limits->canExportJpz())->toBeTrue()
            ->and($limits->canExportPdf())->toBeTrue();
    });

    it('has 120 req/min API rate limit', function () {
        $limits = new PlanLimits(isPro: true);

        expect($limits->apiRateLimit())->toBe(120);
    });

    it('reports pro', function () {
        $limits = new PlanLimits(isPro: true);

        expect($limits->isPro())->toBeTrue();
    });
});
