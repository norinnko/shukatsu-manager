import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CalendarClock,
    CalendarDays,
    Hourglass,
    Inbox,
    Plus,
    Star,
    Trophy,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { CompanyFormDialog } from '@/components/companies/company-form-dialog';
import { EmptyState } from '@/components/empty-state';
import { EventFormDialog } from '@/components/events/event-form-dialog';
import { EventListItem } from '@/components/events/event-list-item';
import { ToneBadge, ToneDot } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useEnums } from '@/hooks/use-enums';
import { daysUntil, formatDay, formatEventWhen, formatFullDay } from '@/lib/date';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as companiesIndex, show as companyRoute } from '@/routes/companies';
import { index as inbox } from '@/routes/inbox';
import type { Company, CompanyRef, CompanyStage, EventItem, MailCategory } from '@/types';

type Props = {
    overdue: EventItem[];
    today: EventItem[];
    soon: EventItem[];
    awaiting: Company[];
    favorites: Company[];
    stageCounts: Partial<Record<CompanyStage, number>>;
    stats: { companies: number; inProgress: number; offers: number; upcomingEvents: number };
    companies: CompanyRef[];
    googleConnected: boolean;
    mailTodo: {
        total: number;
        items: { id: number; subject: string; summary: string | null; category: MailCategory | null; company: string | null; received_at: string }[];
    };
};

function Stat({ icon: Icon, label, value, className }: { icon: LucideIcon; label: string; value: number; className?: string }) {
    return (
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
            <div className={cn('flex size-10 items-center justify-center rounded-lg', className)}>
                <Icon className="size-5" />
            </div>
            <div>
                <p className="text-2xl font-semibold tabular-nums leading-none">{value}</p>
                <p className="mt-1 text-xs text-muted-foreground">{label}</p>
            </div>
        </div>
    );
}

function Section({
    title,
    icon: Icon,
    tone,
    count,
    children,
}: {
    title: string;
    icon: LucideIcon;
    tone?: string;
    count?: number;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3">
            <h2 className={cn('flex items-center gap-2 text-sm font-semibold', tone)}>
                <Icon className="size-4" />
                {title}
                {count !== undefined && (
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                        {count}
                    </span>
                )}
            </h2>
            {children}
        </section>
    );
}

export default function Dashboard({
    overdue,
    today,
    soon,
    awaiting,
    favorites,
    stageCounts,
    stats,
    companies,
    googleConnected,
    mailTodo,
}: Props) {
    const { stages, priority, mailCategories } = useEnums();
    const [eventDialog, setEventDialog] = useState<{ open: boolean; event: EventItem | null }>({
        open: false,
        event: null,
    });
    const [companyDialog, setCompanyDialog] = useState(false);

    const openEvent = (event: EventItem | null = null) => setEventDialog({ open: true, event });
    const renderEvents = (events: EventItem[]) => (
        <ul className="space-y-2">
            {events.map((e) => (
                <EventListItem key={e.id} event={e} onEdit={openEvent} googleConnected={googleConnected} />
            ))}
        </ul>
    );

    const activeStages = stages.filter((s) => !['rejected', 'declined'].includes(s.value));
    const pipelineTotal = activeStages.reduce((sum, s) => sum + (stageCounts[s.value] ?? 0), 0);

    return (
        <>
            <Head title="ホーム" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">{formatFullDay(new Date().toISOString())}</p>
                        <h1 className="text-2xl font-semibold tracking-tight">今日やること</h1>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setCompanyDialog(true)}>
                            <Building2 /> 企業を追加
                        </Button>
                        <Button onClick={() => openEvent()}>
                            <Plus /> 予定を追加
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Stat icon={Hourglass} label="選考中の企業" value={stats.inProgress} className="bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300" />
                    <Stat icon={CalendarDays} label="2週間以内の予定" value={stats.upcomingEvents} className="bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300" />
                    <Stat icon={Trophy} label="内定" value={stats.offers} className="bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300" />
                    <Stat icon={Building2} label="登録企業" value={stats.companies} className="bg-muted text-muted-foreground" />
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
                    <div className="space-y-6">
                        {overdue.length > 0 && (
                            <Section title="期限切れ（完了にするか日程を直す）" icon={AlertTriangle} tone="text-red-600" count={overdue.length}>
                                {renderEvents(overdue)}
                            </Section>
                        )}

                        {mailTodo.total > 0 && (
                            <Section title="受信箱で確認が必要" icon={Inbox} tone="text-amber-700 dark:text-amber-400" count={mailTodo.total}>
                                <ul className="divide-y rounded-lg border">
                                    {mailTodo.items.map((mail) => {
                                        const category = mailCategories.find((c) => c.value === mail.category);

                                        return (
                                            <li key={mail.id}>
                                                <Link href={inbox()} className="block px-4 py-3 hover:bg-muted/50">
                                                    <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                                        {category && <ToneBadge color={category.color}>{category.label}</ToneBadge>}
                                                        {mail.company && <span>{mail.company}</span>}
                                                        <span>{formatDay(mail.received_at)}</span>
                                                    </div>
                                                    <p className="mt-1 line-clamp-1 text-sm font-medium">{mail.subject}</p>
                                                    {mail.summary && <p className="line-clamp-1 text-xs text-muted-foreground">{mail.summary}</p>}
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                                {mailTodo.total > mailTodo.items.length && (
                                    <Link href={inbox()} className="text-sm text-muted-foreground hover:underline">
                                        ほか{mailTodo.total - mailTodo.items.length}件を受信箱で見る
                                    </Link>
                                )}
                            </Section>
                        )}

                        <Section title="今日" icon={CalendarClock} count={today.length}>
                            {today.length ? (
                                renderEvents(today)
                            ) : (
                                <EmptyState icon={CalendarClock} title="今日の予定はありません" />
                            )}
                        </Section>

                        <Section title="これから2週間" icon={CalendarDays} count={soon.length}>
                            {soon.length ? (
                                renderEvents(soon)
                            ) : (
                                <EmptyState icon={CalendarDays} title="2週間以内の予定はありません">
                                    締切や面接が決まったら「予定を追加」から登録しましょう。
                                </EmptyState>
                            )}
                        </Section>
                    </div>

                    <div className="space-y-6">
                        <Card className="gap-3 py-4">
                            <CardHeader className="px-4">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Hourglass className="size-4" /> 結果待ち
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-4">
                                {awaiting.length ? (
                                    <ul className="space-y-2">
                                        {awaiting.map((c) => {
                                            const late = c.result_expected_on !== null && daysUntil(c.result_expected_on) < 0;

                                            return (
                                                <li key={c.id} className="flex items-center justify-between gap-2 text-sm">
                                                    <Link href={companyRoute(c.id)} className="truncate hover:underline">
                                                        {c.name}
                                                    </Link>
                                                    {c.result_expected_on ? (
                                                        <span className={cn('shrink-0 text-xs', late ? 'font-semibold text-red-600' : 'text-muted-foreground')}>
                                                            {late ? `${formatDay(c.result_expected_on)} 予定・連絡来た？` : `${formatDay(c.result_expected_on)}ごろ`}
                                                        </span>
                                                    ) : (
                                                        <span className="shrink-0 text-xs text-muted-foreground">日付未定</span>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                ) : (
                                    <p className="text-sm text-muted-foreground">結果待ちの企業はありません。</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="gap-3 py-4">
                            <CardHeader className="px-4">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Star className="size-4 text-rose-500" /> 第一志望
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-4">
                                {favorites.length ? (
                                    <ul className="space-y-3">
                                        {favorites.map((c) => (
                                            <li key={c.id} className="space-y-0.5">
                                                <Link href={companyRoute(c.id)} className="text-sm font-medium hover:underline">
                                                    {c.name}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {c.next_event
                                                        ? `次：${formatEventWhen(c.next_event)} ${c.next_event.title}`
                                                        : (c.next_action ?? '次の予定なし')}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        企業の志望度を「{priority.first?.label}」にするとここに出ます。
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="gap-3 py-4">
                            <CardHeader className="px-4">
                                <CardTitle className="flex items-center justify-between text-sm">
                                    <span>選考の状況</span>
                                    <Link href={companiesIndex()} className="text-xs font-normal text-muted-foreground hover:underline">
                                        ボードを開く
                                    </Link>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 px-4">
                                {activeStages.map((s) => {
                                    const n = stageCounts[s.value] ?? 0;

                                    return (
                                        <div key={s.value} className="flex items-center gap-2 text-sm">
                                            <ToneDot color={s.color} />
                                            <span className="w-32 shrink-0 truncate">{s.label}</span>
                                            <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-foreground/70"
                                                    style={{ width: pipelineTotal ? `${(n / pipelineTotal) * 100}%` : 0 }}
                                                />
                                            </div>
                                            <span className="w-6 text-right text-xs tabular-nums text-muted-foreground">{n}</span>
                                        </div>
                                    );
                                })}
                                {(stageCounts.rejected || stageCounts.declined) && (
                                    <p className="pt-1 text-xs text-muted-foreground">
                                        終了：
                                        <ToneBadge color="zinc" className="ml-1">
                                            不合格 {stageCounts.rejected ?? 0}
                                        </ToneBadge>
                                        <ToneBadge color="zinc" className="ml-1">
                                            辞退 {stageCounts.declined ?? 0}
                                        </ToneBadge>
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <EventFormDialog
                open={eventDialog.open}
                onOpenChange={(open) => setEventDialog((s) => ({ ...s, open }))}
                event={eventDialog.event}
                companies={companies}
                googleConnected={googleConnected}
            />
            <CompanyFormDialog open={companyDialog} onOpenChange={setCompanyDialog} />
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'ホーム', href: dashboard() }],
};
