<?php

namespace App\Http\Resources;

use App\Enums\MailCategory;
use App\Models\Email;
use App\Services\Gmail\MailActionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * @mixin Email
 */
class EmailResource extends JsonResource
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
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'subject' => $this->subject,
            'snippet' => $this->snippet,
            'body' => $this->body,
            'received_at' => $this->received_at->toAtomString(),
            'status' => $this->status->value,
            'ai' => [
                'status' => $this->ai_status->value,
                'category' => $this->ai_category?->value,
                'decision' => $this->ai_decision?->value,
                'summary' => $this->ai_summary,
                'reason' => $this->ai_reason,
                'actions' => $this->actions($request),
                // Only for invitations; ads and the like would be noise.
                'signup_url' => $this->ai_category === MailCategory::Info ? $this->ai_signup_url : null,
                'error' => $this->ai_error,
                'rule' => $this->whenLoaded('mailRule', fn () => $this->mailRule
                    ? ['id' => $this->mailRule->id, 'description' => $this->mailRule->description]
                    : null),
            ],
            'gmail_url' => $this->gmailUrl((string) $request->user()?->email),
        ];
    }

    /**
     * Calendar actions, with the event an update / cancel refers to as it
     * is now, so the page can show "before → after". A suggested event that
     * is already on the calendar (e.g. from an earlier reminder of the same
     * deadline) is marked so it is not added twice.
     *
     * @return list<array<string, mixed>>
     */
    private function actions(Request $request): array
    {
        $actions = $this->ai_actions ?? [];

        if ($actions === [] || ($user = $request->user()) === null) {
            return [];
        }

        $ids = array_filter(array_column($actions, 'event_id'));
        $dates = array_filter(array_column($actions, 'date'));
        $events = $user->events()
            ->where(fn ($q) => $q->whereIn('id', $ids ?: [0])->orWhereIn(DB::raw('date(starts_at)'), $dates ?: ['']))
            ->get();

        return array_map(function (array $action) use ($events) {
            $current = $action['event_id'] ? $events->firstWhere('id', $action['event_id']) : null;

            return [
                ...$action,
                'registered' => MailActionService::isRegistered($action, $events),
                'current' => $current ? [
                    'title' => $current->displayTitle(),
                    'starts_at' => $current->starts_at->toAtomString(),
                    'ends_at' => $current->ends_at?->toAtomString(),
                    'all_day' => $current->all_day,
                    'status' => $current->status->value,
                ] : null,
            ];
        }, $actions);
    }
}
