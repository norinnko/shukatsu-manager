import { Link, router } from '@inertiajs/react';
import { CopyPlus, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CopyButton } from '@/components/copy-button';
import { CharCount } from '@/components/es/char-count';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useEnums } from '@/hooks/use-enums';
import { cn } from '@/lib/utils';
import { show as companyRoute } from '@/routes/companies';
import { destroy } from '@/routes/es';
import type { EsAnswer } from '@/types';

export function EsAnswerCard({
    answer,
    onEdit,
    onDuplicate,
    showCompany = true,
}: {
    answer: EsAnswer;
    onEdit: (answer: EsAnswer) => void;
    onDuplicate: (answer: EsAnswer) => void;
    showCompany?: boolean;
}) {
    const { esCategory } = useEnums();
    const [expanded, setExpanded] = useState(false);
    const category = esCategory[answer.category];

    const remove = () => {
        if (confirm('このES回答を削除しますか？')) {
            router.delete(destroy(answer.id).url, { preserveScroll: true });
        }
    };

    return (
        <article className="rounded-lg border bg-card p-4">
            <header className="mb-2 flex items-start gap-2">
                <div className="min-w-0 flex-1 space-y-1.5">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <ToneBadge color={category?.color}>{category?.label}</ToneBadge>
                        {showCompany &&
                            (answer.company ? (
                                <Link
                                    href={companyRoute(answer.company.id)}
                                    className="text-xs text-muted-foreground hover:text-foreground hover:underline"
                                >
                                    {answer.company.name}
                                </Link>
                            ) : (
                                <span className="text-xs text-muted-foreground">共通</span>
                            ))}
                    </div>
                    <h3 className="text-sm font-semibold leading-snug">{answer.question}</h3>
                </div>
                <CopyButton value={answer.answer} label="回答" />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="size-8" aria-label="操作">
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => onEdit(answer)}>
                            <Pencil /> 編集
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => onDuplicate(answer)}>
                            <CopyPlus /> この回答を元に新規作成
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem variant="destructive" onSelect={remove}>
                            <Trash2 /> 削除
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </header>

            <button
                type="button"
                onClick={() => setExpanded((v) => !v)}
                className="w-full text-left"
                aria-expanded={expanded}
            >
                <p
                    className={cn(
                        'text-sm leading-relaxed whitespace-pre-line text-foreground/90',
                        !expanded && 'line-clamp-4',
                    )}
                >
                    {answer.answer}
                </p>
            </button>

            <footer className="mt-2 flex items-center justify-between">
                <button
                    type="button"
                    onClick={() => setExpanded((v) => !v)}
                    className="text-xs text-muted-foreground hover:text-foreground"
                >
                    {expanded ? '閉じる' : '全文を表示'}
                </button>
                <CharCount text={answer.answer} limit={answer.char_limit} />
            </footer>
        </article>
    );
}
