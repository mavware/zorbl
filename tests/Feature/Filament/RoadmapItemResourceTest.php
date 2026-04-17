<?php

use App\Filament\Resources\RoadmapItems\Pages\CreateRoadmapItem;
use App\Filament\Resources\RoadmapItems\Pages\EditRoadmapItem;
use App\Filament\Resources\RoadmapItems\Pages\ListRoadmapItems;
use App\Models\RoadmapItem;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view roadmap items list', function () {
    $items = RoadmapItem::factory()->count(3)->create();

    Livewire::test(ListRoadmapItems::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($items);
});

test('admin can search roadmap items by title', function () {
    $target = RoadmapItem::factory()->create(['title' => 'Dark Mode Support']);
    RoadmapItem::factory()->create(['title' => 'Bug Fix ABC']);

    Livewire::test(ListRoadmapItems::class)
        ->searchTable('Dark Mode')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords(RoadmapItem::where('title', 'Bug Fix ABC')->get());
});

test('admin can create a roadmap item', function () {
    Livewire::test(CreateRoadmapItem::class)
        ->fillForm([
            'title' => 'New Feature Request',
            'type' => 'feature',
            'status' => 'planned',
            'sort_order' => 5,
        ])
        ->call('create')
        ->assertNotified();

    $this->assertDatabaseHas('roadmap_items', [
        'title' => 'New Feature Request',
        'type' => 'feature',
        'status' => 'planned',
        'sort_order' => 5,
    ]);
});

test('creating a roadmap item requires title, type, and status', function () {
    Livewire::test(CreateRoadmapItem::class)
        ->fillForm([
            'title' => null,
            'type' => null,
            'status' => null,
            'sort_order' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'title' => 'required',
            'type' => 'required',
            'status' => 'required',
        ])
        ->assertNotNotified();
});

test('admin can edit a roadmap item', function () {
    $item = RoadmapItem::factory()->planned()->create();

    Livewire::test(EditRoadmapItem::class, ['record' => $item->id])
        ->fillForm([
            'title' => 'Updated Roadmap Item',
            'status' => 'in_progress',
        ])
        ->call('save')
        ->assertNotified();

    $item->refresh();
    expect($item->title)->toBe('Updated Roadmap Item')
        ->and($item->status)->toBe('in_progress');
});

test('admin can delete a roadmap item', function () {
    $item = RoadmapItem::factory()->create();

    Livewire::test(EditRoadmapItem::class, ['record' => $item->id])
        ->callAction('delete')
        ->assertNotified();

    $this->assertDatabaseMissing('roadmap_items', ['id' => $item->id]);
});

test('non-admin cannot access roadmap items admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/roadmap-items')
        ->assertForbidden();
});
