<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $avatar
 * @property string|null $google_id
 * @property string|null $google_access_token
 * @property string|null $google_refresh_token
 * @property Carbon|null $google_token_expires_at
 * @property list<string>|null $google_scopes
 * @property string $google_calendar_id
 * @property string|null $gmail_history_id
 * @property Carbon|null $gmail_synced_at
 * @property string|null $gmail_sync_error
 * @property string|null $line_user_id
 * @property Carbon|null $line_linked_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'avatar'])]
#[Hidden(['remember_token', 'google_id', 'google_access_token', 'google_refresh_token', 'google_scopes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
            'google_scopes' => 'array',
            'gmail_synced_at' => 'datetime',
            'line_linked_at' => 'datetime',
        ];
    }

    /**
     * Whether the user granted the scopes needed to write to Google Calendar.
     */
    public function hasGoogleCalendar(): bool
    {
        return $this->hasGoogleScope('https://www.googleapis.com/auth/calendar.events');
    }

    /**
     * Whether the user granted read-only access to Gmail.
     */
    public function hasGmail(): bool
    {
        return $this->hasGoogleScope('https://www.googleapis.com/auth/gmail.readonly');
    }

    private function hasGoogleScope(string $scope): bool
    {
        return $this->google_refresh_token !== null
            && in_array($scope, $this->google_scopes ?? [], true);
    }

    /** @return HasOne<Profile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /** @return HasMany<Education, $this> */
    public function educations(): HasMany
    {
        return $this->hasMany(Education::class);
    }

    /** @return HasMany<Certification, $this> */
    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }

    /** @return HasMany<Email, $this> */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    /** @return HasMany<MailRule, $this> */
    public function mailRules(): HasMany
    {
        return $this->hasMany(MailRule::class);
    }

    /** @return HasMany<Company, $this> */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
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
}
