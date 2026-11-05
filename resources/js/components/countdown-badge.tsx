import { countdown } from '@/lib/date';
import type { Countdown } from '@/lib/date';
import { cn } from '@/lib/utils';

const styles: Record<Countdown['level'], string> = {
    overdue: 'bg-red-600 text-white',
    today: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    soon: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    week: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    later: 'bg-muted text-muted-foreground',
};

export function CountdownBadge({
    dueAt,
    startsAt,
    className,
}: {
    dueAt: string;
    startsAt: string;
    className?: string;
}) {
    const c = countdown(dueAt, startsAt);

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-xs font-semibold tabular-nums',
                styles[c.level],
                className,
            )}
        >
            {c.label}
        </span>
    );
}
