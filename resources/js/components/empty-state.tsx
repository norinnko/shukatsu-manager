import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function EmptyState({
    icon: Icon,
    title,
    children,
}: {
    icon: LucideIcon;
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-6 py-10 text-center">
            <Icon className="size-8 text-muted-foreground/60" />
            <p className="text-sm font-medium">{title}</p>
            {children && (
                <div className="text-sm text-muted-foreground">{children}</div>
            )}
        </div>
    );
}
