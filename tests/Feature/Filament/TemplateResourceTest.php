<?php

use App\Filament\Resources\Templates\Pages\CreateTemplate;
use App\Filament\Resources\Templates\Pages\EditTemplate;
use App\Filament\Resources\Templates\Pages\ListTemplates;
use App\Models\Template;
use App\Models\User;
use Database\Factories\TemplateFactory;
use Illuminate\Support\Facades\Cache;
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

test('creating a template fails without rotational symmetry', function () {
    $grid = TemplateFactory::openGrid(5, 5);
    $grid[0][0] = '#';

    Livewire::test(CreateTemplate::class)
        ->fillForm([
            'name' => 'Asymmetric',
            'width' => 5,
            'height' => 5,
            'grid' => $grid,
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['grid']);
});

test('creating a template fails when a word is shorter than 3 cells', function () {
    $grid = TemplateFactory::openGrid(5, 5);
    $grid[0][1] = '#';
    $grid[4][3] = '#';

    Livewire::test(CreateTemplate::class)
        ->fillForm([
            'name' => 'Short word',
            'width' => 5,
            'height' => 5,
            'grid' => $grid,
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['grid']);
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

    $this->assertDatabaseMissing('templates', ['id' => $template->id]);
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
