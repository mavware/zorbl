<?php

namespace App\Actions;

use App\Models\User;

class ExportAccount
{
    /**
     * Build a portable JSON-ready representation of everything we hold for a
     * user. Used by the data-export download endpoint to satisfy the GDPR
     * Article 20 "right to data portability" obligation.
     *
     * @return array<string, mixed>
     */
    public function __invoke(User $user): array
    {
        $user->loadMissing([
            'crosswords',
            'puzzleAttempts',
            'clueEntries',
            'crosswordLikes',
            'favoriteLists.crosswords',
            'achievements',
            'supportTickets.responses',
            'contestEntries',
            'webhookEndpoints',
            'blockedTags',
        ]);

        return [
            'exported_at' => now()->toIso8601String(),
            'format_version' => 1,
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
                'copyright_name' => $user->copyright_name,
                'bio' => $user->bio,
                'google_id' => $user->google_id,
                'current_streak' => $user->current_streak,
                'longest_streak' => $user->longest_streak,
                'last_solve_date' => $user->last_solve_date,
                'notification_preferences' => $user->notification_preferences,
                'created_at' => optional($user->created_at)->toIso8601String(),
            ],
            'subscription' => $user->subscriptions()->get()->map(fn ($s) => [
                'type' => $s->type,
                'stripe_status' => $s->stripe_status,
                'stripe_price' => $s->stripe_price,
                'quantity' => $s->quantity,
                'trial_ends_at' => optional($s->trial_ends_at)->toIso8601String(),
                'ends_at' => optional($s->ends_at)->toIso8601String(),
                'created_at' => optional($s->created_at)->toIso8601String(),
            ])->all(),
            'crosswords' => $user->crosswords->map->toArray()->all(),
            'puzzle_attempts' => $user->puzzleAttempts->map->toArray()->all(),
            'clue_entries' => $user->clueEntries->map->toArray()->all(),
            'crossword_likes' => $user->crosswordLikes->map(fn ($l) => [
                'crossword_id' => $l->crossword_id,
                'created_at' => optional($l->created_at)->toIso8601String(),
            ])->all(),
            'favorite_lists' => $user->favoriteLists->map(fn ($list) => [
                'name' => $list->name,
                'created_at' => optional($list->created_at)->toIso8601String(),
                'crossword_ids' => $list->crosswords->pluck('id')->all(),
            ])->all(),
            'achievements' => $user->achievements->map->toArray()->all(),
            'support_tickets' => $user->supportTickets->map(fn ($t) => [
                'subject' => $t->subject,
                'status' => $t->status,
                'created_at' => optional($t->created_at)->toIso8601String(),
                'responses' => $t->responses->map(fn ($r) => [
                    'body' => $r->body,
                    'from_user_id' => $r->user_id,
                    'created_at' => optional($r->created_at)->toIso8601String(),
                ])->all(),
            ])->all(),
            'contest_entries' => $user->contestEntries->map->toArray()->all(),
            'webhook_endpoints' => $user->webhookEndpoints->map(fn ($w) => [
                'url' => $w->url,
                'events' => $w->events ?? null,
                'created_at' => optional($w->created_at)->toIso8601String(),
            ])->all(),
            'blocked_tags' => $user->blockedTags->pluck('slug')->all(),
        ];
    }
}
