import { Head, router } from '@inertiajs/react';
import { AlertCircle, Wrench } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { devLogin } from '@/routes';
import { redirect } from '@/routes/auth/google';

type Props = {
    googleConfigured: boolean;
    devLogin: boolean;
    error?: string | null;
};

function GoogleIcon() {
    return (
        <svg viewBox="0 0 48 48" className="size-5" aria-hidden>
            <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z" />
            <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z" />
            <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z" />
            <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z" />
        </svg>
    );
}

export default function Login({ googleConfigured, devLogin: devLoginEnabled, error }: Props) {
    return (
        <>
            <Head title="ログイン" />

            <div className="grid gap-4">
                {error && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {googleConfigured ? (
                    // A full page navigation: Google's consent screen cannot load inside an XHR.
                    <Button asChild size="lg" variant="outline" className="w-full">
                        <a href={redirect().url}>
                            <GoogleIcon />
                            Googleでログイン
                        </a>
                    </Button>
                ) : (
                    <Alert>
                        <AlertCircle />
                        <AlertDescription>
                            Googleログインが未設定です。.env の GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET を設定してください。
                        </AlertDescription>
                    </Alert>
                )}

                {devLoginEnabled && (
                    <Button
                        variant="secondary"
                        className="w-full"
                        onClick={() => router.post(devLogin().url)}
                    >
                        <Wrench />
                        開発用ログイン（ローカル環境のみ）
                    </Button>
                )}

                <p className="text-center text-xs text-muted-foreground">
                    登録済みのGoogleアカウントだけがログインできます。
                </p>
            </div>
        </>
    );
}

Login.layout = {
    title: '就活マネージャー',
    description: '選考・予定・ESをひとつの場所で管理',
};
