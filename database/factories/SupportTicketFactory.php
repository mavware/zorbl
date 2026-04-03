<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(3),
            'category' => fake()->randomElement(['bug_report', 'feature_request', 'account_issue', 'puzzle_issue', 'general']),
            'status' => 'open',
            'priority' => 'normal',
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => 'open']);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => 'in_progress']);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => 'resolved']);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function bugReport(): static
    {
        return $this->state(fn () => ['category' => 'bug_report']);
    }

    public function featureRequest(): static
    {
        return $this->state(fn () => ['category' => 'feature_request']);
    }

    public function accountIssue(): static
    {
        return $this->state(fn () => ['category' => 'account_issue']);
    }

    public function puzzleIssue(): static
    {
        return $this->state(fn () => ['category' => 'puzzle_issue']);
    }

    public function lowPriority(): static
    {
        return $this->state(fn () => ['priority' => 'low']);
    }

    public function highPriority(): static
    {
        return $this->state(fn () => ['priority' => 'high']);
    }

    public function urgent(): static
    {
        return $this->state(fn () => ['priority' => 'urgent']);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['assigned_to' => $user->id]);
    }
}
