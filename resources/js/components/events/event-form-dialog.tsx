import { useForm } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
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
import { formatTime, toDate, toDateInput } from '@/lib/date';
import { store, update } from '@/routes/events';
import type { RouteDefinition } from '@/wayfinder';
import type { CompanyRef, EventItem, EventType, MailEventDraft } from '@/types';

/** Prefill for a new event, e.g. dates OpenAI found in a mail. */
export type EventDraft = MailEventDraft & { company_id?: number | null; memo?: string | null };

type FormData = {
    company_id: string;
    type: EventType;
    title: string;
    all_day: boolean;
    start_date: string;
    start_time: string;
    end_date: string;
    end_time: string;
    location: string;
    online_url: string;
    memo: string;
    sync_google: boolean;
};

const TITLE_SUGGESTIONS: Record<EventType, string> = {
    deadline: 'ES締切',
    intern: 'インターンシップ',
    interview: '一次面接',
    seminar: '会社説明会',
    test: 'Webテスト受検期限',
    other: '',
};

function initialData(
    event: EventItem | null | undefined,
    defaults: { companyId?: number | null; date?: string; googleConnected: boolean; draft?: EventDraft | null },
): FormData {
    if (event) {
        const start = toDate(event.starts_at);
        const end = event.ends_at ? toDate(event.ends_at) : null;

        return {
            company_id: event.company_id ? String(event.company_id) : '',
            type: event.type,
            title: event.title,
            all_day: event.all_day,
            start_date: toDateInput(start),
            start_time: event.all_day ? '' : formatTime(event.starts_at),
            end_date: end ? toDateInput(end) : '',
            end_time: end && !event.all_day ? formatTime(event.ends_at!) : '',
            location: event.location ?? '',
            online_url: event.online_url ?? '',
            memo: event.memo ?? '',
            sync_google: event.sync_google,
        };
    }

    const draft = defaults.draft;

    if (draft) {
        return {
            company_id: draft.company_id ? String(draft.company_id) : '',
            type: draft.type,
            title: draft.title,
            all_day: !draft.start_time,
            start_date: draft.date,
            start_time: draft.start_time ?? '',
            end_date: draft.end_date ?? '',
            end_time: draft.end_time ?? '',
            location: draft.location ?? '',
            online_url: draft.online_url ?? '',
            memo: draft.memo ?? '',
            sync_google: defaults.googleConnected,
        };
    }

    return {
        company_id: defaults.companyId ? String(defaults.companyId) : '',
        type: 'interview',
        title: '',
        all_day: false,
        start_date: defaults.date ?? toDateInput(new Date()),
        start_time: '10:00',
        end_date: '',
        end_time: '',
        location: '',
        online_url: '',
        memo: '',
        sync_google: defaults.googleConnected,
    };
}

export function EventFormDialog({
    open,
    onOpenChange,
    event,
    companies,
    defaultCompanyId,
    defaultDate,
    googleConnected,
    draft,
    action,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    event?: EventItem | null;
    companies: CompanyRef[];
    defaultCompanyId?: number | null;
    defaultDate?: string;
    googleConnected: boolean;
    draft?: EventDraft | null;
    /** Where a new event is posted (defaults to events.store). */
    action?: RouteDefinition<'post'>;
}) {
    const { eventTypes } = useEnums();
    const form = useForm<FormData>(
        initialData(event, { companyId: defaultCompanyId, date: defaultDate, googleConnected, draft }),
    );

    // Refill the form each time the dialog opens for a different event/day.
    useEffect(() => {
        if (open) {
            form.setData(
                initialData(event, { companyId: defaultCompanyId, date: defaultDate, googleConnected, draft }),
            );
            form.clearErrors();
        }
    }, [open, event?.id, defaultDate, defaultCompanyId, draft]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            company_id: data.company_id ? Number(data.company_id) : null,
            start_time: data.all_day ? null : data.start_time,
            end_time: data.all_day ? null : data.end_time || null,
            end_date: data.end_date || null,
        }));
        form.submit(event ? update(event.id) : (action ?? store()), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const setType = (type: EventType) => {
        const suggestions = Object.values(TITLE_SUGGESTIONS);
        form.setData((data) => ({
            ...data,
            type,
            // Only replace the title if it is empty or still a suggestion.
            title: data.title === '' || suggestions.includes(data.title) ? TITLE_SUGGESTIONS[type] : data.title,
        }));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{event ? '予定を編集' : '予定を追加'}</DialogTitle>
                    <DialogDescription>
                        締切・面接・説明会などを登録します。
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="種類" htmlFor="type" error={form.errors.type}>
                            <NativeSelect
                                id="type"
                                value={form.data.type}
                                onChange={(e) => setType(e.target.value as EventType)}
                            >
                                {eventTypes.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label="企業" htmlFor="company_id" error={form.errors.company_id}>
                            <NativeSelect
                                id="company_id"
                                value={form.data.company_id}
                                onChange={(e) => form.setData('company_id', e.target.value)}
                            >
                                <option value="">（なし）</option>
                                {companies.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                    </div>

                    <Field label="タイトル" htmlFor="title" error={form.errors.title}>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            placeholder="例：一次面接（オンライン）"
                            required
                        />
                    </Field>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="all_day"
                            checked={form.data.all_day}
                            onCheckedChange={(v) => form.setData('all_day', v === true)}
                        />
                        <Label htmlFor="all_day">終日（時刻なし）</Label>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <Field label="日付" htmlFor="start_date" error={form.errors.start_date}>
                            <Input
                                id="start_date"
                                type="date"
                                value={form.data.start_date}
                                onChange={(e) => form.setData('start_date', e.target.value)}
                                required
                            />
                        </Field>
                        {!form.data.all_day && (
                            <Field label="開始" htmlFor="start_time" error={form.errors.start_time}>
                                <Input
                                    id="start_time"
                                    type="time"
                                    value={form.data.start_time}
                                    onChange={(e) => form.setData('start_time', e.target.value)}
                                    required
                                />
                            </Field>
                        )}
                        <Field
                            label={form.data.all_day ? '終了日（複数日）' : '終了日'}
                            htmlFor="end_date"
                            error={form.errors.end_date}
                            hint="同じ日なら空欄"
                        >
                            <Input
                                id="end_date"
                                type="date"
                                value={form.data.end_date}
                                min={form.data.start_date}
                                onChange={(e) => form.setData('end_date', e.target.value)}
                            />
                        </Field>
                        {!form.data.all_day && (
                            <Field label="終了" htmlFor="end_time" error={form.errors.end_time}>
                                <Input
                                    id="end_time"
                                    type="time"
                                    value={form.data.end_time}
                                    onChange={(e) => form.setData('end_time', e.target.value)}
                                />
                            </Field>
                        )}
                    </div>

                    <Field label="参加URL（Zoom・Teamsなど）" htmlFor="online_url" error={form.errors.online_url}>
                        <Input
                            id="online_url"
                            type="url"
                            inputMode="url"
                            value={form.data.online_url}
                            onChange={(e) => form.setData('online_url', e.target.value)}
                            placeholder="https://"
                        />
                    </Field>

                    <Field label="場所" htmlFor="location" error={form.errors.location}>
                        <Input
                            id="location"
                            value={form.data.location}
                            onChange={(e) => form.setData('location', e.target.value)}
                            placeholder="例：大阪本社 3F"
                        />
                    </Field>

                    <Field label="メモ" htmlFor="memo" error={form.errors.memo}>
                        <Textarea
                            id="memo"
                            value={form.data.memo}
                            onChange={(e) => form.setData('memo', e.target.value)}
                            placeholder="持ち物・服装・聞かれそうなことなど"
                            rows={3}
                        />
                    </Field>

                    {googleConnected ? (
                        <div className="flex items-center gap-2 rounded-md border p-3">
                            <Checkbox
                                id="sync_google"
                                checked={form.data.sync_google}
                                onCheckedChange={(v) => form.setData('sync_google', v === true)}
                            />
                            <Label htmlFor="sync_google" className="flex items-center gap-1.5">
                                <CalendarCheck className="size-4 text-emerald-600" />
                                Googleカレンダーにも登録する
                            </Label>
                        </div>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            設定 → 連携 でGoogleカレンダーをつなぐと、ここから自動で登録できます。
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            キャンセル
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {event ? '保存' : '追加'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
