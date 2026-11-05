import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect } from 'react';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store as storeCertification, update as updateCertification } from '@/routes/certifications';
import { store as storeEducation, update as updateEducation } from '@/routes/educations';
import { update as updateProfile } from '@/routes/profile';
import type { Certification, Education, Profile } from '@/types';

function FormDialog({
    open,
    onOpenChange,
    title,
    processing,
    onSubmit,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    processing: boolean;
    onSubmit: (e: FormEvent) => void;
    children: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <form onSubmit={onSubmit} className="grid gap-4">
                    {children}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            キャンセル
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            保存
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

const PROFILE_FIELDS: { key: keyof Profile; label: string; type?: string; placeholder?: string }[] = [
    { key: 'full_name', label: '氏名', placeholder: '藤井 則行' },
    { key: 'full_name_kana', label: 'フリガナ', placeholder: 'フジイ ノリユキ' },
    { key: 'full_name_roman', label: 'ローマ字', placeholder: 'FUJII NORIYUKI' },
    { key: 'birth_date', label: '生年月日', type: 'date' },
    { key: 'contact_email', label: '連絡用メール', type: 'email' },
    { key: 'phone', label: '電話番号', type: 'tel' },
    { key: 'postal_code', label: '郵便番号', placeholder: '000-0000' },
    { key: 'address', label: '住所' },
    { key: 'github_url', label: 'GitHub', type: 'url', placeholder: 'https://github.com/' },
    { key: 'portfolio_url', label: 'ポートフォリオ', type: 'url', placeholder: 'https://' },
];

export function ProfileDialog({
    open,
    onOpenChange,
    profile,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    profile: Profile;
}) {
    const toData = (p: Profile) =>
        Object.fromEntries(
            [...PROFILE_FIELDS.map((f) => f.key), 'note'].map((k) => [k, (p[k as keyof Profile] as string) ?? '']),
        ) as Record<keyof Profile, string>;
    const form = useForm(toData(profile));

    useEffect(() => {
        if (open) {
            form.setData(toData(profile));
            form.clearErrors();
        }
    }, [open]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) =>
            Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])),
        );
        form.submit(updateProfile(), { preserveScroll: true, onSuccess: () => onOpenChange(false) });
    };

    return (
        <FormDialog open={open} onOpenChange={onOpenChange} title="基本情報を編集" processing={form.processing} onSubmit={submit}>
            <div className="grid gap-3 sm:grid-cols-2">
                {PROFILE_FIELDS.map((f) => (
                    <Field
                        key={f.key}
                        label={f.label}
                        htmlFor={f.key}
                        error={form.errors[f.key]}
                        className={f.key === 'address' ? 'sm:col-span-2' : undefined}
                    >
                        <Input
                            id={f.key}
                            type={f.type ?? 'text'}
                            value={form.data[f.key]}
                            placeholder={f.placeholder}
                            onChange={(e) => form.setData(f.key, e.target.value)}
                        />
                    </Field>
                ))}
            </div>
            <Field label="メモ" htmlFor="note" error={form.errors.note}>
                <Textarea id="note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} rows={3} />
            </Field>
        </FormDialog>
    );
}

export function EducationDialog({
    open,
    onOpenChange,
    education,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    education: Education | null;
}) {
    const toData = (e: Education | null) => ({
        school: e?.school ?? '',
        faculty: e?.faculty ?? '',
        department: e?.department ?? '',
        major: e?.major ?? '',
        degree: e?.degree ?? '',
        entered_on: e?.entered_on ?? '',
        graduated_on: e?.graduated_on ?? '',
        graduation_status: e?.graduation_status ?? 'graduated',
    });
    const form = useForm(toData(education));

    useEffect(() => {
        if (open) {
            form.setData(toData(education));
            form.clearErrors();
        }
    }, [open, education?.id]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) =>
            Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])),
        );
        form.submit(education ? updateEducation(education.id) : storeEducation(), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={education ? '学歴を編集' : '学歴を追加'}
            processing={form.processing}
            onSubmit={submit}
        >
            <Field label="学校名（正式名称）" htmlFor="school" error={form.errors.school}>
                <Input id="school" value={form.data.school} onChange={(e) => form.setData('school', e.target.value)} required />
            </Field>
            <div className="grid grid-cols-2 gap-3">
                <Field label="学部" htmlFor="faculty" error={form.errors.faculty}>
                    <Input id="faculty" value={form.data.faculty} onChange={(e) => form.setData('faculty', e.target.value)} />
                </Field>
                <Field label="学科" htmlFor="department" error={form.errors.department}>
                    <Input id="department" value={form.data.department} onChange={(e) => form.setData('department', e.target.value)} />
                </Field>
                <Field label="専攻・コース" htmlFor="major" error={form.errors.major}>
                    <Input id="major" value={form.data.major} onChange={(e) => form.setData('major', e.target.value)} />
                </Field>
                <Field label="学位" htmlFor="degree" error={form.errors.degree}>
                    <Input
                        id="degree"
                        value={form.data.degree}
                        onChange={(e) => form.setData('degree', e.target.value)}
                        placeholder="学士 / 高度専門士"
                    />
                </Field>
                <Field label="入学年月" htmlFor="entered_on" error={form.errors.entered_on}>
                    <Input
                        id="entered_on"
                        type="month"
                        value={form.data.entered_on}
                        onChange={(e) => form.setData('entered_on', e.target.value)}
                        required
                    />
                </Field>
                <Field label="卒業年月" htmlFor="graduated_on" error={form.errors.graduated_on}>
                    <Input
                        id="graduated_on"
                        type="month"
                        value={form.data.graduated_on}
                        onChange={(e) => form.setData('graduated_on', e.target.value)}
                    />
                </Field>
            </div>
            <Field label="状態" htmlFor="graduation_status" error={form.errors.graduation_status}>
                <NativeSelect
                    id="graduation_status"
                    value={form.data.graduation_status}
                    onChange={(e) => form.setData('graduation_status', e.target.value as 'graduated' | 'expected')}
                >
                    <option value="graduated">卒業</option>
                    <option value="expected">卒業見込み</option>
                </NativeSelect>
            </Field>
        </FormDialog>
    );
}

export function CertificationDialog({
    open,
    onOpenChange,
    certification,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    certification: Certification | null;
}) {
    const toData = (c: Certification | null) => ({
        name: c?.name ?? '',
        acquired_on: c?.acquired_on ?? '',
        score: c?.score ?? '',
    });
    const form = useForm(toData(certification));

    useEffect(() => {
        if (open) {
            form.setData(toData(certification));
            form.clearErrors();
        }
    }, [open, certification?.id]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) =>
            Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])),
        );
        form.submit(certification ? updateCertification(certification.id) : storeCertification(), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={certification ? '資格を編集' : '資格を追加'}
            processing={form.processing}
            onSubmit={submit}
        >
            <Field label="資格名（正式名称）" htmlFor="cert_name" error={form.errors.name}>
                <Input id="cert_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
            </Field>
            <div className="grid grid-cols-2 gap-3">
                <Field label="取得年月" htmlFor="acquired_on" error={form.errors.acquired_on}>
                    <Input
                        id="acquired_on"
                        type="month"
                        value={form.data.acquired_on}
                        onChange={(e) => form.setData('acquired_on', e.target.value)}
                    />
                </Field>
                <Field label="スコア・級" htmlFor="score" error={form.errors.score}>
                    <Input
                        id="score"
                        value={form.data.score}
                        onChange={(e) => form.setData('score', e.target.value)}
                        placeholder="例：TOEIC 650"
                    />
                </Field>
            </div>
        </FormDialog>
    );
}
