import { Head, router } from '@inertiajs/react';
import { Award, GraduationCap, Pencil, Plus, Trash2, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { CopyButton } from '@/components/copy-button';
import { CertificationDialog, EducationDialog, ProfileDialog } from '@/components/profile/profile-dialogs';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    certificationsText,
    educationTimeline,
    formatYm,
    schoolFullName,
    YM_FORMATS,
} from '@/lib/profile-format';
import type { YmFormat } from '@/lib/profile-format';
import { destroy as destroyCertification } from '@/routes/certifications';
import { destroy as destroyEducation } from '@/routes/educations';
import { show } from '@/routes/profile';
import type { Certification, Education, Profile } from '@/types';

type Props = {
    profile: Profile;
    educations: Education[];
    certifications: Certification[];
};

function CopyRow({ label, value, mono = false }: { label: string; value: string | null | undefined; mono?: boolean }) {
    return (
        <div className="flex min-h-11 items-center gap-2 py-1.5 text-sm">
            <span className="w-24 shrink-0 text-muted-foreground">{label}</span>
            <span className={`min-w-0 flex-1 break-all ${mono ? 'font-mono' : ''} ${value ? '' : 'text-muted-foreground'}`}>
                {value || '未入力'}
            </span>
            <CopyButton value={value} label={label} />
        </div>
    );
}

function SectionCard({ icon: Icon, title, action, children }: { icon: typeof UserRound; title: string; action?: ReactNode; children: ReactNode }) {
    return (
        <Card className="gap-2 py-4">
            <CardHeader className="flex flex-row items-center justify-between px-4">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Icon className="size-4" /> {title}
                </CardTitle>
                {action}
            </CardHeader>
            <CardContent className="px-4">{children}</CardContent>
        </Card>
    );
}

function readFormat(): YmFormat {
    try {
        const saved = localStorage.getItem('ym-format');

        if (YM_FORMATS.some((f) => f.value === saved)) {
            return saved as YmFormat;
        }
    } catch {
        // storage unavailable
    }

    return 'kanji';
}

export default function ProfileIndex({ profile, educations, certifications }: Props) {
    const [format, setFormat] = useState<YmFormat>(readFormat);
    const [profileOpen, setProfileOpen] = useState(false);
    const [eduDialog, setEduDialog] = useState<{ open: boolean; item: Education | null }>({ open: false, item: null });
    const [certDialog, setCertDialog] = useState<{ open: boolean; item: Certification | null }>({ open: false, item: null });

    const changeFormat = (value: YmFormat) => {
        setFormat(value);
        try {
            localStorage.setItem('ym-format', value);
        } catch {
            // ignore
        }
    };

    const removeEducation = (e: Education) => {
        if (confirm(`「${e.school}」を削除しますか？`)) {
            router.delete(destroyEducation(e.id).url, { preserveScroll: true });
        }
    };

    const removeCertification = (c: Certification) => {
        if (confirm(`「${c.name}」を削除しますか？`)) {
            router.delete(destroyCertification(c.id).url, { preserveScroll: true });
        }
    };

    const newestFirst = [...educations].sort((a, b) => b.entered_on.localeCompare(a.entered_on));

    return (
        <>
            <Head title="基本情報" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">基本情報</h1>
                    <p className="text-sm text-muted-foreground">
                        マイページの入力欄にそのまま貼り付けられます。年月の書き方を選んでからコピーしてください。
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-muted-foreground">年月の形式</span>
                    <ToggleGroup type="single" variant="outline" value={format} onValueChange={(v) => v && changeFormat(v as YmFormat)}>
                        {YM_FORMATS.map((f) => (
                            <ToggleGroupItem key={f.value} value={f.value} className="px-3 text-xs">
                                {f.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>

                <SectionCard
                    icon={UserRound}
                    title="プロフィール"
                    action={
                        <Button size="sm" variant="outline" onClick={() => setProfileOpen(true)}>
                            <Pencil /> 編集
                        </Button>
                    }
                >
                    <div className="divide-y">
                        <CopyRow label="氏名" value={profile.full_name} />
                        <CopyRow label="フリガナ" value={profile.full_name_kana} />
                        <CopyRow label="ローマ字" value={profile.full_name_roman} />
                        <CopyRow label="生年月日" value={profile.birth_date} />
                        <CopyRow label="メール" value={profile.contact_email} />
                        <CopyRow label="電話番号" value={profile.phone} />
                        <CopyRow label="郵便番号" value={profile.postal_code} />
                        <CopyRow label="住所" value={profile.address} />
                        <CopyRow label="GitHub" value={profile.github_url} mono />
                        <CopyRow label="ポートフォリオ" value={profile.portfolio_url} mono />
                    </div>
                </SectionCard>

                <SectionCard
                    icon={GraduationCap}
                    title="学歴"
                    action={
                        <Button size="sm" variant="outline" onClick={() => setEduDialog({ open: true, item: null })}>
                            <Plus /> 追加
                        </Button>
                    }
                >
                    <div className="mb-3 flex flex-wrap gap-2">
                        <CopyButton size="sm" value={educationTimeline(educations, format)} label="学歴（古い順）" />
                        <span className="self-center text-xs text-muted-foreground">古い順の年表</span>
                        <CopyButton size="sm" value={educationTimeline(educations, format, true)} label="学歴（新しい順）" />
                        <span className="self-center text-xs text-muted-foreground">新しい順の年表</span>
                    </div>
                    <pre className="mb-4 overflow-x-auto rounded-md bg-muted p-3 text-xs leading-relaxed whitespace-pre-wrap">
                        {educationTimeline(educations, format) || '学歴が未登録です'}
                    </pre>

                    <div className="space-y-3">
                        {newestFirst.map((e, i) => (
                            <div key={e.id} className="rounded-lg border p-3">
                                <div className="mb-1 flex items-center gap-2">
                                    <span className="text-xs text-muted-foreground">学歴{i + 1}{i === 0 ? '（最終学歴）' : ''}</span>
                                    <div className="ml-auto flex">
                                        <Button variant="ghost" size="icon" className="size-8" onClick={() => setEduDialog({ open: true, item: e })} aria-label="編集">
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" className="size-8" onClick={() => removeEducation(e)} aria-label="削除">
                                            <Trash2 className="size-4 text-red-600" />
                                        </Button>
                                    </div>
                                </div>
                                <div className="divide-y">
                                    <CopyRow label="学校名" value={e.school} />
                                    {(e.faculty || e.department || e.major) && (
                                        <CopyRow label="学部・学科" value={[e.faculty, e.department, e.major].filter(Boolean).join(' ')} />
                                    )}
                                    <CopyRow label="正式表記" value={schoolFullName(e)} />
                                    {e.degree && <CopyRow label="学位" value={e.degree} />}
                                    <CopyRow label="入学" value={formatYm(e.entered_on, format)} />
                                    <CopyRow
                                        label={e.graduation_status === 'expected' ? '卒業見込み' : '卒業'}
                                        value={formatYm(e.graduated_on, format)}
                                    />
                                    <CopyRow
                                        label="在籍期間"
                                        value={e.graduated_on ? `${formatYm(e.entered_on, format)}~${formatYm(e.graduated_on, format)}` : ''}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </SectionCard>

                <SectionCard
                    icon={Award}
                    title="資格"
                    action={
                        <Button size="sm" variant="outline" onClick={() => setCertDialog({ open: true, item: null })}>
                            <Plus /> 追加
                        </Button>
                    }
                >
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                        <CopyButton size="sm" value={certificationsText(certifications, format, 'newline')} label="資格（改行区切り）" />
                        <span className="text-xs text-muted-foreground">改行区切り</span>
                        <CopyButton size="sm" value={certificationsText(certifications, format, 'comma')} label="資格（読点区切り）" />
                        <span className="text-xs text-muted-foreground">読点区切り</span>
                    </div>
                    <ul className="divide-y">
                        {certifications.map((c) => (
                            <li key={c.id} className="flex min-h-11 items-center gap-2 py-1.5 text-sm">
                                <span className="min-w-0 flex-1">
                                    {c.name}
                                    {c.score && <span className="ml-1 text-muted-foreground">{c.score}</span>}
                                </span>
                                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">{formatYm(c.acquired_on, format)}</span>
                                <CopyButton value={c.name} label={c.name} />
                                <Button variant="ghost" size="icon" className="size-8" onClick={() => setCertDialog({ open: true, item: c })} aria-label="編集">
                                    <Pencil className="size-4" />
                                </Button>
                                <Button variant="ghost" size="icon" className="size-8" onClick={() => removeCertification(c)} aria-label="削除">
                                    <Trash2 className="size-4 text-red-600" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                </SectionCard>
            </div>

            <ProfileDialog open={profileOpen} onOpenChange={setProfileOpen} profile={profile} />
            <EducationDialog
                open={eduDialog.open}
                onOpenChange={(open) => setEduDialog((s) => ({ ...s, open }))}
                education={eduDialog.item}
            />
            <CertificationDialog
                open={certDialog.open}
                onOpenChange={(open) => setCertDialog((s) => ({ ...s, open }))}
                certification={certDialog.item}
            />
        </>
    );
}

ProfileIndex.layout = {
    breadcrumbs: [{ title: '基本情報', href: show() }],
};
