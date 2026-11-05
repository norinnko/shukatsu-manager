import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    BellOff,
    CalendarClock,
    CalendarPlus,
    CalendarX2,
    Check,
    ChevronDown,
    ExternalLink,
    Inbox,
    RefreshCw,
    RotateCcw,
    Sparkles,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { EventFormDialog } from '@/components/events/event-form-dialog';
import type { EventDraft } from '@/components/events/event-form-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useEnums } from '@/hooks/use-enums';
import { formatDay, formatTime } from '@/lib/date';
import { tone } from '@/lib/tone';
import { cn } from '@/lib/utils';
import { sync } from '@/routes/gmail';
import { assess, hideSimilar, index, status as statusRoute } from '@/routes/inbox';
import { apply as applyAction, event as storeMailEvent } from '@/routes/inbox/actions';
import { edit as integrations } from '@/routes/integrations';
import type { CompanyRef, EmailStatus, InboxEmail, MailAction } from '@/types';

type Props = {
    emails: InboxEmail[];
    counts: Partial<Record<EmailStatus, number>>;
    status: EmailStatus;
    gmail: { connected: boolean; syncedAt: string | null; error: string | null };
    ai: { configured: boolean; pending: number; failed: number };
    companies: CompanyRef[];
    googleConnected: boolean;
};

type DraftTarget = { email: InboxEmail; index: number; draft: EventDraft } | null;

export default function InboxIndex({ emails, counts, status, gmail, ai, companies, googleConnected }: Props) {
    const { emailStatuses } = useEnums();
    const [busy, setBusy] = useState<'sync' | 'assess' | null>(null);
    const [target, setTarget] = useState<DraftTarget>(null);

    const run = (kind: 'sync' | 'assess') =>
        router.post(kind === 'sync' ? sync().url : assess().url, {}, {
            preserveScroll: true,
            onStart: () => setBusy(kind),
            onFinish: () => setBusy(null),
        });

    return (
        <>
            <Head title="受信箱" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">受信箱</h1>
                        <p className="text-sm text-muted-foreground">
                            Gmailのメールを10分ごとに取り込み、AIが内容を読んで仕分けます。
                            {gmail.syncedAt && `（最終取り込み：${formatDay(gmail.syncedAt)} ${formatTime(gmail.syncedAt)}）`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {ai.configured && ai.pending + ai.failed > 0 && (
                            <Button variant="outline" onClick={() => run('assess')} disabled={busy !== null}>
                                {busy === 'assess' ? <Spinner /> : <Sparkles />} AI判定を今すぐ実行
                            </Button>
                        )}
                        {gmail.connected && (
                            <Button variant="outline" onClick={() => run('sync')} disabled={busy !== null}>
                                <RefreshCw className={cn(busy === 'sync' && 'animate-spin')} /> 今すぐ取り込む
                            </Button>
                        )}
                    </div>
                </div>

                {!gmail.connected && (
                    <Alert>
                        <AlertCircle />
                        <AlertDescription>
                            <p>
                                Gmailの読み取りが許可されていません。
                                <Link href={integrations()} className="underline">
                                    設定 → 連携
                                </Link>
                                から再連携してください。
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                {gmail.error && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertDescription>{gmail.error}</AlertDescription>
                    </Alert>
                )}

                {!ai.configured ? (
                    <Alert>
                        <Sparkles />
                        <AlertDescription>
                            <p>OpenAIのAPIキーが未設定のため、AIの判定は止まっています（.env の SHUKATSU_OPENAI_API_KEY）。</p>
                        </AlertDescription>
                    </Alert>
                ) : (
                    ai.pending + ai.failed > 0 && (
                        <p className="text-sm text-muted-foreground">
                            AI判定待ち {ai.pending}件{ai.failed > 0 && `・失敗 ${ai.failed}件`}（1分ごとに自動で進みます）
                        </p>
                    )
                )}

                <div className="flex flex-wrap gap-1">
                    {emailStatuses.map((s) => (
                        <Button key={s.value} asChild size="sm" variant={status === s.value ? 'default' : 'outline'}>
                            <Link href={index({ query: { status: s.value } })} preserveScroll>
                                {s.label}（{counts[s.value] ?? 0}）
                            </Link>
                        </Button>
                    ))}
                </div>

                {emails.length ? (
                    <div className="space-y-3">
                        {emails.map((email) => (
                            <EmailCard
                                key={email.id}
                                email={email}
                                aiConfigured={ai.configured}
                                onAddEvent={(index, action) =>
                                    setTarget({
                                        email,
                                        index,
                                        draft: { ...action, date: action.date ?? '', company_id: email.company?.id ?? null, memo: email.subject },
                                    })
                                }
                            />
                        ))}
                    </div>
                ) : (
                    <EmptyState icon={Inbox} title={status === 'new' ? '未確認のメールはありません' : 'メールはありません'} />
                )}
            </div>

            <EventFormDialog
                open={target !== null}
                onOpenChange={(open) => !open && setTarget(null)}
                companies={companies}
                googleConnected={googleConnected}
                draft={target?.draft ?? null}
                action={target ? storeMailEvent({ email: target.email.id, index: target.index }) : undefined}
            />
        </>
    );
}

function EmailCard({
    email,
    aiConfigured,
    onAddEvent,
}: {
    email: InboxEmail;
    aiConfigured: boolean;
    onAddEvent: (index: number, action: MailAction) => void;
}) {
    const { mailCategories } = useEnums();
    const [open, setOpen] = useState(false);
    const [learning, setLearning] = useState(false);
    const category = mailCategories.find((c) => c.value === email.ai.category);

    const setStatus = (value: EmailStatus) =>
        router.patch(statusRoute(email.id).url, { status: value }, { preserveScroll: true });

    const hide = () => {
        if (confirm('このメールから「次から表示しない」ルールをAIが作り、似た内容のメールを次から自動で除外します。\n（面接の日程・選考結果・締切の連絡は除外されません）')) {
            router.post(hideSimilar(email.id).url, {}, {
                preserveScroll: true,
                onStart: () => setLearning(true),
                onFinish: () => setLearning(false),
            });
        }
    };

    return (
        <article className={cn('rounded-lg border p-4', email.ai.decision === 'unsure' && 'border-amber-300 dark:border-amber-700')}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                    <p className="text-xs text-muted-foreground">
                        {formatDay(email.received_at)} {formatTime(email.received_at)}・{email.from_name ?? email.from_address}
                    </p>
                    <h2 className="mt-0.5 font-medium break-words">{email.subject}</h2>
                    <div className="mt-1.5 flex flex-wrap gap-1">
                        {email.ai.decision === 'unsure' && (
                            <Badge className="border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                                <TriangleAlert /> 念のため表示
                            </Badge>
                        )}
                        {category && (
                            <Badge variant="outline" className={tone(category.color).badge}>
                                {category.label}
                            </Badge>
                        )}
                        {email.company && <Badge>{email.company.name}</Badge>}
                        {aiConfigured && email.ai.status === 'pending' && <Badge variant="outline">AI判定待ち</Badge>}
                        {email.ai.status === 'failed' && (
                            <Badge variant="destructive" title={email.ai.error ?? undefined}>
                                AI判定に失敗
                            </Badge>
                        )}
                    </div>
                </div>
                <div className="flex w-full flex-wrap gap-1 sm:w-auto sm:shrink-0">
                    {email.status === 'new' && (
                        <>
                            <Button size="sm" variant="outline" onClick={() => setStatus('done')}>
                                <Check /> 確認済み
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setStatus('ignored')} title="このメールだけ無視します">
                                <X /> 無視
                            </Button>
                            {aiConfigured && (
                                <Button size="sm" variant="ghost" onClick={hide} disabled={learning} title="似たメールを次から自動で除外します">
                                    {learning ? <Spinner /> : <BellOff />} 次から表示しない
                                </Button>
                            )}
                        </>
                    )}
                    {email.status === 'excluded' && (
                        <Button size="sm" variant="outline" onClick={() => setStatus('new')}>
                            <RotateCcw /> 受信箱に戻す
                        </Button>
                    )}
                    {(email.status === 'done' || email.status === 'ignored') && (
                        <Button size="sm" variant="ghost" onClick={() => setStatus('new')}>
                            <RotateCcw /> 未確認に戻す
                        </Button>
                    )}
                </div>
            </div>

            {email.ai.summary ? (
                <p className="mt-2 text-sm">{email.ai.summary}</p>
            ) : (
                email.snippet && !open && <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">{email.snippet}</p>
            )}

            {email.ai.reason && (
                <div className="mt-2 rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                    <p>{email.ai.reason}</p>
                    {email.ai.rule && <p className="mt-1">ルール：{email.ai.rule.description}</p>}
                </div>
            )}

            {email.ai.actions.length > 0 && (
                <ul className="mt-3 space-y-1.5">
                    {email.ai.actions.map((action, i) => (
                        <ActionRow key={i} email={email} index={i} action={action} onAdd={() => onAddEvent(i, action)} />
                    ))}
                </ul>
            )}

            {email.ai.signup_url && (
                <div className="mt-3">
                    <Button asChild size="sm" variant="outline">
                        <a
                            href={email.ai.signup_url}
                            target="_blank"
                            rel="noreferrer"
                            title="申し込んだ後に届く参加確定のメールから、予定に追加できます"
                        >
                            <ExternalLink /> 申し込みページを開く
                        </a>
                    </Button>
                </div>
            )}

            {open && (
                <pre className="mt-3 max-h-[60svh] overflow-y-auto rounded-md bg-muted p-3 font-sans text-sm whitespace-pre-wrap break-words">
                    {email.body || email.snippet}
                </pre>
            )}

            <div className="mt-2 flex flex-wrap gap-3 text-sm">
                <button
                    type="button"
                    className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground"
                    onClick={() => setOpen(!open)}
                >
                    <ChevronDown className={cn('size-4 transition-transform', open && 'rotate-180')} />
                    {open ? '本文を閉じる' : '本文を表示'}
                </button>
                <a
                    href={email.gmail_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground"
                >
                    <ExternalLink className="size-4" /> Gmailで開く
                </a>
            </div>
        </article>
    );
}

/** "10/9(金) 14:00〜15:00" */
function when(date: string, startTime: string | null, endTime: string | null): string {
    return `${formatDay(`${date}T00:00:00+09:00`)}${startTime ? ` ${startTime}` : '（終日）'}${endTime ? `〜${endTime}` : ''}`;
}

function currentWhen(current: NonNullable<MailAction['current']>): string {
    const start = `${formatDay(current.starts_at)}${current.all_day ? '（終日）' : ` ${formatTime(current.starts_at)}`}`;

    return current.ends_at && !current.all_day ? `${start}〜${formatTime(current.ends_at)}` : start;
}

function ActionRow({ email, index, action, onAdd }: { email: InboxEmail; index: number; action: MailAction; onAdd: () => void }) {
    const [busy, setBusy] = useState(false);

    const apply = () => {
        const message =
            action.kind === 'cancel'
                ? `「${action.current?.title}」をキャンセルにします。Googleカレンダーからも削除されます。よろしいですか？`
                : `「${action.current?.title}」の日時を変更します。よろしいですか？`;

        if (confirm(message)) {
            router.post(applyAction({ email: email.id, index }).url, {}, {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            });
        }
    };

    const label = {
        create: { icon: CalendarPlus, text: '参加確定' },
        update: { icon: CalendarClock, text: '日程変更' },
        cancel: { icon: CalendarX2, text: 'キャンセル' },
    }[action.kind];

    return (
        <li className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-dashed px-3 py-2 text-sm">
            <span className="min-w-0">
                <span className="mr-2 inline-flex items-center gap-1 text-xs text-muted-foreground">
                    <label.icon className="size-3.5" /> {label.text}
                </span>
                {action.kind === 'create' && (
                    <>
                        <span className="font-medium">{action.title}</span>
                        <span className="ml-2 text-muted-foreground">
                            {action.date && when(action.date, action.start_time, action.end_time)}
                            {action.location && `・${action.location}`}
                            {action.online_url && '・オンライン'}
                        </span>
                    </>
                )}
                {action.kind !== 'create' && action.current && (
                    <>
                        <span className="font-medium">{action.current.title}</span>
                        <span className="ml-2 inline-flex flex-wrap items-center gap-1 text-muted-foreground">
                            {currentWhen(action.current)}
                            {action.kind === 'update' && action.date && (
                                <>
                                    <ArrowRight className="size-3.5" />
                                    <span className="font-medium text-foreground">{when(action.date, action.start_time, action.end_time)}</span>
                                </>
                            )}
                        </span>
                    </>
                )}
            </span>
            {action.applied ? (
                <Badge variant="outline">
                    <Check /> 反映済み
                </Badge>
            ) : action.registered ? (
                <Badge variant="outline">
                    <Check /> 登録済み
                </Badge>
            ) : action.kind === 'create' ? (
                <Button size="sm" variant="outline" onClick={onAdd}>
                    <CalendarPlus /> 予定に追加
                </Button>
            ) : (
                <Button size="sm" variant={action.kind === 'cancel' ? 'destructive' : 'outline'} onClick={apply} disabled={busy}>
                    {busy ? <Spinner /> : action.kind === 'cancel' ? <CalendarX2 /> : <CalendarClock />}
                    {action.kind === 'cancel' ? 'キャンセルにする' : '変更する'}
                </Button>
            )}
        </li>
    );
}

InboxIndex.layout = {
    breadcrumbs: [{ title: '受信箱', href: index() }],
};
