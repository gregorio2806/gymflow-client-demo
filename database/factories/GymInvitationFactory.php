<?php

namespace Database\Factories;

use App\Enums\GymRole;
use App\Models\Gym;
use App\Models\GymInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymInvitation>
 */
class GymInvitationFactory extends Factory
{
    /**
     * The plain token of the last created invitation (tests use it to build the accept link).
     */
    public static ?string $lastToken = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => GymRole::Staff,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (GymInvitation $invitation) {
            static::$lastToken = $invitation->issueToken();
        });
    }

    public function expired(): static
    {
        return $this->afterMaking(fn (GymInvitation $invitation) => $invitation->expires_at = now()->subMinute());
    }
}
