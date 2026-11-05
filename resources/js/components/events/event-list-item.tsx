import { Link, router } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarPlus,
    Check,
    ExternalLink,
    MoreHorizontal,
    Pencil,
    RotateCcw,
    Trash2,
    Video,
    XCircle,
} from 'lucide-react';
import { CountdownBadge } from '@/components/countdown-badge';
import { ToneDot } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useEnums } from '@/hooks/use-enums';
import { formatEventWhen } from '@/lib/date';
import { cn } from '@/lib/utils';
import { show as companyRoute } from '@/routes/companies';
import { destroy, status, sync } from '@/routes/events';
import type { EventItem, EventStatus } from '@/types';

export function EventListItem({
    event,
    onEdit,
    showCompany = true,
    googleConnected = false,
}: {
    event: EventItem;
    onEdit?: (event: EventItem) => void;
    showCompany?: boolean;
    googleConnected?: boolean;
}) {
    const { eventType } = useEnums();
    const type = eventType[event.type];
    const finished = event.status !== 'upcoming';

    const setStatus = (value: EventStatus) =>
        router.patch(status(event.id).url, { status: value }, { preserveScroll: true });

    const remove = () => {
        if (confirm(`「${event.title}」を削除しますか？`)) {
            router.delete(destroy(event.id).url, { preserveScroll: true });
        }
    };

    return (
        <li
            className={cn(
                'flex items-start gap-3 rounded-lg border bg-card p-3',
                finished && 'opacity-60',
            )}
        >
            <ToneDot color={type?.color} className="mt-1.5" />
            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    {event.status === 'upcoming' && (
                        <CountdownBadge dueAt={event.due_at} startsAt={event.starts_at} />
                    )}
                    {event.status === 'done' && (
                        <span className="text-xs font-medium text-emerald-600">完了</span>
                    )}
                    {event.status === 'cancelled' && (
                        <span className="text-xs font-medium text-muted-foreground">キャンセル</span>
                    )}
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {formatEventWhen(event)}
                    </span>
                    <span className="text-xs text-muted-foreground">{type?.label}</span>
                </div>
                <p className={cn('font-medium leading-snug', event.status === 'cancelled' && 'line-through')}>
                    {showCompany && event.company && (
                        <Link
                            href={companyRoute(event.company.id)}
                            className="mr-1.5 text-muted-foreground hover:text-foreground hover:underline"
                        >
                            {event.company.name}
                        </Link>
                    )}
                    {event.title}
                </p>
                {(event.online_url || event.memo) && (
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        {event.online_url && (
                            <a
                                href={event.online_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 text-sky-700 hover:underline dark:text-sky-400"
                            >
                                <Video className="size-3.5" />
                                参加URLを開く
                            </a>
                        )}
                        {event.memo && <span className="line-clamp-2 whitespace-pre-line">{event.memo}</span>}
                    </div>
                )}
                {event.google_sync_error && (
                    <p className="text-xs text-red-600">Google反映エラー：{event.google_sync_error}</p>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-1">
                {event.google_synced && (
                    <CalendarCheck className="size-4 text-emerald-600" aria-label="Googleカレンダー登録済" />
                )}
                {event.status === 'upcoming' && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => setStatus('done')}
                        aria-label="完了にする"
                    >
                        <Check className="size-4" />
                    </Button>
                )}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="size-8" aria-label="操作">
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {onEdit && (
                            <DropdownMenuItem onSelect={() => onEdit(event)}>
                                <Pencil /> 編集
                            </DropdownMenuItem>
                        )}
                        {googleConnected && event.status === 'upcoming' && (
                            <DropdownMenuItem
                                onSelect={() => router.post(sync(event.id).url, {}, { preserveScroll: true })}
                            >
                                <CalendarPlus /> {event.google_synced ? 'Googleに再反映' : 'Googleカレンダーに登録'}
                            </DropdownMenuItem>
                        )}
                        {event.online_url && (
                            <DropdownMenuItem asChild>
                                <a href={event.online_url} target="_blank" rel="noopener noreferrer">
                                    <ExternalLink /> 参加URLを開く
                                </a>
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuSeparator />
                        {event.status !== 'upcoming' ? (
                            <DropdownMenuItem onSelect={() => setStatus('upcoming')}>
                                <RotateCcw /> 予定に戻す
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem onSelect={() => setStatus('cancelled')}>
                                <XCircle /> キャンセルにする
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem variant="destructive" onSelect={remove}>
                            <Trash2 /> 削除
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </li>
    );
}
