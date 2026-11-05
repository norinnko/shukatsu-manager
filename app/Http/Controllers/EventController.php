<?php

namespace App\Http\Controllers;

use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Http\Requests\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\Google\GoogleCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private GoogleCalendarService $calendar)
    {
        //
    }

    public function index(Request $request): Response
    {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : now()->startOfMonth();

        // The grid shows whole weeks (Sunday start), so include the edges.
        $rangeStart = $month->startOfWeek(CarbonImmutable::SUNDAY);
        $rangeEnd = $month->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $user = $request->user();

        $events = $user->events()
            ->with('company:id,name')
            ->where('starts_at', '<=', $rangeEnd)
            ->where(fn ($q) => $q
                ->where('starts_at', '>=', $rangeStart)
                ->orWhere('ends_at', '>=', $rangeStart))
            ->orderBy('starts_at')
            ->get();

        $agenda = $user->events()
            ->upcoming()
            ->with('company:id,name')
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')
            ->limit(100)
            ->get();

        return Inertia::render('calendar/index', [
            'month' => $month->format('Y-m'),
            'events' => EventResource::collection($events)->resolve(),
            'agenda' => EventResource::collection($agenda)->resolve(),
            'companies' => $user->companies()->active()->orderBy('name')->get(['id', 'name']),
            'googleConnected' => $user->hasGoogleCalendar(),
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = $request->user()->events()->create([
            ...$request->eventData(),
            'source' => EventSource::Manual,
        ]);

        $this->syncAndToast($event, '予定を追加しました。');

        return back();
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        Gate::authorize('own', $event);

        $event->update($request->eventData());

        if (! $event->sync_google) {
            $this->calendar->delete($event);
        }

        $this->syncAndToast($event, '予定を更新しました。');

        return back();
    }

    public function updateStatus(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('own', $event);

        $data = $request->validate(['status' => ['required', Rule::enum(EventStatus::class)]]);
        $event->update($data);

        if ($event->status === EventStatus::Cancelled) {
            $this->calendar->delete($event);
        }

        $this->toast(match ($event->status) {
            EventStatus::Done => '完了にしました。',
            EventStatus::Cancelled => 'キャンセルにしました。',
            EventStatus::Upcoming => '予定に戻しました。',
        });

        return back();
    }

    /**
     * Manually push (or re-push) an event to Google Calendar.
     */
    public function sync(Event $event): RedirectResponse
    {
        Gate::authorize('own', $event);

        $event->update(['sync_google' => true]);

        $this->syncAndToast($event, 'Googleカレンダーに登録しました。');

        return back();
    }

    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('own', $event);

        $this->calendar->delete($event);
        $event->delete();

        $this->toast('予定を削除しました。');

        return back();
    }

    private function syncAndToast(Event $event, string $message): void
    {
        $event->loadMissing('company', 'user');

        if (! $event->sync_google || ! $event->user->hasGoogleCalendar()) {
            $this->toast($message);

            return;
        }

        if ($this->calendar->sync($event)) {
            $this->toast($message.'（Googleカレンダーにも反映）');
        } else {
            $this->toast($message.' ただしGoogleカレンダーへの反映に失敗しました。', 'warning');
        }
    }
}
