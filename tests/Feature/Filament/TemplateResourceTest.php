<?php

use App\Filament\Resources\Templates\Pages\CreateTemplate;
use App\Filament\Resources\Templates\Pages\EditTemplate;
use App\Filament\Resources\Templates\Pages\ListTemplates;
use App\Jobs\GenerateTemplateDrafts;
use App\Models\Template;
use App\Models\User;
use App\Models\Word;
use Database\Factories\TemplateFactory;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view templates list', function () {
    $templates = Template::factory()->count(3)->create();

    Livewire::test(ListTemplates::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($templates);
});

test('admin can create a template', function () {
    Livewire::test(CreateTemplate::class)
        ->fillForm([
            'name' => 'Open 15',
            'width' => 15,
            'height' => 15,
            'grid' => TemplateFactory::openGrid(15, 15),
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('templates', [
        'name' => 'Open 15',
        'width' => 15,
        'height' => 15,
        'is_active' => true,
    ]);
});

test('creating a template fails when grid dimensions do not match', function () {
    Livewire::test(CreateTemplate::class)
        ->fillForm([
            'name' => 'Mismatched',
            'width' => 15,
            'height' => 15,
            'grid' => TemplateFactory::openGrid(10, 10),
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['grid']);
});

test('creating a template with short words succeeds (warning surfaced via modal, not hard validation)', function () {
    $grid = TemplateFactory::openGrid(5, 5);
    $grid[0][1] = '#';
    $grid[4][3] = '#';

    Livewire::test(CreateTemplate::class)
        ->fillForm([
            'name' => 'Short word',
            'width' => 5,
            'height' => 5,
            'grid' => $grid,
            'min_word_length' => 3,
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('templates', [
        'name' => 'Short word',
        'min_word_length' => 3,
    ]);
});

test('lowering min_word_length allows grids previously rejected', function () {
    $grid = TemplateFactory::openGrid(5, 5);
    $grid[0][1] = '#';
    $grid[4][3] = '#';

    Livewire::test(CreateTemplate::class)
        ->fillForm([
            'name' => 'Mini-friendly',
            'width' => 5,
            'height' => 5,
            'grid' => $grid,
            'min_word_length' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('templates', [
        'name' => 'Mini-friendly',
        'min_word_length' => 1,
    ]);
});

test('admin can update min_word_length on an existing template', function () {
    $template = Template::factory()->create(['min_word_length' => 3]);

    Livewire::test(EditTemplate::class, ['record' => $template->id])
        ->fillForm(['min_word_length' => 5])
        ->call('save')
        ->assertNotified();

    expect($template->fresh()->min_word_length)->toBe(5);
});

test('admin can save a template with bars in styles', function () {
    Livewire::test(CreateTemplate::class)
        ->fillForm([
            'name' => 'Bars 5x5',
            'width' => 5,
            'height' => 5,
            'grid' => TemplateFactory::openGrid(5, 5),
            'styles' => [
                '0,1' => ['bars' => ['right']],
                '4,3' => ['bars' => ['left']],
            ],
            'min_word_length' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $template = Template::firstWhere('name', 'Bars 5x5');
    expect($template)->not->toBeNull();
    expect($template->styles)->toBe([
        '0,1' => ['bars' => ['right']],
        '4,3' => ['bars' => ['left']],
    ]);
});

test('saving a template without bars stores null styles', function () {
    Livewire::test(CreateTemplate::class)
        ->fillForm([
            'name' => 'No bars',
            'width' => 5,
            'height' => 5,
            'grid' => TemplateFactory::openGrid(5, 5),
            'styles' => [],
            'min_word_length' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Template::firstWhere('name', 'No bars')->styles)->toBeNull();
});

test('admin can edit a template', function () {
    $template = Template::factory()->create(['name' => 'Old name']);

    Livewire::test(EditTemplate::class, ['record' => $template->id])
        ->fillForm(['name' => 'New name'])
        ->call('save')
        ->assertNotified();

    expect($template->fresh()->name)->toBe('New name');
});

test('admin can delete a template', function () {
    $template = Template::factory()->create();

    Livewire::test(EditTemplate::class, ['record' => $template->id])
        ->callAction('delete')
        ->assertNotified();

    $this->assertSoftDeleted('templates', ['id' => $template->id]);
});

test('non-admin cannot access templates admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/templates')
        ->assertForbidden();
});

test('saving a template clears the cached template list for its dimensions', function () {
    Cache::put('grid_templates_15x15', ['cached'], now()->addHour());

    Template::factory()->square(15)->create();

    expect(Cache::get('grid_templates_15x15'))->toBeNull();
});

test('deleting a template clears the cached template list for its dimensions', function () {
    $template = Template::factory()->square(15)->create();
    Cache::put('grid_templates_15x15', ['cached'], now()->addHour());

    $template->delete();

    expect(Cache::get('grid_templates_15x15'))->toBeNull();
});

test('admin can generate procedural templates as inactive drafts', function () {
    Livewire::test(ListTemplates::class)
        ->callAction('generateProcedural', data: [
            'size' => 9,
            'style' => 'standard',
            'candidate_count' => 2,
            'seed' => 5,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Generation complete');

    $drafts = Template::with('annotation')->where('width', 9)->get();

    expect($drafts)->not->toBeEmpty();

    foreach ($drafts as $draft) {
        expect($draft->is_active)->toBeFalse()
            ->and($draft->annotation?->philosophy)->toContain('Procedurally generated standard grid');
    }
});

test('procedural generation reports a style the size does not support', function () {
    Livewire::test(ListTemplates::class)
        ->callAction('generateProcedural', data: [
            'size' => 9,
            'style' => 'themed',
            'candidate_count' => 1,
        ])
        ->assertNotified('Generation failed');

    expect(Template::count())->toBe(0);
});

test('the procedural modal shows a lever section for every group of settings', function () {
    Livewire::test(ListTemplates::class)
        ->mountAction('generateProcedural')
        ->assertMountedActionModalSee([
            'Minimum touching blocks',
            'Shape targets',
            'Word-length mix',
            'Scoring weights',
            'Search',
        ]);
});

test('procedural generation applies the levers and records them on the draft', function () {
    Livewire::test(ListTemplates::class)
        ->callAction('generateProcedural', data: [
            'size' => 11,
            'style' => 'standard',
            'candidate_count' => 1,
            'seed' => 7,
            'min_touching_blocks' => 0,
            'min_density' => 18,
            'max_density' => 21,
            'max_cheaters' => 2,
            'share_3' => 30,
            'weight_block_band' => 40,
            'search_attempts_per_template' => 1,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Generation complete');

    $draft = Template::with('annotation')->where('width', 11)->sole();
    $blocks = collect($draft->grid)->flatten()->filter(fn ($cell) => $cell === '#')->count();

    expect($draft->is_active)->toBeFalse()
        // 18-21% of 121 cells.
        ->and($blocks)->toBeBetween(22, 25)
        ->and($draft->annotation->philosophy)
        ->toContain('minTouchingBlocks=0')
        ->toContain('minBlocks=22')
        ->toContain('maxBlocks=25')
        ->toContain('maxCheaters=2')
        ->toContain('blockBand=40')
        ->toContain('attemptsPerTemplate=1');
});

test('procedural generation reports levers that contradict each other', function () {
    Livewire::test(ListTemplates::class)
        ->callAction('generateProcedural', data: [
            'size' => 11,
            'style' => 'standard',
            'candidate_count' => 1,
            'min_density' => 30,
            'max_density' => 10,
        ])
        ->assertNotified('Generation failed');

    expect(Template::count())->toBe(0);
});

test('words to fit from the repeater are reserved in the generated draft', function () {
    Livewire::test(ListTemplates::class)
        ->callAction('generateProcedural', data: [
            'size' => 15,
            'style' => 'standard',
            'candidate_count' => 1,
            'seed' => 5,
            'seed_words' => [
                ['word' => 'Hello World', 'direction' => 'across'],
                ['word' => 'Lucky Seven', 'direction' => 'down'],
            ],
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Generation complete');

    $draft = Template::with('annotation')->where('width', 15)->sole();

    expect($draft->annotation->best_for)->toBe('Fits HELLOWORLD across row 4; LUCKYSEVEN down column 4.')
        ->and(array_slice($draft->grid[3], 0, 11))->toBe([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '#']);
});

test('a word that cannot fit the chosen size is reported', function () {
    Livewire::test(ListTemplates::class)
        ->callAction('generateProcedural', data: [
            'size' => 13,
            'style' => 'standard',
            'candidate_count' => 1,
            'seed_words' => [['word' => 'ELEVENLETTER', 'direction' => 'across']],
        ])
        ->assertNotified('Generation failed');

    expect(Template::count())->toBe(0);
});

test('grids above the queue threshold are generated in the background', function () {
    Queue::fake();

    Livewire::test(ListTemplates::class)
        ->callAction('generateProcedural', data: [
            'size' => GenerateTemplateDrafts::QUEUE_ABOVE_SIZE + 2,
            'style' => 'standard',
            'candidate_count' => 2,
            'seed' => 9,
            'seed_words' => [['word' => 'Hello World', 'direction' => 'across']],
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Generation queued');

    Queue::assertPushed(GenerateTemplateDrafts::class, function (GenerateTemplateDrafts $job): bool {
        return $job->size === GenerateTemplateDrafts::QUEUE_ABOVE_SIZE + 2
            && $job->count === 2
            && $job->seed === 9
            && $job->requestedById === $this->admin->id
            && $job->seedWords === [['word' => 'Hello World', 'direction' => 'across']];
    });

    expect(Template::count())->toBe(0);
});

test('settings that cannot work are reported before a large grid is queued', function () {
    Queue::fake();

    Livewire::test(ListTemplates::class)
        ->callAction('generateProcedural', data: [
            'size' => 27,
            'style' => 'standard',
            'candidate_count' => 1,
            'seed_words' => [['word' => 'TWENTYFOURLETTERSLONGWORD', 'direction' => 'across']],
        ])
        ->assertNotified('Generation failed');

    Queue::assertNothingPushed();
});

test('the largest size offered is 35x35', function () {
    Livewire::test(ListTemplates::class)
        ->mountAction('generateProcedural')
        ->assertMountedActionModalSee('35×35');
});

test('the fill check reports a template the word list can fill', function () {
    foreach (['CAR', 'OLE', 'WET', 'COW', 'ALE', 'RET'] as $word) {
        Word::factory()->word($word)->create();
    }

    $template = Template::factory()->square(3)->create(['name' => 'Tiny']);

    Livewire::test(ListTemplates::class)
        ->callAction(TestAction::make('checkFill')->table($template), ['timeout' => 5])
        ->assertHasNoFormErrors()
        ->assertNotified('Tiny filled');
});

test('the fill check reports a template the word list cannot fill', function () {
    $template = Template::factory()->square(3)->create(['name' => 'Tiny']);

    Livewire::test(EditTemplate::class, ['record' => $template->id])
        ->callAction('checkFill', ['timeout' => 5])
        ->assertNotified('Tiny did not fill');
});
