import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { mainNavItems } from '@/lib/navigation';
import { cn, toUrl } from '@/lib/utils';

/** Bottom tab bar for phones (the sidebar is hidden behind a menu there). */
export function MobileTabBar() {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <nav
            aria-label="メニュー"
            className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        >
            <ul className="grid grid-cols-6">
                {mainNavItems.map((item) => {
                    const active = isCurrentOrParentUrl(item.href);

                    return (
                        <li key={toUrl(item.href)}>
                            <Link
                                href={item.href}
                                prefetch
                                className={cn(
                                    'flex flex-col items-center gap-0.5 py-2 text-[10px]',
                                    active ? 'text-foreground' : 'text-muted-foreground',
                                )}
                                aria-current={active ? 'page' : undefined}
                            >
                                {item.icon && <item.icon className={cn('size-5', active && 'stroke-[2.5]')} />}
                                {item.title}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
