<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\TicketResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketResponse>
 */
class TicketResponseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(2),
            'is_admin_response' => false,
        ];
    }

    public function adminResponse(): static
    {
        return $this->state(fn () => ['is_admin_response' => true]);
    }
}
