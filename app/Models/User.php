<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Eloquent;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
 * @property string|null $copyright_name
 * @property int $current_streak
 * @property int $longest_streak
 * @property string|null $last_solve_date
 * @property-read Collection<int, Achievement> $achievements
 * @property-read int|null $achievements_count
 * @property-read Collection<int, ClueEntry> $clueEntries
 * @property-read int|null $clue_entries_count
 * @property-read Collection<int, CrosswordLike> $crosswordLikes
 * @property-read int|null $crossword_likes_count
 * @property-read Collection<int, Crossword> $crosswords
 * @property-read int|null $crosswords_count
 * @property-read Collection<int, FavoriteList> $favoriteLists
 * @property-read int|null $favorite_lists_count
 * @property-read Collection<int, User> $followers
 * @property-read int|null $followers_count
 * @property-read Collection<int, User> $following
 * @property-read int|null $following_count
 * @property-read Collection<int, Crossword> $likedCrosswords
 * @property-read int|null $liked_crosswords_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, PuzzleAttempt> $puzzleAttempts
 * @property-read int|null $puzzle_attempts_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, SupportTicket> $supportTickets
 * @property-read int|null $support_tickets_count
 * @property-read Collection<int, SupportTicket> $assignedTickets
 * @property-read int|null $assigned_tickets_count
 *
 * @method static UserFactory factory($count = null, $state = [])
 *
 * @mixin Eloquent
 */
#[Fillable(['name', 'email', 'password', 'copyright_name', 'current_streak', 'longest_streak', 'last_solve_date'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    /**
     * @return HasMany<Crossword, $this>
     */
    public function crosswords(): HasMany
    {
        return $this->hasMany(Crossword::class);
    }

    /**
     * @return HasMany<PuzzleAttempt, $this>
     */
    public function puzzleAttempts(): HasMany
    {
        return $this->hasMany(PuzzleAttempt::class);
    }

    /**
     * @return HasMany<ClueEntry, $this>
     */
    public function clueEntries(): HasMany
    {
        return $this->hasMany(ClueEntry::class);
    }

    /**
     * @return HasMany<CrosswordLike, $this>
     */
    public function crosswordLikes(): HasMany
    {
        return $this->hasMany(CrosswordLike::class);
    }

    /**
     * @return BelongsToMany<Crossword, $this>
     */
    public function likedCrosswords(): BelongsToMany
    {
        return $this->belongsToMany(Crossword::class, 'crossword_likes')->withTimestamps();
    }

    /**
     * @return HasMany<FavoriteList, $this>
     */
    public function favoriteLists(): HasMany
    {
        return $this->hasMany(FavoriteList::class);
    }

    /**
     * @return HasMany<Achievement, $this>
     */
    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    /**
     * Users this user is following.
     *
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
    }

    /**
     * Users who follow this user.
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->withTimestamps();
    }

    /**
     * @return HasMany<SupportTicket, $this>
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * @return HasMany<SupportTicket, $this>
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    /**
     * @return HasMany<ContestEntry, $this>
     */
    public function contestEntries(): HasMany
    {
        return $this->hasMany(ContestEntry::class);
    }

    /**
     * Contests the user has entered.
     *
     * @return BelongsToMany<Contest, $this>
     */
    public function contests(): BelongsToMany
    {
        return $this->belongsToMany(Contest::class, 'contest_entries')->withTimestamps();
    }

    /**
     * Check if this user is following another user.
     */
    public function isFollowing(User $user): bool
    {
        return $this->following()->where('following_id', $user->id)->exists();
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->hasRole('Admin');
        }

        return false;
    }
}
