import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { CalendarCheck, CalendarX, Info, Link2, MessageCircle, Mail, RefreshCw, Send, Sparkles, Trash2 } from 'lucide-react';
import { Field } from '@/components/field';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDay, formatTime } from '@/lib/date';
import { sync as syncGmail } from '@/routes/gmail';
import { connect, disconnect } from '@/routes/google';
import { update as updateCalendar } from '@/routes/google/calendar';
import { index as inbox } from '@/routes/inbox';
import { edit } from '@/routes/integrations';
import { test as testLine, update as updateLine } from '@/routes/line';
import { destroy as destroyRule } from '@/routes/mail-rules';

type Props = {
    google: {
        configured: boolean;
        connected: boolean;
        email: string;
        calendarId: string;
        scopes: string[];
    };
    gmail: {
        connected: boolean;
        syncedAt: string | null;
        error: string | null;
        count: number;
    };
    ai: { configured: boolean; model: string };
    line: { configured: boolean; userId: string | null; linkedAt: string | null; webhookUrl: string; morningAt: string };
    mailRules: { id: number; description: string; example_subject: string | null; hidden: number }[];
};

export default function Integrations({ google, gmail, ai, line, mailRules }: Props) {
    const lineForm = useForm({ line_user_id: line.userId ?? '' });

    const saveLine = (e: FormEvent) => {
        e.preventDefault();
        lineForm.submit(updateLine(), { preserveScroll: true });
    };

    const removeRule = (id: number) => {
        if (confirm('このルールを削除しますか？このルールで除外していたメールは受信箱に戻ります。')) {
            router.delete(destroyRule(id).url, { preserveScroll: true });
        }
    };

    const calendarForm = useForm({ calendar_id: google.calendarId });

    const saveCalendar = (e: FormEvent) => {
        e.preventDefault();
        calendarForm.submit(updateCalendar(), { preserveScroll: true });
    };

    const remove = () => {
        if (confirm('Google連携を解除しますか？登録済みの予定はGoogleカレンダーに残ります。')) {
            router.delete(disconnect().url);
        }
    };

    return (
        <>
            <Head title="連携" />

            <div className="space-y-8">
                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Googleカレンダー"
                        description="アプリで登録した予定を、Googleカレンダーにも自動で書き込みます（アプリ → Google の一方向）。Gmailの読み取りも、この連携で許可します。"
                    />

                    {!google.configured ? (
                        <Alert>
                            <Info />
                            <AlertDescription>
                                Google OAuth が未設定です。.env に GOOGLE_CLIENT_ID と GOOGLE_CLIENT_SECRET を設定してください。
                            </AlertDescription>
                        </Alert>
                    ) : google.connected ? (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
                            <div className="flex items-center gap-3">
                                <CalendarCheck className="size-6 text-emerald-600" />
                                <div>
                                    <p className="text-sm font-medium">連携中</p>
                                    <p className="text-xs text-muted-foreground">
                                        {google.email}（カレンダー：{google.calendarId === 'primary' ? 'メイン' : google.calendarId}）
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <Button asChild variant="outline" size="sm">
                                    <a href={connect().url}>再連携</a>
                                </Button>
                                <Button variant="ghost" size="sm" onClick={remove}>
                                    <CalendarX /> 解除
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-dashed p-4">
                            <p className="text-sm text-muted-foreground">
                                まだ連携していません。ログイン中のアカウント（{google.email}）で許可してください。
                            </p>
                            <Button asChild>
                                <a href={connect().url}>
                                    <Link2 /> Googleカレンダーと連携
                                </a>
                            </Button>
                        </div>
                    )}

                    <form onSubmit={saveCalendar} className="grid gap-3 rounded-lg border p-4">
                        <Field
                            label="書き込み先のカレンダーID"
                            htmlFor="calendar_id"
                            error={calendarForm.errors.calendar_id}
                            hint={
                                <>
                                    メインのカレンダーは「primary」。専用カレンダーを使うときは、Googleカレンダーの「設定と共有」→「カレンダーの統合」にある
                                    カレンダーID（xxxx@group.calendar.google.com）を貼り付けてください。変更すると、今後の予定を新しいカレンダーに登録し直します。
                                </>
                            }
                        >
                            <Input
                                id="calendar_id"
                                value={calendarForm.data.calendar_id}
                                onChange={(e) => calendarForm.setData('calendar_id', e.target.value)}
                                placeholder="primary"
                                autoComplete="off"
                                spellCheck={false}
                            />
                        </Field>
                        <div>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={calendarForm.processing || calendarForm.data.calendar_id.trim() === google.calendarId}
                            >
                                保存
                            </Button>
                        </div>
                    </form>
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Gmail取り込み"
                        description="Gmailのメール（迷惑メール・ゴミ箱・送信済みを除く）を10分ごとに取り込み、AIが内容を読んで仕分けます。"
                    />

                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
                        <div className="flex items-center gap-3">
                            <Mail className={gmail.connected ? 'size-6 text-emerald-600' : 'size-6 text-muted-foreground'} />
                            <div>
                                <p className="text-sm font-medium">{gmail.connected ? '取り込み中（読み取りのみ）' : '未許可'}</p>
                                <p className="text-xs text-muted-foreground">
                                    {gmail.connected
                                        ? `取り込み済み ${gmail.count}件${gmail.syncedAt ? `・最終 ${formatDay(gmail.syncedAt)} ${formatTime(gmail.syncedAt)}` : ''}`
                                        : '上の「再連携」を押して、Gmailの読み取りを許可してください。'}
                                </p>
                            </div>
                        </div>
                        {gmail.connected && (
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" onClick={() => router.post(syncGmail().url, {}, { preserveScroll: true })}>
                                    <RefreshCw /> 今すぐ取り込む
                                </Button>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={inbox()}>受信箱を開く</Link>
                                </Button>
                            </div>
                        )}
                    </div>

                    {gmail.error && (
                        <Alert variant="destructive">
                            <Info />
                            <AlertDescription>{gmail.error}</AlertDescription>
                        </Alert>
                    )}
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="AIによるメールの仕分け"
                        description="受信箱で「次から表示しない」を押すと、AIがそのメールから除外ルールを作り、次から似た内容のメールを「自動で除外」に振り分けます。面接の日程・選考結果・締切の連絡は除外しません。"
                    />

                    <div className="flex items-center gap-3 rounded-lg border p-4">
                        <Sparkles className={ai.configured ? 'size-6 text-emerald-600' : 'size-6 text-muted-foreground'} />
                        <div>
                            <p className="text-sm font-medium">{ai.configured ? `OpenAI（${ai.model}）で判定中` : 'OpenAIのAPIキーが未設定'}</p>
                            <p className="text-xs text-muted-foreground">
                                {ai.configured ? 'モデルは .env の OPENAI_MODEL で変えられます。' : '.env の SHUKATSU_OPENAI_API_KEY に設定すると、AIの判定が始まります。'}
                            </p>
                        </div>
                    </div>

                    {mailRules.length ? (
                        <ul className="space-y-2">
                            {mailRules.map((rule) => (
                                <li key={rule.id} className="flex items-start justify-between gap-3 rounded-lg border p-3">
                                    <div className="min-w-0">
                                        <p className="text-sm">{rule.description}</p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {rule.example_subject && `元のメール：${rule.example_subject}・`}除外中 {rule.hidden}件
                                        </p>
                                    </div>
                                    <Button variant="ghost" size="sm" onClick={() => removeRule(rule.id)}>
                                        <Trash2 /> 削除
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">まだ除外ルールはありません。</p>
                    )}
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="LINE通知"
                        description={`毎朝${line.morningAt}に今日の予定・近づいている予定・受信箱の件数をまとめて送り、大事なメール（参加確定・日程変更・キャンセル・選考結果）が届いたら「✅追加 / ✏️修正 / ❌無視」のボタン付きで知らせます。`}
                    />

                    {!line.configured && (
                        <Alert>
                            <MessageCircle />
                            <AlertDescription>
                                <p>LINEのチャネルが未設定です。.env の LINE_CHANNEL_SECRET と LINE_CHANNEL_ACCESS_TOKEN を設定してください。</p>
                            </AlertDescription>
                        </Alert>
                    )}

                    <form onSubmit={saveLine} className="grid gap-3 rounded-lg border p-4">
                        <Field
                            label="送り先のLINEユーザーID"
                            htmlFor="line_user_id"
                            error={lineForm.errors.line_user_id}
                            hint="LINE Developers の「チャネル基本設定」の一番下にある「あなたのユーザーID」（Uで始まる33文字）。保存した後に届いたメールから通知します。"
                        >
                            <Input
                                id="line_user_id"
                                value={lineForm.data.line_user_id}
                                onChange={(e) => lineForm.setData('line_user_id', e.target.value.trim())}
                                placeholder="U0123456789abcdef0123456789abcdef"
                                autoComplete="off"
                                spellCheck={false}
                            />
                        </Field>
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" size="sm" disabled={lineForm.processing || lineForm.data.line_user_id === (line.userId ?? '')}>
                                保存
                            </Button>
                            {line.configured && line.userId && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => router.post(testLine().url, {}, { preserveScroll: true })}
                                >
                                    <Send /> テスト送信
                                </Button>
                            )}
                        </div>
                    </form>

                    <p className="text-xs text-muted-foreground">
                        ボタンを押したときの処理には、LINE Developers の「Messaging API設定」で Webhook URL を
                        <code className="mx-1 rounded bg-muted px-1 font-mono">{line.webhookUrl}</code>
                        に設定して「Webhookの利用」をオンにする必要があります（公開サーバーでのみ動きます）。
                    </p>
                </section>
            </div>
        </>
    );
}

Integrations.layout = {
    breadcrumbs: [{ title: '連携', href: edit() }],
};
