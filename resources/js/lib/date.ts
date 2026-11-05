/**
 * Date helpers. The server sends ISO strings with the JST offset
 * (e.g. 2026-10-09T14:00:00+09:00); the app is used in Japan, so local
 * browser time is JST.
 */
const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

export function toDate(iso: string): Date {
    return new Date(iso);
}

/** "10/9(金)" */
export function formatDay(iso: string): string {
    const d = toDate(iso);

    return `${d.getMonth() + 1}/${d.getDate()}(${WEEKDAYS[d.getDay()]})`;
}

/** "2026/10/9(金)" */
export function formatFullDay(iso: string): string {
    const d = toDate(iso);

    return `${d.getFullYear()}/${d.getMonth() + 1}/${d.getDate()}(${WEEKDAYS[d.getDay()]})`;
}

/** "14:00" */
export function formatTime(iso: string): string {
    const d = toDate(iso);

    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

/** "10/9(金) 14:00〜15:00", "8/17(月)〜8/19(水)", "10/9(金) 終日" */
export function formatEventWhen(event: {
    starts_at: string;
    ends_at: string | null;
    all_day: boolean;
}): string {
    const start = formatDay(event.starts_at);

    if (event.all_day) {
        return event.ends_at ? `${start}〜${formatDay(event.ends_at)}` : `${start} 終日`;
    }

    const startTime = formatTime(event.starts_at);

    if (!event.ends_at) {
        return `${start} ${startTime}`;
    }

    const sameDay = isSameDay(toDate(event.starts_at), toDate(event.ends_at));

    return sameDay
        ? `${start} ${startTime}〜${formatTime(event.ends_at)}`
        : `${start} ${startTime}〜${formatDay(event.ends_at)} ${formatTime(event.ends_at)}`;
}

export function isSameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

export function startOfDay(d: Date): Date {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

/** Whole days from today to the given date (negative = past). */
export function daysUntil(iso: string, now: Date = new Date()): number {
    const ms = startOfDay(toDate(iso)).getTime() - startOfDay(now).getTime();

    return Math.round(ms / 86_400_000);
}

export type Countdown = {
    label: string;
    level: 'overdue' | 'today' | 'soon' | 'week' | 'later';
};

/** "3日超過" / "今日" / "明日" / "あと3日" */
export function countdown(dueIso: string, startIso: string, now: Date = new Date()): Countdown {
    if (toDate(dueIso).getTime() < now.getTime()) {
        const days = -daysUntil(dueIso, now);

        return { label: days <= 0 ? '期限切れ' : `${days}日超過`, level: 'overdue' };
    }

    const days = daysUntil(startIso, now);

    if (days <= 0) {
        return { label: '今日', level: 'today' };
    }

    if (days === 1) {
        return { label: '明日', level: 'soon' };
    }

    return { label: `あと${days}日`, level: days <= 3 ? 'soon' : days <= 7 ? 'week' : 'later' };
}

/** "2026-10-09" in local time. */
export function toDateInput(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** "YYYY-MM" → "2026年4月" */
export function formatYearMonth(ym: string | null | undefined): string {
    if (!ym) {
        return '';
    }

    const [y, m] = ym.split('-');

    return `${y}年${Number(m)}月`;
}
