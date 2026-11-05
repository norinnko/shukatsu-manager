import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { tone } from '@/lib/tone';
import { cn } from '@/lib/utils';

export function ToneBadge({
    color,
    className,
    children,
}: {
    color: string | null | undefined;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Badge variant="outline" className={cn(tone(color).badge, className)}>
            {children}
        </Badge>
    );
}

export function ToneDot({
    color,
    className,
}: {
    color: string | null | undefined;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'inline-block size-2 shrink-0 rounded-full',
                tone(color).dot,
                className,
            )}
        />
    );
}
