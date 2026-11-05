<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\User;
use App\Support\ShukatsuImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShukatsuImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_example_file_imports_and_is_idempotent(): void
    {
        $this->travelTo('2026-09-30 12:00:00');
        $path = database_path('seed-data/shukatsu.example.json');
        $importer = app(ShukatsuImporter::class);

        $first = $importer->importFile($path);
        $second = $importer->importFile($path);

        $this->assertSame(1, $first['companies']);
        $this->assertSame(2, $first['events']);
        $this->assertSame(0, array_sum($second));

        $user = User::sole();
        $this->assertSame('taro@example.com', $user->email);
        $this->assertCount(2, $user->educations);
        $this->assertSame(EventStatus::Upcoming, $user->events()->where('title', 'ES締切')->sole()->status);
    }

    public function test_past_events_are_imported_as_done(): void
    {
        $this->travelTo('2026-09-30 12:00:00');

        app(ShukatsuImporter::class)->import([
            'user' => ['email' => 'me@example.com'],
            'companies' => [[
                'name' => 'A社',
                'events' => [
                    ['type' => 'deadline', 'title' => 'past', 'starts_at' => '2026-06-01', 'all_day' => true],
                    ['type' => 'deadline', 'title' => 'today', 'starts_at' => '2026-09-30', 'all_day' => true],
                ],
            ]],
        ]);

        $events = User::sole()->events()->pluck('status', 'title');
        $this->assertSame(EventStatus::Done, $events['past']);
        $this->assertSame(EventStatus::Upcoming, $events['today']);
    }
}
