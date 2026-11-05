<?php

namespace App\Services\Gmail;

use App\Models\Event;
use App\Models\MailRule;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What OpenAI needs to know about the user to judge a mail: their
 * "次から表示しない" rules, companies and upcoming events.
 */
final readonly class MailContext
{
    /** Upcoming events shown to OpenAI, so it can tell which one a mail moves or cancels. */
    private const MAX_EVENTS = 60;

    /**
     * @param  Collection<int, MailRule>  $rules
     * @param  Collection<int, Event>  $events
     */
    public function __construct(
        public Collection $rules,
        public CompanyMatcher $companies,
        public Collection $events,
    ) {
        //
    }

    public static function forUser(User $user): self
    {
        return new self(
            $user->mailRules()->oldest('id')->get(),
            CompanyMatcher::forUser($user),
            $user->events()
                ->upcoming()
                ->with('company:id,name')
                ->where('starts_at', '>=', now()->subDay())
                ->orderBy('starts_at')
                ->limit(self::MAX_EVENTS)
                ->get(),
        );
    }
}
