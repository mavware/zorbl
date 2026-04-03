<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'copyright_name'])]
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
