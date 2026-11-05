<?php

namespace App\Models;

use App\Enums\EsCategory;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $company_id
 * @property EsCategory $category
 * @property string $question
 * @property string $answer
 * @property int|null $char_limit
 * @property-read Company|null $company
 */
#[Fillable(['company_id', 'category', 'question', 'answer', 'char_limit'])]
class EsAnswer extends Model
{
    use SerializesLocalDates;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'category' => EsCategory::class,
            'char_limit' => 'integer',
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
}
