import { Link, router } from '@inertiajs/react';
import { ArrowRightLeft, ExternalLink, MoreHorizontal } from 'lucide-react';
import { MypageLoginButton } from '@/components/companies/mypage-login-button';
import { CountdownBadge } from '@/components/countdown-badge';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useEnums } from '@/hooks/use-enums';
import { cn } from '@/lib/utils';
import { show, stage as stageRoute } from '@/routes/companies';
import type { Company } from '@/types';

export function moveCompany(company: Company, stage: string) {
    if (stage !== company.stage) {
        router.patch(stageRoute(company.id).url, { stage }, { preserveScroll: true, preserveState: true });
    }
}

export function CompanyCard({ company, draggable = false }: { company: Company; draggable?: boolean }) {
    const { stages, phase, priority } = useEnums();
    const next = company.next_event;

    return (
        <div
            draggable={draggable}
            onDragStart={(e) => {
                e.dataTransfer.setData('text/company-id', String(company.id));
                e.dataTransfer.effectAllowed = 'move';
            }}
            className={cn(
                'group rounded-lg border bg-card p-3 shadow-xs transition-shadow hover:shadow-md',
                draggable && 'cursor-grab active:cursor-grabbing',
                company.priority === 'first' && 'border-l-4 border-l-rose-500',
            )}
        >
            <div className="flex items-start gap-1">
                <Link href={show(company.id)} className="min-w-0 flex-1 text-sm font-semibold leading-snug hover:underline">
                    {company.name}
                </Link>
                <MypageLoginButton
                    url={company.mypage_url}
                    loginId={company.mypage_login_id}
                    size="icon"
                    className="-mt-1 size-7 border-0 shadow-none"
                />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="-mt-1 -mr-1 size-7" aria-label="操作">
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-52">
                        <DropdownMenuLabel className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <ArrowRightLeft className="size-3.5" /> 段階を移動
                        </DropdownMenuLabel>
                        <DropdownMenuRadioGroup value={company.stage} onValueChange={(v) => moveCompany(company, v)}>
                            {stages.map((s) => (
                                <DropdownMenuRadioItem key={s.value} value={s.value}>
                                    {s.label}
                                </DropdownMenuRadioItem>
                            ))}
                        </DropdownMenuRadioGroup>
                        {company.mypage_url && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <a href={company.mypage_url} target="_blank" rel="noopener noreferrer">
                                        <ExternalLink /> マイページを開く
                                    </a>
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div className="mt-2 flex flex-wrap gap-1">
                <ToneBadge color={phase[company.phase]?.color}>{phase[company.phase]?.label}</ToneBadge>
                {company.priority && (
                    <ToneBadge color={priority[company.priority]?.color}>{priority[company.priority]?.label}</ToneBadge>
                )}
                {company.test_type && <ToneBadge color="orange">{company.test_type}</ToneBadge>}
            </div>

            {next ? (
                <div className="mt-2 flex items-center gap-1.5 text-xs">
                    <CountdownBadge dueAt={next.due_at} startsAt={next.starts_at} />
                    <span className="truncate text-muted-foreground">{next.title}</span>
                </div>
            ) : (
                company.next_action && (
                    <p className="mt-2 truncate text-xs text-muted-foreground">次：{company.next_action}</p>
                )
            )}
        </div>
    );
}
