import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { CharCount } from '@/components/es/char-count';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useEnums } from '@/hooks/use-enums';
import { store, update } from '@/routes/es';
import type { CompanyRef, EsAnswer, EsCategory } from '@/types';

type FormData = {
    company_id: string;
    category: EsCategory;
    question: string;
    answer: string;
    char_limit: string;
};

function initialData(answer?: EsAnswer | null, companyId?: number | null, base?: EsAnswer | null): FormData {
    const source = answer ?? base;

    return {
        company_id: answer?.company_id
            ? String(answer.company_id)
            : companyId
              ? String(companyId)
              : '',
        category: source?.category ?? 'self_pr',
        question: source?.question ?? '',
        answer: source?.answer ?? '',
        char_limit: source?.char_limit ? String(source.char_limit) : '',
    };
}

export function EsAnswerFormDialog({
    open,
    onOpenChange,
    answer,
    duplicateFrom,
    companies,
    defaultCompanyId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    answer?: EsAnswer | null;
    /** Start a new answer from an existing one (reuse for another company). */
    duplicateFrom?: EsAnswer | null;
    companies: CompanyRef[];
    defaultCompanyId?: number | null;
}) {
    const { esCategories } = useEnums();
    const form = useForm<FormData>(initialData(answer, defaultCompanyId, duplicateFrom));

    useEffect(() => {
        if (open) {
            form.setData(initialData(answer, defaultCompanyId, duplicateFrom));
            form.clearErrors();
        }
    }, [open, answer?.id, duplicateFrom?.id, defaultCompanyId]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            company_id: data.company_id ? Number(data.company_id) : null,
            char_limit: data.char_limit ? Number(data.char_limit) : null,
        }));
        form.submit(answer ? update(answer.id) : store(), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {answer ? 'ES回答を編集' : duplicateFrom ? '回答を元に新規作成' : 'ES回答を追加'}
                    </DialogTitle>
                    <DialogDescription>
                        設問と回答を保存しておくと、別の企業でも使い回せます。
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <Field label="企業" htmlFor="es_company" error={form.errors.company_id} className="col-span-2 sm:col-span-1">
                            <NativeSelect
                                id="es_company"
                                value={form.data.company_id}
                                onChange={(e) => form.setData('company_id', e.target.value)}
                            >
                                <option value="">（共通）</option>
                                {companies.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label="カテゴリ" htmlFor="es_category" error={form.errors.category}>
                            <NativeSelect
                                id="es_category"
                                value={form.data.category}
                                onChange={(e) => form.setData('category', e.target.value as EsCategory)}
                            >
                                {esCategories.map((c) => (
                                    <option key={c.value} value={c.value}>
                                        {c.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label="文字数制限" htmlFor="es_limit" error={form.errors.char_limit}>
                            <Input
                                id="es_limit"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                value={form.data.char_limit}
                                onChange={(e) => form.setData('char_limit', e.target.value)}
                                placeholder="例：400"
                            />
                        </Field>
                    </div>

                    <Field label="設問" htmlFor="es_question" error={form.errors.question}>
                        <Textarea
                            id="es_question"
                            value={form.data.question}
                            onChange={(e) => form.setData('question', e.target.value)}
                            rows={2}
                            required
                        />
                    </Field>

                    <Field label="回答" htmlFor="es_answer" error={form.errors.answer}>
                        <Textarea
                            id="es_answer"
                            value={form.data.answer}
                            onChange={(e) => form.setData('answer', e.target.value)}
                            rows={10}
                            className="min-h-48 leading-relaxed"
                            required
                        />
                        <div className="text-right">
                            <CharCount
                                text={form.data.answer}
                                limit={form.data.char_limit ? Number(form.data.char_limit) : null}
                            />
                        </div>
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            キャンセル
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            保存
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
