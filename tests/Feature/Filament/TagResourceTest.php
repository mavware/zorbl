<?php

use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view tags list', function () {
    $tags = Tag::factory()->count(3)->create();

    Livewire::test(ListTags::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($tags);
});

test('admin can create a tag', function () {
    Livewire::test(CreateTag::class)
        ->fillForm([
            'name' => 'Pop Culture',
        ])
        ->call('create')
        ->assertNotified();

    $tag = Tag::where('name', 'Pop Culture')->first();
    expect($tag)->not->toBeNull()
        ->and($tag->slug)->toBe('pop-culture');
});

test('creating a tag requires a name', function () {
    Livewire::test(CreateTag::class)
        ->fillForm([
            'name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required'])
        ->assertNotNotified();
});

test('tag names must be unique', function () {
    Tag::factory()->create(['name' => 'Sports']);

    Livewire::test(CreateTag::class)
        ->fillForm([
            'name' => 'Sports',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique'])
        ->assertNotNotified();
});

test('admin can edit a tag', function () {
    $tag = Tag::factory()->create(['name' => 'Old Name']);

    Livewire::test(EditTag::class, ['record' => $tag->id])
        ->fillForm([
            'name' => 'New Name',
        ])
        ->call('save')
        ->assertNotified();

    expect($tag->fresh()->name)->toBe('New Name');
});

test('admin can delete a tag', function () {
    $tag = Tag::factory()->create(['name' => 'Temporary']);

    Livewire::test(EditTag::class, ['record' => $tag->id])
        ->callAction('delete')
        ->assertNotified();

    expect(Tag::where('name', 'Temporary')->exists())->toBeFalse();
});

test('non-admin cannot access tags admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/tags')
        ->assertForbidden();
});
