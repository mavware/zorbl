<?php

use App\Models\Achievement;
use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('stats page requires authentication', function () {
    $this->get(route('crosswords.stats'))
        ->assertRedirect();
});

test('stats page renders for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.stats'))
        ->assertSuccessful()
        ->assertSee('Solve Statistics');
});

test('stats page shows empty state without completed puzzles', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('Complete puzzles to see your solve history');
});

test('stats page shows total solved count', function () {
    $user = User::factory()->create();

    Crossword::factory()->published()->count(3)->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ])->each(function (Crossword $crossword) use ($user) {
        PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
            'solve_time_seconds' => 120,
        ]);
    });

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('Puzzles Solved');
});

test('stats page shows average solve time', function () {
    $user = User::factory()->create();
    $crossword1 = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);
    $crossword2 = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword1)->completed()->create([
        'solve_time_seconds' => 60,
    ]);
    PuzzleAttempt::factory()->for($user)->for($crossword2)->completed()->create([
        'solve_time_seconds' => 180,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('Average Time')
        ->assertSee('2:00');
});

test('stats page shows fastest solve', function () {
    $user = User::factory()->create();
    $fast = Crossword::factory()->published()->create([
        'title' => 'Speed Puzzle',
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);
    $slow = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    PuzzleAttempt::factory()->for($user)->for($fast)->completed()->create([
        'solve_time_seconds' => 45,
    ]);
    PuzzleAttempt::factory()->for($user)->for($slow)->completed()->create([
        'solve_time_seconds' => 300,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('Fastest Solve')
        ->assertSee('0:45')
        ->assertSee('Speed Puzzle');
});

test('stats page displays solve history table with puzzle details', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'title' => 'History Puzzle',
        'width' => 10,
        'height' => 10,
        'grid' => Crossword::emptyGrid(10, 10),
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'solve_time_seconds' => 240,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('History Puzzle')
        ->assertSee('4:00')
        ->assertSee('10×10');
});

test('stats page can sort by solve time', function () {
    $user = User::factory()->create();
    $puzzle1 = Crossword::factory()->published()->create([
        'title' => 'Quick One',
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);
    $puzzle2 = Crossword::factory()->published()->create([
        'title' => 'Slow One',
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    PuzzleAttempt::factory()->for($user)->for($puzzle1)->completed()->create([
        'solve_time_seconds' => 30,
    ]);
    PuzzleAttempt::factory()->for($user)->for($puzzle2)->completed()->create([
        'solve_time_seconds' => 600,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->call('sortBy', 'solve_time_seconds')
        ->assertSet('sortField', 'solve_time_seconds')
        ->assertSet('sortDirection', 'asc')
        ->assertSee('Quick One')
        ->assertSee('Slow One');
});

test('stats page toggles sort direction on same field', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->call('sortBy', 'solve_time_seconds')
        ->assertSet('sortDirection', 'asc')
        ->call('sortBy', 'solve_time_seconds')
        ->assertSet('sortDirection', 'desc');
});

test('stats page resets sort direction when switching fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->call('sortBy', 'solve_time_seconds')
        ->call('sortBy', 'solve_time_seconds')
        ->assertSet('sortDirection', 'desc')
        ->call('sortBy', 'completed_at')
        ->assertSet('sortField', 'completed_at')
        ->assertSet('sortDirection', 'asc');
});

test('stats page groups times by grid size', function () {
    $user = User::factory()->create();

    $small = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);
    $medium = Crossword::factory()->published()->create([
        'width' => 15,
        'height' => 15,
        'grid' => Crossword::emptyGrid(15, 15),
    ]);
    $large = Crossword::factory()->published()->create([
        'width' => 21,
        'height' => 21,
        'grid' => Crossword::emptyGrid(21, 21),
    ]);

    PuzzleAttempt::factory()->for($user)->for($small)->completed()->create([
        'solve_time_seconds' => 60,
    ]);
    PuzzleAttempt::factory()->for($user)->for($medium)->completed()->create([
        'solve_time_seconds' => 300,
    ]);
    PuzzleAttempt::factory()->for($user)->for($large)->completed()->create([
        'solve_time_seconds' => 900,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('Times by Grid Size')
        ->assertSee('Small')
        ->assertSee('Medium')
        ->assertSee('Large');
});

test('stats page shows current streak', function () {
    $user = User::factory()->create([
        'current_streak' => 5,
        'longest_streak' => 12,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('Current Streak')
        ->assertSee('5 days')
        ->assertSee('Best: 12 days');
});

test('stats page shows achievements', function () {
    $user = User::factory()->create();
    Achievement::factory()->for($user)->create([
        'label' => 'Speed Demon',
        'description' => 'Solved in under a minute',
        'icon' => 'bolt',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('Speed Demon');
});

test('stats page shows empty achievements message', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('Complete puzzles to earn achievements!');
});

test('stats page excludes incomplete attempts from history', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Incomplete Puzzle',
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'is_completed' => false,
        'solve_time_seconds' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertDontSee('Incomplete Puzzle')
        ->assertSee('Complete puzzles to see your solve history');
});

test('stats page formats hours correctly', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Marathon Puzzle',
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'solve_time_seconds' => 3661,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('1:01:01');
});

test('stats page only shows current user attempts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Other User Puzzle',
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    PuzzleAttempt::factory()->for($other)->for($crossword)->completed()->create([
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertDontSee('Other User Puzzle')
        ->assertSee('Complete puzzles to see your solve history');
});

test('stats page groups times by difficulty', function () {
    $user = User::factory()->create();

    $easy = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
        'difficulty_label' => 'Easy',
        'difficulty_score' => 1.5,
    ]);
    $hard = Crossword::factory()->published()->create([
        'width' => 15,
        'height' => 15,
        'grid' => Crossword::emptyGrid(15, 15),
        'difficulty_label' => 'Hard',
        'difficulty_score' => 3.5,
    ]);

    PuzzleAttempt::factory()->for($user)->for($easy)->completed()->create([
        'solve_time_seconds' => 60,
    ]);
    PuzzleAttempt::factory()->for($user)->for($hard)->completed()->create([
        'solve_time_seconds' => 600,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('Times by Difficulty')
        ->assertSee('Easy')
        ->assertSee('Hard');
});

test('stats page difficulty breakdown shows correct stats per level', function () {
    $user = User::factory()->create();

    $medium1 = Crossword::factory()->published()->create([
        'width' => 10,
        'height' => 10,
        'grid' => Crossword::emptyGrid(10, 10),
        'difficulty_label' => 'Medium',
        'difficulty_score' => 2.5,
    ]);
    $medium2 = Crossword::factory()->published()->create([
        'width' => 10,
        'height' => 10,
        'grid' => Crossword::emptyGrid(10, 10),
        'difficulty_label' => 'Medium',
        'difficulty_score' => 2.8,
    ]);

    PuzzleAttempt::factory()->for($user)->for($medium1)->completed()->create([
        'solve_time_seconds' => 120,
    ]);
    PuzzleAttempt::factory()->for($user)->for($medium2)->completed()->create([
        'solve_time_seconds' => 180,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');

    $byDifficulty = $component->instance()->averageByDifficulty;

    expect($byDifficulty)->toHaveCount(1);
    expect($byDifficulty[0]['label'])->toBe('Medium');
    expect($byDifficulty[0]['count'])->toBe(2);
    expect($byDifficulty[0]['average'])->toBe(150);
    expect($byDifficulty[0]['fastest'])->toBe(120);
});

test('stats page difficulty breakdown excludes puzzles without difficulty label', function () {
    $user = User::factory()->create();

    $rated = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
        'difficulty_label' => 'Easy',
        'difficulty_score' => 1.5,
    ]);
    $unrated = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
        'difficulty_label' => null,
        'difficulty_score' => null,
    ]);

    PuzzleAttempt::factory()->for($user)->for($rated)->completed()->create([
        'solve_time_seconds' => 60,
    ]);
    PuzzleAttempt::factory()->for($user)->for($unrated)->completed()->create([
        'solve_time_seconds' => 90,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');

    $byDifficulty = $component->instance()->averageByDifficulty;

    expect($byDifficulty)->toHaveCount(1);
    expect($byDifficulty[0]['label'])->toBe('Easy');
    expect($byDifficulty[0]['count'])->toBe(1);
});

test('stats page difficulty breakdown is ordered Easy, Medium, Hard, Expert', function () {
    $user = User::factory()->create();

    $difficulties = [
        ['label' => 'Expert', 'score' => 4.5],
        ['label' => 'Easy', 'score' => 1.2],
        ['label' => 'Hard', 'score' => 3.5],
        ['label' => 'Medium', 'score' => 2.5],
    ];

    foreach ($difficulties as $d) {
        $crossword = Crossword::factory()->published()->create([
            'width' => 5,
            'height' => 5,
            'grid' => Crossword::emptyGrid(5, 5),
            'difficulty_label' => $d['label'],
            'difficulty_score' => $d['score'],
        ]);

        PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
            'solve_time_seconds' => 120,
        ]);
    }

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');

    $byDifficulty = $component->instance()->averageByDifficulty;

    expect($byDifficulty)->toHaveCount(4);
    expect($byDifficulty[0]['label'])->toBe('Easy');
    expect($byDifficulty[1]['label'])->toBe('Medium');
    expect($byDifficulty[2]['label'])->toBe('Hard');
    expect($byDifficulty[3]['label'])->toBe('Expert');
});

test('stats page hides difficulty section when no rated puzzles solved', function () {
    $user = User::factory()->create();

    $crossword = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
        'difficulty_label' => null,
        'difficulty_score' => null,
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'solve_time_seconds' => 60,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertDontSee('Times by Difficulty');
});

test('stats page sort field is synced to url', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->call('sortBy', 'solve_time_seconds')
        ->assertSet('sortField', 'solve_time_seconds');
});

test('stats page paginates solve history at 15 per page', function () {
    $user = User::factory()->create();

    Crossword::factory()->published()->count(20)->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ])->each(function (Crossword $crossword) use ($user) {
        PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
            'solve_time_seconds' => rand(60, 600),
        ]);
    });

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');
    $paginated = $component->get('paginatedAttempts');

    expect($paginated)->toHaveCount(15);
    expect($paginated->total())->toBe(20);
    expect($paginated->lastPage())->toBe(2);
});

test('stats page shows second page of solve history', function () {
    $user = User::factory()->create();

    Crossword::factory()->published()->count(20)->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ])->each(function (Crossword $crossword) use ($user) {
        PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
            'solve_time_seconds' => rand(60, 600),
        ]);
    });

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats')
        ->set('paginators.page', 2);
    $paginated = $component->get('paginatedAttempts');

    expect($paginated)->toHaveCount(5);
    expect($paginated->currentPage())->toBe(2);
});

test('stats page resets to page 1 when sorting changes', function () {
    $user = User::factory()->create();

    Crossword::factory()->published()->count(20)->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ])->each(function (Crossword $crossword) use ($user) {
        PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
            'solve_time_seconds' => rand(60, 600),
        ]);
    });

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats')
        ->set('paginators.page', 2)
        ->call('sortBy', 'solve_time_seconds');

    $paginated = $component->get('paginatedAttempts');
    expect($paginated->currentPage())->toBe(1);
});

test('stats page summary cards show totals across all pages', function () {
    $user = User::factory()->create();

    Crossword::factory()->published()->count(20)->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ])->each(function (Crossword $crossword) use ($user) {
        PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
            'solve_time_seconds' => 120,
        ]);
    });

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');

    expect($component->get('totalSolved'))->toBe(20);
    expect($component->get('averageTime'))->toBe(120);
});

test('stats page shows solve activity heatmap section', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('Solve Activity')
        ->assertSee('Less')
        ->assertSee('More');
});

test('activity heatmap counts solves per day', function () {
    $user = User::factory()->create();

    $crosswords = Crossword::factory()->published()->count(3)->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    foreach ($crosswords as $crossword) {
        PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
            'completed_at' => today(),
            'solve_time_seconds' => 120,
        ]);
    }

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');
    $heatmap = $component->instance()->activityHeatmap;

    expect($heatmap['totalInRange'])->toBe(3);
    expect($heatmap['days'][today()->format('Y-m-d')])->toBe(3);
});

test('activity heatmap excludes incomplete attempts', function () {
    $user = User::factory()->create();

    $crossword = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'is_completed' => false,
        'completed_at' => null,
        'solve_time_seconds' => null,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');
    $heatmap = $component->instance()->activityHeatmap;

    expect($heatmap['totalInRange'])->toBe(0);
    expect($heatmap['days'])->toBeEmpty();
});

test('activity heatmap excludes other users solves', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $crossword = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    PuzzleAttempt::factory()->for($other)->for($crossword)->completed()->create([
        'completed_at' => today(),
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');
    $heatmap = $component->instance()->activityHeatmap;

    expect($heatmap['totalInRange'])->toBe(0);
});

test('activity heatmap returns weeks covering the past year', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');
    $heatmap = $component->instance()->activityHeatmap;

    expect(count($heatmap['weeks']))->toBeGreaterThanOrEqual(52);
    expect(count($heatmap['weeks']))->toBeLessThanOrEqual(54);
});

test('activity heatmap assigns intensity levels based on solve count', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $crossword = Crossword::factory()->published()->create([
            'width' => 5,
            'height' => 5,
            'grid' => Crossword::emptyGrid(5, 5),
        ]);

        PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
            'completed_at' => today(),
            'solve_time_seconds' => 120,
        ]);
    }

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');
    $heatmap = $component->instance()->activityHeatmap;

    $todayKey = today()->format('Y-m-d');
    $todayCell = null;
    foreach ($heatmap['weeks'] as $week) {
        foreach ($week as $day) {
            if ($day['date'] === $todayKey) {
                $todayCell = $day;
                break 2;
            }
        }
    }

    expect($todayCell)->not->toBeNull();
    expect($todayCell['count'])->toBe(5);
    expect($todayCell['level'])->toBe(4);
});

test('activity heatmap shows total solves in the last year', function () {
    $user = User::factory()->create();

    $crossword = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'completed_at' => today(),
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('1 puzzle solved in the last year');
});

test('activity heatmap pluralizes solve count correctly', function () {
    $user = User::factory()->create();

    foreach (range(1, 2) as $i) {
        $crossword = Crossword::factory()->published()->create([
            'width' => 5,
            'height' => 5,
            'grid' => Crossword::emptyGrid(5, 5),
        ]);

        PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
            'completed_at' => today(),
            'solve_time_seconds' => 120,
        ]);
    }

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->assertSee('2 puzzles solved in the last year');
});

test('activity heatmap includes month labels', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.stats');
    $heatmap = $component->instance()->activityHeatmap;

    expect(count($heatmap['months']))->toBeGreaterThanOrEqual(12);
});
