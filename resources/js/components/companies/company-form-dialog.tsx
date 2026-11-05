import { useForm } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useEnums } from '@/hooks/use-enums';
import { store, update } from '@/routes/companies';
import type { Company, CompanyPhase, CompanyPriority, CompanyStage } from '@/types';

type FormData = {
    name: string;
    industry: string;
    phase: CompanyPhase;
    stage: CompanyStage;
    priority: CompanyPriority | '';
    tags: string;
    mypage_url: string;
    mypage_login_id: string;
    recruit_url: string;
    test_type: string;
    next_action: string;
    result_expected_on: string;
    memo: string;
    archived: boolean;
};

function initialData(company?: Company | null): FormData {
    return {
        name: company?.name ?? '',
        industry: company?.industry ?? '',
        phase: company?.phase ?? 'intern',
        stage: company?.stage ?? 'interested',
        priority: company?.priority ?? '',
        tags: company?.tags.join(', ') ?? '',
        mypage_url: company?.mypage_url ?? '',
        mypage_login_id: company?.mypage_login_id ?? '',
        recruit_url: company?.recruit_url ?? '',
        test_type: company?.test_type ?? '',
        next_action: company?.next_action ?? '',
        result_expected_on: company?.result_expected_on ?? '',
        memo: company?.memo ?? '',
        archived: company?.archived ?? false,
    };
}

export function CompanyFormDialog({
    open,
    onOpenChange,
    company,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    company?: Company | null;
}) {
    const { phases, stages, priorities } = useEnums();
    const form = useForm<FormData>(initialData(company));

    useEffect(() => {
        if (open) {
            form.setData(initialData(company));
            form.clearErrors();
        }
    }, [open, company?.id]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            priority: data.priority || null,
            tags: data.tags
                .split(/[,、]/)
                .map((t) => t.trim())
                .filter(Boolean),
            result_expected_on: data.result_expected_on || null,
            ...(company ? {} : { archived: undefined }),
        }));
        form.submit(company ? update(company.id) : store(), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{company ? '企業情報を編集' : '企業を追加'}</DialogTitle>
                    <DialogDescription>選考の段階やマイページ情報を管理します。</DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <Field label="企業名" htmlFor="name" error={form.errors.name}>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="例：株式会社〇〇"
                            required
                            autoFocus={!company}
                        />
                    </Field>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <Field label="区分" htmlFor="phase" error={form.errors.phase}>
                            <NativeSelect
                                id="phase"
                                value={form.data.phase}
                                onChange={(e) => form.setData('phase', e.target.value as CompanyPhase)}
                            >
                                {phases.map((p) => (
                                    <option key={p.value} value={p.value}>
                                        {p.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label="選考段階" htmlFor="stage" error={form.errors.stage}>
                            <NativeSelect
                                id="stage"
                                value={form.data.stage}
                                onChange={(e) => form.setData('stage', e.target.value as CompanyStage)}
                            >
                                {stages.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label="志望度" htmlFor="priority" error={form.errors.priority}>
                            <NativeSelect
                                id="priority"
                                value={form.data.priority}
                                onChange={(e) => form.setData('priority', e.target.value as CompanyPriority | '')}
                            >
                                <option value="">未設定</option>
                                {priorities.map((p) => (
                                    <option key={p.value} value={p.value}>
                                        {p.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="次にやること" htmlFor="next_action" error={form.errors.next_action}>
                            <Input
                                id="next_action"
                                value={form.data.next_action}
                                onChange={(e) => form.setData('next_action', e.target.value)}
                                placeholder="例：ES下書き"
                            />
                        </Field>
                        <Field
                            label="結果連絡の予定日"
                            htmlFor="result_expected_on"
                            error={form.errors.result_expected_on}
                        >
                            <Input
                                id="result_expected_on"
                                type="date"
                                value={form.data.result_expected_on}
                                onChange={(e) => form.setData('result_expected_on', e.target.value)}
                            />
                        </Field>
                    </div>

                    <div className="grid gap-3 rounded-lg border p-3">
                        <p className="text-sm font-medium">マイページ</p>
                        <Field label="マイページURL" htmlFor="mypage_url" error={form.errors.mypage_url}>
                            <Input
                                id="mypage_url"
                                type="url"
                                inputMode="url"
                                value={form.data.mypage_url}
                                onChange={(e) => form.setData('mypage_url', e.target.value)}
                                placeholder="https://"
                            />
                        </Field>
                        <Field label="ログインID" htmlFor="mypage_login_id" error={form.errors.mypage_login_id}>
                            <Input
                                id="mypage_login_id"
                                value={form.data.mypage_login_id}
                                onChange={(e) => form.setData('mypage_login_id', e.target.value)}
                                autoComplete="off"
                            />
                        </Field>
                        <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
                            <ShieldAlert className="mt-0.5 size-3.5 shrink-0 text-amber-600" />
                            パスワードはここに保存しません。パスワードマネージャー（iCloudキーチェーン・1Passwordなど）で管理してください。
                        </p>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="採用ページURL" htmlFor="recruit_url" error={form.errors.recruit_url}>
                            <Input
                                id="recruit_url"
                                type="url"
                                inputMode="url"
                                value={form.data.recruit_url}
                                onChange={(e) => form.setData('recruit_url', e.target.value)}
                                placeholder="https://"
                            />
                        </Field>
                        <Field label="業界" htmlFor="industry" error={form.errors.industry}>
                            <Input
                                id="industry"
                                value={form.data.industry}
                                onChange={(e) => form.setData('industry', e.target.value)}
                                placeholder="例：セキュリティ / SIer"
                            />
                        </Field>
                        <Field label="適性検査の種類" htmlFor="test_type" error={form.errors.test_type}>
                            <Input
                                id="test_type"
                                value={form.data.test_type}
                                onChange={(e) => form.setData('test_type', e.target.value)}
                                placeholder="例：SPI / TG-WEB / 玉手箱"
                                list="test-type-options"
                            />
                            <datalist id="test-type-options">
                                <option value="SPI" />
                                <option value="TG-WEB" />
                                <option value="玉手箱" />
                                <option value="GAB" />
                                <option value="CAB" />
                                <option value="eF-1G" />
                            </datalist>
                        </Field>
                        <Field label="タグ" htmlFor="tags" error={form.errors.tags} hint="カンマ区切り">
                            <Input
                                id="tags"
                                value={form.data.tags}
                                onChange={(e) => form.setData('tags', e.target.value)}
                                placeholder="例：大手, セキュリティ"
                            />
                        </Field>
                    </div>

                    <Field label="メモ" htmlFor="memo" error={form.errors.memo}>
                        <Textarea
                            id="memo"
                            value={form.data.memo}
                            onChange={(e) => form.setData('memo', e.target.value)}
                            rows={4}
                        />
                    </Field>

                    {company && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="archived"
                                checked={form.data.archived}
                                onCheckedChange={(v) => form.setData('archived', v === true)}
                            />
                            <Label htmlFor="archived">アーカイブ（一覧に表示しない）</Label>
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            キャンセル
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {company ? '保存' : '追加'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
