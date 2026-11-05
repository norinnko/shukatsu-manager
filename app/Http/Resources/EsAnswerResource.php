<?php

namespace App\Http\Resources;

use App\Models\EsAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EsAnswer
 */
class EsAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company' => $this->whenLoaded('company', fn () => $this->company
                ? ['id' => $this->company->id, 'name' => $this->company->name]
                : null),
            'company_id' => $this->company_id,
            'category' => $this->category->value,
            'question' => $this->question,
            'answer' => $this->answer,
            'char_limit' => $this->char_limit,
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
