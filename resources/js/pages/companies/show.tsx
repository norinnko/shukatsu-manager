import { Head, router } from '@inertiajs/react';
import {
    CalendarPlus,
    FilePlus2,
    FileText,
    Globe,
    KeyRound,
    Pencil,
    StickyNote,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { moveCompany } from '@/components/companies/company-card';
import { CompanyFormDialog } from '@/components/companies/company-form-dialog';
import { MypageLoginButton } from '@/components/companies/mypage-login-button';
import { CopyButton } from '@/components/copy-button';
import { EmptyState } from '@/components/empty-state';
import { EsAnswerCard } from '@/components/es/es-answer-card';
import { EsAnswerFormDialog } from '@/components/es/es-answer-form-dialog';
import { EventFormDialog } from '@/components/events/event-form-dialog';
import { EventListItem } from '@/components/events/event-list-item';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useEnums } from '@/hooks/use-enums';
import { formatFullDay } from '@/lib/date';
import { tone } from '@/lib/tone';
import { cn } from '@/lib/utils';
import { destroy, index } from '@/routes/companies';
import type { Company, EsAnswer, EventItem } from '@/types';

type Props = {
    company: Company;
    events: EventItem[];
    esAnswers: EsAnswer[];
    googleConnected: boolean;
};

function InfoRow({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="grid grid-cols-[7rem_1fr] items-start gap-2 py-2 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 break-words">{children}</dd>
        </div>
    );
}

export default function CompanyShow({ company, events, esAnswers, googleConnected }: Props) {
    const { stages, phase, priority } = useEnums();
    const [editOpen, setEditOpen] = useState(false);
    const [eventDialog, setEventDialog] = useState<{ open: boolean; event: EventItem | null }>({ open: false, event: null });
    const [esDialog, setEsDialog] = useState<{ open: boolean; answer: EsAnswer | null; base: EsAnswer | null }>({
        open: false,
        answer: null,
        base: null,
    });

    const companyRef = [{ id: company.id, name: company.name }];
    const upcoming = events.filter((e) => e.status === 'upcoming').reverse();
    const past = events.filter((e) => e.status !== 'upcoming');

    const remove = () => {
        if (confirm(`「${company.name}」を削除しますか？\n予定も一緒に削除されます（ES回答は残ります）。`)) {
            router.delete(destroy(company.id).url);
        }
    };

    return (
        <>
            <Head title={company.name} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">{company.name}</h1>
                        <div className="flex flex-wrap gap-1.5">
                            <ToneBadge color={phase[company.phase]?.color}>{phase[company.phase]?.label}</ToneBadge>
                            {company.priority && (
                                <ToneBadge color={priority[company.priority]?.color}>{priority[company.priority]?.label}</ToneBadge>
                            )}
                            {company.tags.map((t) => (
                                <ToneBadge key={t} color="slate">
                                    #{t}
                                </ToneBadge>
                            ))}
                            {company.archived && <ToneBadge color="zinc">アーカイブ</ToneBadge>}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <MypageLoginButton url={company.mypage_url} loginId={company.mypage_login_id} className="h-9 px-4" />
                        <Button variant="outline" onClick={() => setEditOpen(true)}>
                            <Pencil /> 編集
                        </Button>
                        <Button variant="ghost" size="icon" onClick={remove} aria-label="削除">
                            <Trash2 className="text-red-600" />
                        </Button>
                    </div>
                </div>

                {/* Stage stepper: tap to move. */}
                <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                    <ol className="flex min-w-max gap-1.5">
                        {stages.map((s) => {
                            const active = s.value === company.stage;

                            return (
                                <li key={s.value}>
                                    <button
                                        type="button"
                                        onClick={() => moveCompany(company, s.value)}
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                            active ? tone(s.color).badge + ' ring-2 ring-offset-1 ring-offset-background' : 'text-muted-foreground hover:bg-muted',
                                        )}
                                        aria-current={active ? 'step' : undefined}
                                    >
                                        {s.label}
                                    </button>
                                </li>
                            );
                        })}
                    </ol>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-6">
                        <section className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-semibold">予定</h2>
                                <Button size="sm" variant="outline" onClick={() => setEventDialog({ open: true, event: null })}>
                                    <CalendarPlus /> 予定を追加
                                </Button>
                            </div>
                            {upcoming.length ? (
                                <ul className="space-y-2">
                                    {upcoming.map((e) => (
                                        <EventListItem
                                            key={e.id}
                                            event={e}
                                            showCompany={false}
                                            googleConnected={googleConnected}
                                            onEdit={(event) => setEventDialog({ open: true, event })}
                                        />
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState icon={CalendarPlus} title="これからの予定はありません" />
                            )}
                            {past.length > 0 && (
                                <details className="group">
                                    <summary className="cursor-pointer text-xs text-muted-foreground hover:text-foreground">
                                        終わった予定（{past.length}件）
                                    </summary>
                                    <ul className="mt-2 space-y-2">
                                        {past.map((e) => (
                                            <EventListItem
                                                key={e.id}
                                                event={e}
                                                showCompany={false}
                                                onEdit={(event) => setEventDialog({ open: true, event })}
                                            />
                                        ))}
                                    </ul>
                                </details>
                            )}
                        </section>

                        <section className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-semibold">ES回答（{esAnswers.length}）</h2>
                                <Button size="sm" variant="outline" onClick={() => setEsDialog({ open: true, answer: null, base: null })}>
                                    <FilePlus2 /> ES回答を追加
                                </Button>
                            </div>
                            {esAnswers.length ? (
                                <div className="space-y-3">
                                    {esAnswers.map((a) => (
                                        <EsAnswerCard
                                            key={a.id}
                                            answer={a}
                                            showCompany={false}
                                            onEdit={(answer) => setEsDialog({ open: true, answer, base: null })}
                                            onDuplicate={(base) => setEsDialog({ open: true, answer: null, base })}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <EmptyState icon={FileText} title="この企業のES回答はまだありません" />
                            )}
                        </section>
                    </div>

                    <Card className="h-fit gap-0 py-2">
                        <CardContent className="px-4">
                            <dl className="divide-y">
                                <InfoRow label="次にやること">{company.next_action ?? '—'}</InfoRow>
                                <InfoRow label="結果連絡予定">
                                    {company.result_expected_on ? formatFullDay(company.result_expected_on) : '—'}
                                </InfoRow>
                                <InfoRow label="マイページ">
                                    {company.mypage_url ? (
                                        <a
                                            href={company.mypage_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="break-all text-sky-700 hover:underline dark:text-sky-400"
                                        >
                                            {company.mypage_url.replace(/^https?:\/\//, '')}
                                        </a>
                                    ) : (
                                        '—'
                                    )}
                                </InfoRow>
                                <InfoRow label="マイページID">
                                    {company.mypage_login_id ? (
                                        <span className="flex items-center gap-1">
                                            <KeyRound className="size-3.5 text-muted-foreground" />
                                            <code className="font-mono">{company.mypage_login_id}</code>
                                            <CopyButton value={company.mypage_login_id} label="ID" />
                                        </span>
                                    ) : (
                                        '—'
                                    )}
                                </InfoRow>
                                <InfoRow label="適性検査">{company.test_type ?? '—'}</InfoRow>
                                <InfoRow label="業界">{company.industry ?? '—'}</InfoRow>
                                <InfoRow label="採用ページ">
                                    {company.recruit_url ? (
                                        <a
                                            href={company.recruit_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1 text-sky-700 hover:underline dark:text-sky-400"
                                        >
                                            <Globe className="size-3.5" /> 開く
                                        </a>
                                    ) : (
                                        '—'
                                    )}
                                </InfoRow>
                                <div className="py-2 text-sm">
                                    <p className="mb-1 flex items-center gap-1.5 text-muted-foreground">
                                        <StickyNote className="size-3.5" /> メモ
                                    </p>
                                    <p className="whitespace-pre-line break-words">{company.memo ?? '—'}</p>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <CompanyFormDialog open={editOpen} onOpenChange={setEditOpen} company={company} />
            <EventFormDialog
                open={eventDialog.open}
                onOpenChange={(open) => setEventDialog((s) => ({ ...s, open }))}
                event={eventDialog.event}
                companies={companyRef}
                defaultCompanyId={company.id}
                googleConnected={googleConnected}
            />
            <EsAnswerFormDialog
                open={esDialog.open}
                onOpenChange={(open) => setEsDialog((s) => ({ ...s, open }))}
                answer={esDialog.answer}
                duplicateFrom={esDialog.base}
                companies={companyRef}
                defaultCompanyId={company.id}
            />
        </>
    );
}

CompanyShow.layout = {
    breadcrumbs: [{ title: '企業', href: index() }],
};
