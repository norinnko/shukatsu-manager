<?php

namespace App\Models;

use App\Enums\CompanyPhase;
use App\Enums\CompanyPriority;
use App\Enums\CompanyStage;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $industry
 * @property CompanyPhase $phase
 * @property CompanyStage $stage
 * @property CompanyPriority|null $priority
 * @property list<string>|null $tags
 * @property string|null $mypage_url
 * @property string|null $mypage_login_id
 * @property string|null $recruit_url
 * @property string|null $test_type
 * @property string|null $next_action
 * @property Carbon|null $result_expected_on
 * @property string|null $memo
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'industry', 'phase', 'stage', 'priority', 'tags', 'mypage_url', 'mypage_login_id',
    'recruit_url', 'test_type', 'next_action', 'result_expected_on', 'memo', 'archived_at',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SerializesLocalDates;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'phase' => CompanyPhase::class,
            'stage' => CompanyStage::class,
            'priority' => CompanyPriority::class,
            'tags' => 'array',
            'result_expected_on' => 'date:Y-m-d',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasMany<EsAnswer, $this> */
    public function esAnswers(): HasMany
    {
        return $this->hasMany(EsAnswer::class);
    }

    /**
     * @param  Builder<Company>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
