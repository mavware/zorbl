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

test('stats page sort field is synced to url', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.stats')
        ->call('sortBy', 'solve_time_seconds')
        ->assertSet('sortField', 'solve_time_seconds');
});
