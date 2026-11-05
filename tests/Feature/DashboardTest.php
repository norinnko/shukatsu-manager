<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_groups_events_by_urgency(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $user = User::factory()->create();

        Event::factory()->for($user)->create(['title' => 'overdue', 'starts_at' => now()->subDays(2)]);
        Event::factory()->for($user)->create(['title' => 'today', 'starts_at' => now()->setTime(18, 0)]);
        Event::factory()->for($user)->create(['title' => 'all day today', 'starts_at' => now()->startOfDay(), 'all_day' => true]);
        Event::factory()->for($user)->create(['title' => 'soon', 'starts_at' => now()->addDays(5)]);
        Event::factory()->for($user)->create(['title' => 'done', 'starts_at' => now()->addDay(), 'status' => EventStatus::Done]);
        Event::factory()->create(['title' => 'someone else', 'starts_at' => now()->addDay()]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('overdue', 1)
                ->where('overdue.0.title', 'overdue')
                ->has('today', 2)
                ->has('soon', 1)
                ->where('soon.0.title', 'soon'));
    }
}
