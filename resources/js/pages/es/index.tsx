import { Head } from '@inertiajs/react';
import { FileText, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { EsAnswerCard } from '@/components/es/es-answer-card';
import { EsAnswerFormDialog } from '@/components/es/es-answer-form-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useEnums } from '@/hooks/use-enums';
import { index } from '@/routes/es';
import type { CompanyRef, EsAnswer, EsCategory } from '@/types';

type Props = {
    answers: EsAnswer[];
    companies: CompanyRef[];
    filters: { q: string; category: string };
};

export default function EsIndex({ answers, companies, filters }: Props) {
    const { esCategories } = useEnums();
    const [query, setQuery] = useState(filters.q);
    const [category, setCategory] = useState<EsCategory | ''>((filters.category as EsCategory) || '');
    const [dialog, setDialog] = useState<{ open: boolean; answer: EsAnswer | null; base: EsAnswer | null }>({
        open: false,
        answer: null,
        base: null,
    });

    const visible = useMemo(() => {
        const q = query.trim().toLowerCase();

        return answers
            .filter((a) => !category || a.category === category)
            .filter(
                (a) =>
                    !q ||
                    a.question.toLowerCase().includes(q) ||
                    a.answer.toLowerCase().includes(q) ||
                    (a.company?.name ?? '').toLowerCase().includes(q),
            );
    }, [answers, query, category]);

    const counts = useMemo(
        () =>
            answers.reduce<Record<string, number>>((acc, a) => {
                acc[a.category] = (acc[a.category] ?? 0) + 1;

                return acc;
            }, {}),
        [answers],
    );

    return (
        <>
            <Head title="ES回答" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">ES回答</h1>
                        <p className="text-sm text-muted-foreground">
                            過去の回答を検索して、コピー・使い回しできます。
                        </p>
                    </div>
                    <Button onClick={() => setDialog({ open: true, answer: null, base: null })}>
                        <Plus /> 回答を追加
                    </Button>
                </div>

                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="設問・回答・企業名で検索（例：ハッカソン、CTF）"
                        className="pl-8"
                    />
                </div>

                <div className="flex flex-wrap gap-1">
                    <Button size="sm" variant={category === '' ? 'default' : 'outline'} onClick={() => setCategory('')}>
                        すべて（{answers.length}）
                    </Button>
                    {esCategories.map((c) => (
                        <Button
                            key={c.value}
                            size="sm"
                            variant={category === c.value ? 'default' : 'outline'}
                            onClick={() => setCategory(c.value)}
                        >
                            {c.label}（{counts[c.value] ?? 0}）
                        </Button>
                    ))}
                </div>

                {visible.length ? (
                    <div className="space-y-3">
                        {visible.map((a) => (
                            <EsAnswerCard
                                key={a.id}
                                answer={a}
                                onEdit={(answer) => setDialog({ open: true, answer, base: null })}
                                onDuplicate={(base) => setDialog({ open: true, answer: null, base })}
                            />
                        ))}
                    </div>
                ) : (
                    <EmptyState icon={FileText} title={answers.length ? '条件に合う回答がありません' : 'まだES回答がありません'} />
                )}
            </div>

            <EsAnswerFormDialog
                open={dialog.open}
                onOpenChange={(open) => setDialog((s) => ({ ...s, open }))}
                answer={dialog.answer}
                duplicateFrom={dialog.base}
                companies={companies}
            />
        </>
    );
}

EsIndex.layout = {
    breadcrumbs: [{ title: 'ES回答', href: index() }],
};
