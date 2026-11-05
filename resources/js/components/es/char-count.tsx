import { cn } from '@/lib/utils';

/** Counts characters the way ES forms usually do (line breaks excluded). */
export function countChars(text: string): number {
    return [...text.replace(/\r?\n/g, '')].length;
}

export function CharCount({
    text,
    limit,
    className,
}: {
    text: string;
    limit: number | null | undefined;
    className?: string;
}) {
    const count = countChars(text);
    const over = limit != null && count > limit;
    const ratio = limit ? count / limit : null;

    return (
        <span
            className={cn(
                'text-xs tabular-nums',
                over
                    ? 'font-semibold text-red-600'
                    : ratio !== null && ratio >= 0.8
                      ? 'text-emerald-600'
                      : 'text-muted-foreground',
                className,
            )}
        >
            {count}
            {limit ? ` / ${limit}字` : '字'}
            {over && `（${count - limit!}字オーバー）`}
        </span>
    );
}
