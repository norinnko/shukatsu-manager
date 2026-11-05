<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'industry' => $this->industry,
            'phase' => $this->phase->value,
            'stage' => $this->stage->value,
            'priority' => $this->priority?->value,
            'tags' => $this->tags ?? [],
            'mypage_url' => $this->mypage_url,
            'mypage_login_id' => $this->mypage_login_id,
            'recruit_url' => $this->recruit_url,
            'test_type' => $this->test_type,
            'next_action' => $this->next_action,
            'result_expected_on' => $this->result_expected_on?->toDateString(),
            'memo' => $this->memo,
            'archived' => $this->archived_at !== null,
            'updated_at' => $this->updated_at?->toAtomString(),
            'next_event' => $this->whenLoaded('events', fn () => $this->events->first()
                ? (new EventResource($this->events->first()))->resolve($request)
                : null),
            'es_answers_count' => $this->whenCounted('esAnswers'),
        ];
    }
}
