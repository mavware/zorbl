<?php

use App\Filament\Resources\RoadmapItems\Pages\CreateRoadmapItem;
use App\Filament\Resources\RoadmapItems\Pages\EditRoadmapItem;
use App\Filament\Resources\RoadmapItems\Pages\ListRoadmapItems;
use App\Models\RoadmapItem;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view roadmap item list', function () {
    $items = RoadmapItem::factory()->count(3)->create();

    Livewire::test(ListRoadmapItems::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($items);
});

test('non-admin cannot access roadmap item list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/roadmap-items')
        ->assertForbidden();
});

test('admin can search roadmap items by title', function () {
    $target = RoadmapItem::factory()->create(['title' => 'Unique Roadmap Feature']);
    RoadmapItem::factory()->count(3)->create();

    Livewire::test(ListRoadmapItems::class)
        ->searchTable('Unique Roadmap Feature')
        ->assertCanSeeTableRecords([$target])
        ->assertCountTableRecords(1);
});

test('admin can search roadmap items by type', function () {
    $feature = RoadmapItem::factory()->feature()->create();
    $fix = RoadmapItem::factory()->fix()->create();

    Livewire::test(ListRoadmapItems::class)
        ->searchTable('feature')
        ->assertCanSeeTableRecords([$feature])
        ->assertCanNotSeeTableRecords([$fix]);
});

test('admin can create a roadmap item', function () {
    Livewire::test(CreateRoadmapItem::class)
        ->fillForm([
            'title' => 'New Feature Idea',
            'description' => 'A detailed description of the feature',
            'type' => 'feature',
            'status' => 'planned',
            'sort_order' => 10,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $this->assertDatabaseHas(RoadmapItem::class, [
        'title' => 'New Feature Idea',
        'type' => 'feature',
        'status' => 'planned',
        'sort_order' => 10,
    ]);
});

test('create roadmap item requires title', function () {
    Livewire::test(CreateRoadmapItem::class)
        ->fillForm([
            'title' => null,
            'type' => 'feature',
            'status' => 'planned',
            'sort_order' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['title' => 'required'])
        ->assertNotNotified();
});

test('create roadmap item requires type', function () {
    Livewire::test(CreateRoadmapItem::class)
        ->fillForm([
            'title' => 'Missing Type',
            'type' => null,
            'status' => 'planned',
            'sort_order' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['type' => 'required'])
        ->assertNotNotified();
});

test('create roadmap item requires status', function () {
    Livewire::test(CreateRoadmapItem::class)
        ->fillForm([
            'title' => 'Missing Status',
            'type' => 'feature',
            'status' => null,
            'sort_order' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['status' => 'required'])
        ->assertNotNotified();
});

test('create roadmap item requires numeric sort order', function () {
    Livewire::test(CreateRoadmapItem::class)
        ->fillForm([
            'title' => 'Bad Sort',
            'type' => 'feature',
            'status' => 'planned',
            'sort_order' => 'not-a-number',
        ])
        ->call('create')
        ->assertHasFormErrors(['sort_order'])
        ->assertNotNotified();
});

test('admin can edit a roadmap item', function () {
    $item = RoadmapItem::factory()->planned()->create(['title' => 'Original Feature']);

    Livewire::test(EditRoadmapItem::class, ['record' => $item->id])
        ->assertFormSet([
            'title' => 'Original Feature',
        ])
        ->fillForm([
            'title' => 'Updated Feature',
            'status' => 'in_progress',
        ])
        ->call('save')
        ->assertNotified();

    $item->refresh();
    expect($item->title)->toBe('Updated Feature')
        ->and($item->status)->toBe('in_progress');
});

test('admin can set target and completed dates', function () {
    $item = RoadmapItem::factory()->create();

    Livewire::test(EditRoadmapItem::class, ['record' => $item->id])
        ->fillForm([
            'target_date' => '2026-06-01',
            'completed_date' => '2026-05-15',
        ])
        ->call('save')
        ->assertNotified();

    $item->refresh();
    expect($item->target_date->format('Y-m-d'))->toBe('2026-06-01')
        ->and($item->completed_date->format('Y-m-d'))->toBe('2026-05-15');
});

test('admin can delete a roadmap item', function () {
    $item = RoadmapItem::factory()->create();

    Livewire::test(EditRoadmapItem::class, ['record' => $item->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    $this->assertModelMissing($item);
});
