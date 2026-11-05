import { Head, Link, router } from '@inertiajs/react';
import { Archive, Building2, Columns3, List, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { CompanyCard, moveCompany } from '@/components/companies/company-card';
import { CompanyFormDialog } from '@/components/companies/company-form-dialog';
import { MypageLoginButton } from '@/components/companies/mypage-login-button';
import { CountdownBadge } from '@/components/countdown-badge';
import { EmptyState } from '@/components/empty-state';
import { ToneBadge, ToneDot } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useEnums } from '@/hooks/use-enums';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/companies';
import type { Company, CompanyPhase, CompanyStage } from '@/types';

type Props = {
    companies: Company[];
    filters: { q: string; archived: boolean };
};

type View = 'board' | 'list';

const CLOSED: CompanyStage[] = ['rejected', 'declined'];

function initialView(): View {
    try {
        const saved = localStorage.getItem('companies-view');

        if (saved === 'board' || saved === 'list') {
            return saved;
        }
    } catch {
        // storage unavailable
    }

    return typeof window !== 'undefined' && window.innerWidth < 768 ? 'list' : 'board';
}

const PRIORITY_WEIGHT: Record<string, number> = { first: 0, normal: 1, backup: 2 };

function sortCompanies(a: Company, b: Company): number {
    const pa = a.priority ? PRIORITY_WEIGHT[a.priority] : 1.5;
    const pb = b.priority ? PRIORITY_WEIGHT[b.priority] : 1.5;

    if (pa !== pb) {
        return pa - pb;
    }

    const na = a.next_event?.starts_at ?? '9999';
    const nb = b.next_event?.starts_at ?? '9999';

    return na.localeCompare(nb) || a.name.localeCompare(b.name, 'ja');
}

export default function CompaniesIndex({ companies, filters }: Props) {
    const { stages, phases, stage: stageLookup, phase: phaseLookup, priority } = useEnums();
    const [view, setView] = useState<View>(initialView);
    const [query, setQuery] = useState(filters.q);
    const [phaseFilter, setPhaseFilter] = useState<CompanyPhase | 'all'>('all');
    const [showClosed, setShowClosed] = useState(false);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dragOver, setDragOver] = useState<CompanyStage | null>(null);

    const changeView = (v: View) => {
        setView(v);
        try {
            localStorage.setItem('companies-view', v);
        } catch {
            // ignore
        }
    };

    const visible = useMemo(() => {
        const q = query.trim().toLowerCase();

        return companies
            .filter((c) => phaseFilter === 'all' || c.phase === phaseFilter)
            .filter(
                (c) =>
                    !q ||
                    c.name.toLowerCase().includes(q) ||
                    (c.industry ?? '').toLowerCase().includes(q) ||
                    c.tags.some((t) => t.toLowerCase().includes(q)),
            )
            .sort(sortCompanies);
    }, [companies, query, phaseFilter]);

    const columns = stages.filter((s) => showClosed || !CLOSED.includes(s.value));

    const onDrop = (stage: CompanyStage, e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(null);
        const id = Number(e.dataTransfer.getData('text/company-id'));
        const company = companies.find((c) => c.id === id);

        if (company) {
            moveCompany(company, stage);
        }
    };

    return (
        <>
            <Head title="企業" />

            <div className="flex w-full min-w-0 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">企業</h1>
                    <div className="flex items-center gap-2">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={view}
                            onValueChange={(v) => v && changeView(v as View)}
                        >
                            <ToggleGroupItem value="board" aria-label="ボード表示">
                                <Columns3 className="size-4" />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="list" aria-label="リスト表示">
                                <List className="size-4" />
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <Button onClick={() => setDialogOpen(true)}>
                            <Plus /> 企業を追加
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full sm:w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="企業名・業界・タグで絞り込み"
                            className="pl-8"
                        />
                    </div>
                    <div className="flex flex-wrap gap-1">
                        {[{ value: 'all', label: 'すべて' }, ...phases].map((p) => (
                            <Button
                                key={p.value}
                                size="sm"
                                variant={phaseFilter === p.value ? 'default' : 'outline'}
                                onClick={() => setPhaseFilter(p.value as CompanyPhase | 'all')}
                            >
                                {p.label}
                            </Button>
                        ))}
                    </div>
                    <div className="ml-auto flex items-center gap-1">
                        <Button size="sm" variant={showClosed ? 'secondary' : 'ghost'} onClick={() => setShowClosed((v) => !v)}>
                            不合格・辞退も表示
                        </Button>
                        <Button
                            size="sm"
                            variant={filters.archived ? 'secondary' : 'ghost'}
                            onClick={() =>
                                router.get(index().url, filters.archived ? {} : { archived: 1 }, { preserveScroll: true })
                            }
                        >
                            <Archive /> アーカイブ{filters.archived ? 'を隠す' : 'も表示'}
                        </Button>
                    </div>
                </div>

                {companies.length === 0 ? (
                    <EmptyState icon={Building2} title="まだ企業が登録されていません">
                        「企業を追加」から登録しましょう。
                    </EmptyState>
                ) : view === 'board' ? (
                    <div className="-mx-4 overflow-x-auto px-4 pb-2 md:-mx-6 md:px-6">
                        <div className="flex min-w-max gap-3">
                            {columns.map((col) => {
                                const items = visible.filter((c) => c.stage === col.value);

                                return (
                                    <section
                                        key={col.value}
                                        onDragOver={(e) => {
                                            e.preventDefault();
                                            setDragOver(col.value);
                                        }}
                                        onDragLeave={() => setDragOver((s) => (s === col.value ? null : s))}
                                        onDrop={(e) => onDrop(col.value, e)}
                                        className={cn(
                                            'flex w-64 shrink-0 flex-col gap-2 rounded-xl bg-muted/50 p-2 transition-colors',
                                            dragOver === col.value && 'bg-sky-100 ring-2 ring-sky-400 dark:bg-sky-950',
                                        )}
                                    >
                                        <h2 className="flex items-center gap-2 px-1 pt-1 text-sm font-semibold">
                                            <ToneDot color={col.color} />
                                            {col.label}
                                            <span className="ml-auto text-xs font-normal text-muted-foreground">{items.length}</span>
                                        </h2>
                                        <div className="flex min-h-16 flex-col gap-2">
                                            {items.map((c) => (
                                                <CompanyCard key={c.id} company={c} draggable />
                                            ))}
                                        </div>
                                    </section>
                                );
                            })}
                        </div>
                    </div>
                ) : (
                    <ul className="divide-y rounded-xl border bg-card">
                        {visible
                            .filter((c) => showClosed || !CLOSED.includes(c.stage))
                            .map((c) => (
                                <li key={c.id} className="flex flex-wrap items-center gap-x-3 gap-y-1.5 p-3">
                                    <div className="min-w-0 flex-1 basis-60">
                                        <Link href={show(c.id)} className="font-medium hover:underline">
                                            {c.name}
                                        </Link>
                                        <div className="mt-1 flex flex-wrap gap-1">
                                            <ToneBadge color={stageLookup[c.stage]?.color}>{stageLookup[c.stage]?.label}</ToneBadge>
                                            <ToneBadge color={phaseLookup[c.phase]?.color}>{phaseLookup[c.phase]?.label}</ToneBadge>
                                            {c.priority && (
                                                <ToneBadge color={priority[c.priority]?.color}>{priority[c.priority]?.label}</ToneBadge>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex min-w-0 basis-56 items-center gap-1.5 text-xs text-muted-foreground">
                                        {c.next_event ? (
                                            <>
                                                <CountdownBadge dueAt={c.next_event.due_at} startsAt={c.next_event.starts_at} />
                                                <span className="truncate">{c.next_event.title}</span>
                                            </>
                                        ) : (
                                            c.next_action && <span className="truncate">{c.next_action}</span>
                                        )}
                                    </div>
                                    <MypageLoginButton url={c.mypage_url} loginId={c.mypage_login_id} />
                                </li>
                            ))}
                    </ul>
                )}
            </div>

            <CompanyFormDialog open={dialogOpen} onOpenChange={setDialogOpen} />
        </>
    );
}

CompaniesIndex.layout = {
    breadcrumbs: [{ title: '企業', href: index() }],
};
