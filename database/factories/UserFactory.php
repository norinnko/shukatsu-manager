<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user connected Google Calendar.
     */
    public function withGoogleCalendar(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_id' => (string) fake()->randomNumber(9),
            'google_access_token' => 'test-access-token',
            'google_refresh_token' => 'test-refresh-token',
            'google_token_expires_at' => now()->addHour(),
            'google_scopes' => ['https://www.googleapis.com/auth/calendar.events'],
        ]);
    }

    /**
     * Indicate that the user connected Google Calendar and Gmail (read-only).
     */
    public function withGmail(): static
    {
        return $this->withGoogleCalendar()->state(fn (array $attributes) => [
            'google_scopes' => [
                'https://www.googleapis.com/auth/calendar.events',
                'https://www.googleapis.com/auth/gmail.readonly',
            ],
        ]);
    }
}
