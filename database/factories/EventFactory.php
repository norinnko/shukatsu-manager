<?php

namespace Database\Factories;

use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => EventType::Interview,
            'title' => '一次面接',
            'starts_at' => now()->addDays(3)->setTime(14, 0),
            'all_day' => false,
            'status' => EventStatus::Upcoming,
            'source' => EventSource::Manual,
        ];
    }
}
