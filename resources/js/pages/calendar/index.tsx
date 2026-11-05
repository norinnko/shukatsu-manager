import { Head, router } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { EventFormDialog } from '@/components/events/event-form-dialog';
import { EventListItem } from '@/components/events/event-list-item';
import { ToneDot } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { useEnums } from '@/hooks/use-enums';
import { formatFullDay, isSameDay, startOfDay, toDate, toDateInput } from '@/lib/date';
import { tone } from '@/lib/tone';
import { cn } from '@/lib/utils';
import { index } from '@/routes/calendar';
import type { CompanyRef, EventItem } from '@/types';

type Props = {
    month: string; // YYYY-MM
    events: EventItem[];
    agenda: EventItem[];
    companies: CompanyRef[];
    googleConnected: boolean;
};

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

function monthOffset(month: string, delta: number): string {
    const [y, m] = month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function buildDays(month: string): Date[] {
    const [y, m] = month.split('-').map(Number);
    const first = new Date(y, m - 1, 1);
    const last = new Date(y, m, 0);
    const start = new Date(first);
    start.setDate(first.getDate() - first.getDay());
    const end = new Date(last);
    end.setDate(last.getDate() + (6 - last.getDay()));

    const days: Date[] = [];

    for (const d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        days.push(new Date(d));
    }

    return days;
}

/** Does the event fall on this calendar day (multi-day aware)? */
function occursOn(event: EventItem, day: Date): boolean {
    const start = startOfDay(toDate(event.starts_at));
    const end = startOfDay(toDate(event.ends_at ?? event.starts_at));
    const d = startOfDay(day).getTime();

    return d >= start.getTime() && d <= end.getTime();
}

export default function CalendarIndex({ month, events, agenda, companies, googleConnected }: Props) {
    const { eventType } = useEnums();
    const today = new Date();
    const [selected, setSelected] = useState<Date>(() => {
        const [y, m] = month.split('-').map(Number);

        return today.getFullYear() === y && today.getMonth() === m - 1 ? today : new Date(y, m - 1, 1);
    });
    const [dialog, setDialog] = useState<{ open: boolean; event: EventItem | null; date?: string }>({
        open: false,
        event: null,
    });

    const days = useMemo(() => buildDays(month), [month]);
    const [year, monthNumber] = month.split('-').map(Number);
    const visible = events.filter((e) => e.status !== 'cancelled');
    const selectedEvents = visible.filter((e) => occursOn(e, selected));

    const go = (target: string) => {
        router.get(index().url, { month: target }, { preserveScroll: true, preserveState: false });
    };

    const openNew = (date?: Date) =>
        setDialog({ open: true, event: null, date: toDateInput(date ?? selected) });
    const openEdit = (event: EventItem) => setDialog({ open: true, event });

    return (
        <>
            <Head title="カレンダー" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-1">
                        <Button variant="ghost" size="icon" onClick={() => go(monthOffset(month, -1))} aria-label="前の月">
                            <ChevronLeft />
                        </Button>
                        <h1 className="min-w-36 text-center text-xl font-semibold tabular-nums">
                            {year}年{monthNumber}月
                        </h1>
                        <Button variant="ghost" size="icon" onClick={() => go(monthOffset(month, 1))} aria-label="次の月">
                            <ChevronRight />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            className="ml-2"
                            onClick={() => go(`${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`)}
                        >
                            今日
                        </Button>
                    </div>
                    <Button onClick={() => openNew()}>
                        <Plus /> 予定を追加
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <div className="grid grid-cols-7 border-b bg-muted/40 text-center text-xs font-medium">
                            {WEEKDAYS.map((w, i) => (
                                <div
                                    key={w}
                                    className={cn('py-2', i === 0 && 'text-red-600', i === 6 && 'text-blue-600')}
                                >
                                    {w}
                                </div>
                            ))}
                        </div>
                        <div className="grid grid-cols-7">
                            {days.map((day) => {
                                const inMonth = day.getMonth() === monthNumber - 1;
                                const dayEvents = visible.filter((e) => occursOn(e, day));
                                const isToday = isSameDay(day, today);
                                const isSelected = isSameDay(day, selected);

                                return (
                                    <button
                                        key={day.toISOString()}
                                        type="button"
                                        onClick={() => setSelected(day)}
                                        onDoubleClick={() => openNew(day)}
                                        className={cn(
                                            'flex min-h-16 flex-col items-stretch gap-0.5 border-r border-b p-1 text-left align-top transition-colors last:border-r-0 md:min-h-24 [&:nth-child(7n)]:border-r-0',
                                            !inMonth && 'bg-muted/30 text-muted-foreground',
                                            isSelected && 'bg-sky-50 dark:bg-sky-950/40',
                                            'hover:bg-muted/60',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mx-auto flex size-6 items-center justify-center rounded-full text-xs tabular-nums md:mx-0',
                                                day.getDay() === 0 && 'text-red-600',
                                                day.getDay() === 6 && 'text-blue-600',
                                                isToday && 'bg-foreground font-semibold text-background',
                                            )}
                                        >
                                            {day.getDate()}
                                        </span>

                                        {/* Phones: coloured dots. */}
                                        <span className="flex flex-wrap justify-center gap-0.5 md:hidden">
                                            {dayEvents.slice(0, 4).map((e) => (
                                                <ToneDot key={e.id} color={eventType[e.type]?.color} className={cn('size-1.5', e.status === 'done' && 'opacity-40')} />
                                            ))}
                                        </span>

                                        {/* Larger screens: titled chips. */}
                                        <span className="hidden flex-col gap-0.5 md:flex">
                                            {dayEvents.slice(0, 3).map((e) => (
                                                <span
                                                    key={e.id}
                                                    className={cn(
                                                        'truncate rounded px-1 py-0.5 text-[11px] leading-tight',
                                                        tone(eventType[e.type]?.color).badge,
                                                        e.status === 'done' && 'opacity-50 line-through',
                                                    )}
                                                    title={`${e.company?.name ?? ''} ${e.title}`}
                                                >
                                                    {e.company ? `${e.company.name.replace(/株式会社|（株）|\(株\)/g, '')} ` : ''}
                                                    {e.title}
                                                </span>
                                            ))}
                                            {dayEvents.length > 3 && (
                                                <span className="px-1 text-[11px] text-muted-foreground">+{dayEvents.length - 3}件</span>
                                            )}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="space-y-6">
                        <section className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-semibold">{formatFullDay(selected.toISOString())}</h2>
                                <Button size="sm" variant="ghost" onClick={() => openNew(selected)}>
                                    <Plus /> この日に追加
                                </Button>
                            </div>
                            {selectedEvents.length ? (
                                <ul className="space-y-2">
                                    {selectedEvents.map((e) => (
                                        <EventListItem key={e.id} event={e} onEdit={openEdit} googleConnected={googleConnected} />
                                    ))}
                                </ul>
                            ) : (
                                <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                                    予定はありません
                                </p>
                            )}
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-sm font-semibold">これからの予定</h2>
                            {agenda.length ? (
                                <ul className="space-y-2">
                                    {agenda.slice(0, 20).map((e) => (
                                        <EventListItem key={e.id} event={e} onEdit={openEdit} googleConnected={googleConnected} />
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState icon={CalendarDays} title="これからの予定はありません" />
                            )}
                        </section>
                    </div>
                </div>

                <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                    {Object.values(eventType).map((t) => (
                        <span key={t.value} className="flex items-center gap-1">
                            <ToneDot color={t.color} /> {t.label}
                        </span>
                    ))}
                    <span className="hidden md:inline">・日付をダブルクリックで予定を追加</span>
                </div>
            </div>

            <EventFormDialog
                open={dialog.open}
                onOpenChange={(open) => setDialog((s) => ({ ...s, open }))}
                event={dialog.event}
                defaultDate={dialog.date}
                companies={companies}
                googleConnected={googleConnected}
            />
        </>
    );
}

CalendarIndex.layout = {
    breadcrumbs: [{ title: 'カレンダー', href: index() }],
};
