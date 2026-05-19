<?php

use App\Enums\TeamRole;
use App\Models\Crossword;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('teams page can be rendered', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('Teams');
});

test('teams page requires authentication', function () {
    $this->get(route('teams.index'))
        ->assertRedirect(route('login'));
});

test('user can create a team', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::teams.index')
        ->set('name', 'Puzzle Builders')
        ->set('description', 'We build puzzles together')
        ->call('createTeam');

    $this->assertDatabaseHas('teams', [
        'name' => 'Puzzle Builders',
        'description' => 'We build puzzles together',
        'owner_id' => $user->id,
    ]);

    $team = Team::where('owner_id', $user->id)->first();
    expect($team->hasMember($user))->toBeTrue();
    expect($team->memberRole($user))->toBe(TeamRole::Owner);
});

test('team name is required', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::teams.index')
        ->set('name', '')
        ->call('createTeam')
        ->assertHasErrors(['name' => 'required']);
});

test('team index shows user teams', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $user->id, 'name' => 'My Team']);
    $team->members()->attach($user->id, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('My Team');
});

test('user can delete their own team', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $team->members()->attach($user->id, ['role' => TeamRole::Owner->value]);

    Livewire::actingAs($user)
        ->test('pages::teams.index')
        ->call('deleteTeam', $team->id);

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

test('user cannot delete another users team', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($other->id, ['role' => TeamRole::Editor->value]);

    Livewire::actingAs($other)
        ->test('pages::teams.index')
        ->call('deleteTeam', $team->id)
        ->assertForbidden();
});

test('team show page can be rendered by member', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $user->id, 'name' => 'Test Team']);
    $team->members()->attach($user->id, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user)
        ->get(route('teams.show', $team))
        ->assertOk()
        ->assertSee('Test Team');
});

test('non-member cannot view team page', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);

    $this->actingAs($stranger)
        ->get(route('teams.show', $team))
        ->assertForbidden();
});

test('owner can add a member to the team', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $newMember = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);

    Livewire::actingAs($owner)
        ->test('pages::teams.show', ['team' => $team])
        ->set('inviteEmail', $newMember->email)
        ->call('inviteMember');

    expect($team->hasMember($newMember))->toBeTrue();
    expect($team->memberRole($newMember))->toBe(TeamRole::Editor);
});

test('adding a nonexistent email shows error', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);

    Livewire::actingAs($owner)
        ->test('pages::teams.show', ['team' => $team])
        ->set('inviteEmail', 'nobody@example.com')
        ->call('inviteMember')
        ->assertHasErrors('inviteEmail');
});

test('adding a duplicate member shows error', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $member = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member->id, ['role' => TeamRole::Editor->value]);

    Livewire::actingAs($owner)
        ->test('pages::teams.show', ['team' => $team])
        ->set('inviteEmail', $member->email)
        ->call('inviteMember')
        ->assertHasErrors('inviteEmail');
});

test('non-owner cannot add members', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $editor = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($editor->id, ['role' => TeamRole::Editor->value]);

    Livewire::actingAs($editor)
        ->test('pages::teams.show', ['team' => $team])
        ->set('inviteEmail', 'someone@example.com')
        ->call('inviteMember')
        ->assertForbidden();
});

test('owner can remove a member', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $member = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member->id, ['role' => TeamRole::Editor->value]);

    Livewire::actingAs($owner)
        ->test('pages::teams.show', ['team' => $team])
        ->call('removeMember', $member->id);

    expect($team->fresh()->hasMember($member))->toBeFalse();
});

test('owner cannot remove themselves', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);

    Livewire::actingAs($owner)
        ->test('pages::teams.show', ['team' => $team])
        ->call('removeMember', $owner->id);

    expect($team->fresh()->hasMember($owner))->toBeTrue();
});

test('member can leave a team', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $member = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member->id, ['role' => TeamRole::Editor->value]);

    Livewire::actingAs($member)
        ->test('pages::teams.show', ['team' => $team])
        ->call('leaveTeam')
        ->assertRedirect(route('teams.index'));

    expect($team->fresh()->hasMember($member))->toBeFalse();
});

test('user can assign puzzle to team', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $team->members()->attach($user->id, ['role' => TeamRole::Owner->value]);

    $crossword = Crossword::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::teams.show', ['team' => $team])
        ->call('assignPuzzle', $crossword->id);

    expect($crossword->fresh()->team_id)->toBe($team->id);
});

test('user can unassign puzzle from team', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $team->members()->attach($user->id, ['role' => TeamRole::Owner->value]);

    $crossword = Crossword::factory()->for($user)->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::teams.show', ['team' => $team])
        ->call('unassignPuzzle', $crossword->id);

    expect($crossword->fresh()->team_id)->toBeNull();
});

test('team member can edit team puzzle via policy', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $editor = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($editor->id, ['role' => TeamRole::Editor->value]);

    $crossword = Crossword::factory()->for($owner)->create(['team_id' => $team->id]);

    expect($editor->can('update', $crossword))->toBeTrue();
    expect($editor->can('view', $crossword))->toBeTrue();
});

test('non-team-member cannot edit team puzzle', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);

    $crossword = Crossword::factory()->for($owner)->create(['team_id' => $team->id]);

    expect($stranger->can('update', $crossword))->toBeFalse();
});

test('only puzzle owner can delete team puzzle', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $editor = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($editor->id, ['role' => TeamRole::Editor->value]);

    $crossword = Crossword::factory()->for($owner)->create(['team_id' => $team->id]);

    expect($owner->can('delete', $crossword))->toBeTrue();
    expect($editor->can('delete', $crossword))->toBeFalse();
});

test('owner can update team details', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id, 'name' => 'Old Name']);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);

    Livewire::actingAs($owner)
        ->test('pages::teams.show', ['team' => $team])
        ->call('openEditModal')
        ->set('editName', 'New Name')
        ->set('editDescription', 'Updated description')
        ->call('updateTeam');

    $team->refresh();
    expect($team->name)->toBe('New Name');
    expect($team->description)->toBe('Updated description');
});

test('deleting team unassigns all puzzles', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $team->members()->attach($user->id, ['role' => TeamRole::Owner->value]);

    $crossword = Crossword::factory()->for($user)->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::teams.index')
        ->call('deleteTeam', $team->id);

    expect($crossword->fresh()->team_id)->toBeNull();
});

test('removing a member unassigns their puzzles from team', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $member = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member->id, ['role' => TeamRole::Editor->value]);

    $crossword = Crossword::factory()->for($member)->create(['team_id' => $team->id]);

    Livewire::actingAs($owner)
        ->test('pages::teams.show', ['team' => $team])
        ->call('removeMember', $member->id);

    expect($crossword->fresh()->team_id)->toBeNull();
});
