import { KeyRound } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

/**
 * One click to log in: copies the login ID, then opens the mypage. The
 * password is never stored in the app; the browser's password manager
 * fills it in.
 */
export function MypageLoginButton({
    url,
    loginId,
    size = 'sm',
    className,
}: {
    url: string | null;
    loginId: string | null;
    size?: 'sm' | 'icon';
    className?: string;
}) {
    const [, copy] = useClipboard();

    if (!url) {
        return null;
    }

    const login = async () => {
        if (loginId) {
            // Copy before opening: the new tab takes focus, and the clipboard
            // only accepts writes from the focused page.
            (await copy(loginId))
                ? toast.success('ログインIDをコピーしました。貼り付けてログインしてください')
                : toast.error('ログインIDをコピーできませんでした');
        }

        window.open(url, '_blank', 'noopener,noreferrer');
    };

    return (
        <Button
            type="button"
            size={size}
            variant="outline"
            className={cn(className)}
            onClick={login}
            title={loginId ? `ID「${loginId}」をコピーしてマイページを開きます` : 'マイページを開きます'}
        >
            <KeyRound /> {size === 'sm' && 'ログイン'}
        </Button>
    );
}
