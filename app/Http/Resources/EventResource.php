<?php

namespace App\Http\Resources;

use App\Models\Event;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
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
            'type' => $this->type->value,
            'title' => $this->title,
            'starts_at' => $this->starts_at->format(DateTimeInterface::ATOM),
            'ends_at' => $this->ends_at?->format(DateTimeInterface::ATOM),
            'due_at' => $this->dueAt()->format(DateTimeInterface::ATOM),
            'all_day' => $this->all_day,
            'location' => $this->location,
            'online_url' => $this->online_url,
            'memo' => $this->memo,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'sync_google' => $this->sync_google,
            'google_synced' => $this->google_event_id !== null,
            'google_sync_error' => $this->google_sync_error,
        ];
    }
}
