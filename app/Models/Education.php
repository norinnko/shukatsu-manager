<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $school
 * @property string|null $faculty
 * @property string|null $department
 * @property string|null $major
 * @property string|null $degree
 * @property string $entered_on YYYY-MM
 * @property string|null $graduated_on YYYY-MM
 * @property string $graduation_status graduated|expected
 */
#[Fillable(['school', 'faculty', 'department', 'major', 'degree', 'entered_on', 'graduated_on', 'graduation_status'])]
class Education extends Model
{
    use SerializesLocalDates;

    protected $table = 'educations';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
