<?php

namespace App\Support;

use App\Enums\CompanyPhase;
use App\Enums\CompanyPriority;
use App\Enums\CompanyStage;
use App\Enums\EsCategory;
use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Imports the initial data (profile, companies, events, ES answers) from the
 * JSON format documented in database/seed-data/shukatsu.example.json.
 *
 * Existing records are never overwritten, so the import is safe to re-run
 * after editing data in the app.
 */
class ShukatsuImporter
{
    /** @var array<string, int> */
    private array $counts = [];

    /**
     * @return array<string, int> Number of newly created records per type.
     */
    public function importFile(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("File not found: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $this->import($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int>
     */
    public function import(array $data): array
    {
        $this->counts = ['companies' => 0, 'events' => 0, 'es_answers' => 0, 'educations' => 0, 'certifications' => 0];

        DB::transaction(function () use ($data) {
            $user = $this->importUser($data['user'] ?? throw new InvalidArgumentException('"user" is required.'));

            if (isset($data['profile'])) {
                $user->profile()->firstOrCreate([], $data['profile']);
            }

            foreach ($data['educations'] ?? [] as $education) {
                $created = $user->educations()->firstOrCreate(
                    Arr::only($education, ['school', 'entered_on']),
                    $education,
                );
                $this->count('educations', $created->wasRecentlyCreated);
            }

            foreach ($data['certifications'] ?? [] as $certification) {
                $created = $user->certifications()->firstOrCreate(
                    Arr::only($certification, ['name']),
                    $certification,
                );
                $this->count('certifications', $created->wasRecentlyCreated);
            }

            foreach ($data['companies'] ?? [] as $company) {
                $this->importCompany($user, $company);
            }
        });

        return $this->counts;
    }

    /**
     * @param  array{email: string, name?: string}  $data
     */
    private function importUser(array $data): User
    {
        return User::firstOrCreate(
            ['email' => strtolower($data['email'])],
            ['name' => $data['name'] ?? $data['email']],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importCompany(User $user, array $data): void
    {
        $company = $user->companies()->firstOrCreate(['name' => $data['name']], [
            'phase' => CompanyPhase::from($data['phase'] ?? 'intern'),
            'stage' => CompanyStage::from($data['stage'] ?? 'interested'),
            'priority' => isset($data['priority']) ? CompanyPriority::from($data['priority']) : null,
            ...Arr::only($data, [
                'industry', 'tags', 'mypage_url', 'mypage_login_id', 'recruit_url',
                'test_type', 'next_action', 'result_expected_on', 'memo',
            ]),
        ]);
        $this->count('companies', $company->wasRecentlyCreated);

        foreach ($data['events'] ?? [] as $event) {
            $this->importEvent($user, $company, $event);
        }

        foreach ($data['es_answers'] ?? [] as $answer) {
            $created = $user->esAnswers()->firstOrCreate(
                ['company_id' => $company->id, 'question' => $answer['question']],
                [
                    'category' => EsCategory::from($answer['category'] ?? 'other'),
                    'answer' => $answer['answer'],
                    'char_limit' => $answer['char_limit'] ?? null,
                ],
            );
            $this->count('es_answers', $created->wasRecentlyCreated);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importEvent(User $user, Company $company, array $data): void
    {
        $allDay = (bool) ($data['all_day'] ?? false);
        $startsAt = CarbonImmutable::parse($data['starts_at']);
        $endsAt = isset($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : null;

        if ($allDay) {
            $startsAt = $startsAt->startOfDay();
            $endsAt = $endsAt?->startOfDay();
        }

        $dueAt = $allDay ? ($endsAt ?? $startsAt)->endOfDay() : ($endsAt ?? $startsAt);

        $event = $user->events()->firstOrCreate(
            ['company_id' => $company->id, 'title' => $data['title'], 'starts_at' => $startsAt],
            [
                'type' => EventType::from($data['type'] ?? 'other'),
                'ends_at' => $endsAt,
                'all_day' => $allDay,
                'online_url' => $data['online_url'] ?? null,
                'location' => $data['location'] ?? null,
                'memo' => $data['memo'] ?? null,
                // Old entries from the previous tool are treated as finished
                // so the dashboard does not open with a wall of overdue items.
                'status' => $dueAt->isPast() ? EventStatus::Done : EventStatus::Upcoming,
                'source' => EventSource::Import,
                'sync_google' => false,
            ],
        );
        $this->count('events', $event->wasRecentlyCreated);
    }

    private function count(string $type, bool $created): void
    {
        if ($created) {
            $this->counts[$type]++;
        }
    }
}
