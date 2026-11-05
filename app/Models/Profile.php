<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $full_name
 * @property string|null $full_name_kana
 * @property string|null $full_name_roman
 * @property Carbon|null $birth_date
 * @property string|null $contact_email
 * @property string|null $phone
 * @property string|null $postal_code
 * @property string|null $address
 * @property string|null $github_url
 * @property string|null $portfolio_url
 * @property string|null $note
 */
#[Fillable([
    'full_name', 'full_name_kana', 'full_name_roman', 'birth_date', 'contact_email',
    'phone', 'postal_code', 'address', 'github_url', 'portfolio_url', 'note',
])]
class Profile extends Model
{
    use SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
