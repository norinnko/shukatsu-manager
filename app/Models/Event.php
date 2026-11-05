<?php

namespace App\Models;

use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Concerns\SerializesLocalDates;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $company_id
 * @property EventType $type
 * @property string $title
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $all_day
 * @property string|null $location
 * @property string|null $online_url
 * @property string|null $memo
 * @property EventStatus $status
 * @property EventSource $source
 * @property bool $sync_google
 * @property string|null $google_event_id
 * @property CarbonImmutable|null $google_synced_at
 * @property string|null $google_sync_error
 * @property-read Company|null $company
 */
#[Fillable([
    'company_id', 'type', 'title', 'starts_at', 'ends_at', 'all_day', 'location',
    'online_url', 'memo', 'status', 'source', 'sync_google',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SerializesLocalDates;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'status' => EventStatus::class,
            'source' => EventSource::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'all_day' => 'boolean',
            'sync_google' => 'boolean',
            'google_synced_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @param  Builder<Event>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', EventStatus::Upcoming);
    }

    /**
     * The moment after which the event counts as passed. All-day events
     * stay "today" until midnight.
     */
    public function dueAt(): CarbonImmutable
    {
        $end = $this->ends_at ?? $this->starts_at;

        return $this->all_day ? $end->endOfDay() : $end;
    }

    /**
     * Title as shown in external calendars, e.g. "【SHIFT】一次面接".
     */
    public function displayTitle(): string
    {
        return $this->company ? "【{$this->company->name}】{$this->title}" : $this->title;
    }
}
