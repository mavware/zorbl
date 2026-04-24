<?php

use App\Models\RoadmapItem;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
});

function adminUser(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    return $admin;
}

test('authenticated users can view the roadmap', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('roadmap.index'))
        ->assertSuccessful();
});

test('guests cannot view the roadmap', function () {
    $this->get(route('roadmap.index'))
        ->assertRedirect();
});

test('roadmap displays items grouped by status', function () {
    $planned = RoadmapItem::factory()->planned()->create(['title' => 'Planned Feature']);
    $inProgress = RoadmapItem::factory()->inProgress()->create(['title' => 'Active Work']);
    $completed = RoadmapItem::factory()->completed()->create(['title' => 'Done Task']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::roadmap.index')
        ->assertSee('Planned Feature')
        ->assertSee('Active Work')
        ->assertSee('Done Task');
});

test('roadmap shows empty state when no items exist', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::roadmap.index')
        ->assertSee('No roadmap items yet');
});

test('admins can add a roadmap item', function () {
    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->set('newTitle', 'Dark mode support')
        ->set('newDescription', 'Add dark mode toggle for all pages')
        ->set('newType', 'feature')
        ->call('addItem');

    expect(RoadmapItem::where('title', 'Dark mode support')->exists())->toBeTrue();
});

test('non-admins cannot add a roadmap item', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::roadmap.index')
        ->set('newTitle', 'Sneaky feature')
        ->set('newType', 'feature')
        ->call('addItem')
        ->assertForbidden();

    expect(RoadmapItem::where('title', 'Sneaky feature')->exists())->toBeFalse();
});

test('non-admins do not see the add item button', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::roadmap.index')
        ->assertDontSee('Add Item');
});

test('admins see the add item button', function () {
    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->assertSee('Add Item');
});

test('adding a roadmap item defaults to planned status', function () {
    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->set('newTitle', 'New feature idea')
        ->set('newType', 'feature')
        ->call('addItem');

    expect(RoadmapItem::first()->status)->toBe('planned');
});

test('title is required when adding a roadmap item', function () {
    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->set('newTitle', '')
        ->set('newType', 'feature')
        ->call('addItem')
        ->assertHasErrors(['newTitle' => 'required']);
});

test('title must be at least 3 characters', function () {
    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->set('newTitle', 'Ab')
        ->set('newType', 'feature')
        ->call('addItem')
        ->assertHasErrors(['newTitle' => 'min']);
});

test('type must be valid when adding a roadmap item', function () {
    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->set('newTitle', 'Some item')
        ->set('newType', 'invalid_type')
        ->call('addItem')
        ->assertHasErrors(['newType' => 'in']);
});

test('admins can edit a roadmap item', function () {
    $item = RoadmapItem::factory()->planned()->create(['title' => 'Original Title']);

    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->call('openEditModal', $item->id)
        ->set('editTitle', 'Updated Title')
        ->set('editType', 'improvement')
        ->call('saveEdit');

    expect($item->fresh())
        ->title->toBe('Updated Title')
        ->type->toBe('improvement');
});

test('non-admins cannot open the edit modal', function () {
    $item = RoadmapItem::factory()->planned()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::roadmap.index')
        ->call('openEditModal', $item->id)
        ->assertForbidden();
});

test('non-admins cannot save edits even if they bypass the UI', function () {
    $item = RoadmapItem::factory()->planned()->create(['title' => 'Original']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::roadmap.index')
        ->set('editingItemId', $item->id)
        ->set('editTitle', 'Hacked')
        ->set('editType', 'feature')
        ->set('editStatus', 'planned')
        ->call('saveEdit')
        ->assertForbidden();

    expect($item->fresh()->title)->toBe('Original');
});

test('admins can change status to in progress', function () {
    $item = RoadmapItem::factory()->planned()->create();

    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->call('openEditModal', $item->id)
        ->set('editStatus', 'in_progress')
        ->call('saveEdit');

    expect($item->fresh()->status)->toBe('in_progress');
});

test('marking an item completed sets the completed date', function () {
    $item = RoadmapItem::factory()->planned()->create();

    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->call('openEditModal', $item->id)
        ->set('editStatus', 'completed')
        ->call('saveEdit');

    $item->refresh();

    expect($item->status)->toBe('completed')
        ->and($item->completed_date)->not->toBeNull();
});

test('moving a completed item back to planned clears the completed date', function () {
    $item = RoadmapItem::factory()->completed()->create();

    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->call('openEditModal', $item->id)
        ->set('editStatus', 'planned')
        ->call('saveEdit');

    expect($item->fresh())
        ->status->toBe('planned')
        ->completed_date->toBeNull();
});

test('admins can delete a roadmap item', function () {
    $item = RoadmapItem::factory()->create();

    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->call('deleteItem', $item->id);

    expect(RoadmapItem::find($item->id))->toBeNull();
});

test('non-admins cannot delete a roadmap item', function () {
    $item = RoadmapItem::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::roadmap.index')
        ->call('deleteItem', $item->id)
        ->assertForbidden();

    expect(RoadmapItem::find($item->id))->not->toBeNull();
});

test('non-admins do not see edit or delete controls', function () {
    RoadmapItem::factory()->planned()->create(['title' => 'Visible Item']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::roadmap.index')
        ->assertSee('Visible Item')
        ->assertDontSee('openEditModal')
        ->assertDontSee('deleteItem');
});

test('filter by type shows only matching items', function () {
    RoadmapItem::factory()->feature()->create(['title' => 'New Feature XYZ']);
    RoadmapItem::factory()->fix()->create(['title' => 'Bug Fix ABC']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::roadmap.index')
        ->set('filter', 'feature')
        ->assertSee('New Feature XYZ')
        ->assertDontSee('Bug Fix ABC');
});

test('filter all shows every item', function () {
    RoadmapItem::factory()->feature()->create(['title' => 'Feature Item']);
    RoadmapItem::factory()->fix()->create(['title' => 'Fix Item']);
    RoadmapItem::factory()->improvement()->create(['title' => 'Improvement Item']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::roadmap.index')
        ->set('filter', 'all')
        ->assertSee('Feature Item')
        ->assertSee('Fix Item')
        ->assertSee('Improvement Item');
});

test('roadmap item can have an optional target date', function () {
    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->set('newTitle', 'Scheduled feature')
        ->set('newType', 'feature')
        ->set('newTargetDate', '2026-06-15')
        ->call('addItem');

    expect(RoadmapItem::first()->target_date->format('Y-m-d'))->toBe('2026-06-15');
});

test('roadmap item can be added without a target date', function () {
    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->set('newTitle', 'Unscheduled feature')
        ->set('newType', 'feature')
        ->set('newTargetDate', '')
        ->call('addItem');

    expect(RoadmapItem::first()->target_date)->toBeNull();
});

test('roadmap appears in sidebar navigation', function () {
    $this->actingAs(adminUser())
        ->get(route('roadmap.index'))
        ->assertSee('Roadmap');
});

test('description is optional when adding a roadmap item', function () {
    Livewire::actingAs(adminUser())
        ->test('pages::roadmap.index')
        ->set('newTitle', 'No description item')
        ->set('newDescription', '')
        ->set('newType', 'fix')
        ->call('addItem');

    expect(RoadmapItem::first())
        ->title->toBe('No description item')
        ->description->toBeNull();
});
