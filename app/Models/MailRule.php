<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of mail the user does not want to see, described by OpenAI from a
 * mail the user marked "次から表示しない".
 *
 * @property int $id
 * @property int $user_id
 * @property string $description
 * @property string|null $example_subject
 * @property int|null $source_email_id
 */
#[Fillable(['description', 'example_subject', 'source_email_id'])]
class MailRule extends Model
{
    use SerializesLocalDates;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Email, $this> */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }
}
