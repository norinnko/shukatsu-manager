<?php

namespace App\Http\Controllers;

use App\Enums\CompanyPriority;
use App\Enums\CompanyStage;
use App\Enums\EmailStatus;
use App\Enums\MailAiStatus;
use App\Enums\MailCategory;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\EventResource;
use App\Models\Email;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $now = now();
        $today = $now->endOfDay();

        $events = $user->events()
            ->upcoming()
            ->with('company:id,name')
            ->where('starts_at', '<=', $now->addDays(14)->endOfDay())
            ->orderBy('starts_at')
            ->get();

        $overdue = $events->filter(fn (Event $e) => $e->dueAt()->isPast());
        $todayEvents = $events->diff($overdue)->filter(fn (Event $e) => $e->starts_at->lessThanOrEqualTo($today));
        $soon = $events->diff($overdue)->diff($todayEvents);

        $companies = $user->companies()->active();

        $stageCounts = (clone $companies)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $awaiting = (clone $companies)
            ->where('stage', CompanyStage::Applied)
            ->orderByRaw('result_expected_on is null, result_expected_on')
            ->get();

        $favorites = (clone $companies)
            ->where('priority', CompanyPriority::First)
            ->with(['events' => fn ($q) => $q->upcoming()->orderBy('starts_at')->limit(1)])
            ->orderBy('name')
            ->get();

        // Unread mails that need the user: a calendar action to confirm, or a
        // result / request in an ongoing selection.
        $mailTodo = $user->emails()
            ->with('company:id,name')
            ->where('status', EmailStatus::New)
            ->where('ai_status', MailAiStatus::Done)
            ->latest('received_at')
            ->limit(100)
            ->get()
            ->filter(fn (Email $e) => in_array($e->ai_category, [MailCategory::Result, MailCategory::Task], true)
                || collect($e->ai_actions ?? [])->contains(fn (array $a) => ! ($a['applied'] ?? false)))
            ->values();

        return Inertia::render('dashboard', [
            'mailTodo' => [
                'total' => $mailTodo->count(),
                'items' => $mailTodo->take(5)->map(fn (Email $e) => [
                    'id' => $e->id,
                    'subject' => $e->subject,
                    'summary' => $e->ai_summary,
                    'category' => $e->ai_category?->value,
                    'company' => $e->company?->name,
                    'received_at' => $e->received_at->toAtomString(),
                ])->all(),
            ],
            'overdue' => EventResource::collection($overdue->values())->resolve(),
            'today' => EventResource::collection($todayEvents->values())->resolve(),
            'soon' => EventResource::collection($soon->values())->resolve(),
            'awaiting' => CompanyResource::collection($awaiting)->resolve(),
            'favorites' => CompanyResource::collection($favorites)->resolve(),
            'stageCounts' => $stageCounts,
            'companies' => (clone $companies)->orderBy('name')->get(['id', 'name']),
            'googleConnected' => $user->hasGoogleCalendar(),
            'stats' => [
                'companies' => (clone $companies)->count(),
                'inProgress' => (clone $companies)->whereIn('stage', [
                    CompanyStage::Es, CompanyStage::Applied, CompanyStage::Webtest,
                    CompanyStage::Interview, CompanyStage::Final,
                ])->count(),
                'offers' => $stageCounts[CompanyStage::Offer->value] ?? 0,
                'upcomingEvents' => $events->count(),
            ],
        ]);
    }
}
