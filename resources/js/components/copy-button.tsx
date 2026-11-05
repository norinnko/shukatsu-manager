import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

export function CopyButton({
    value,
    label,
    className,
    size = 'icon',
}: {
    value: string | null | undefined;
    label?: string;
    className?: string;
    size?: 'icon' | 'sm';
}) {
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);

    if (!value) {
        return null;
    }

    const onClick = async () => {
        if (await copy(value)) {
            setCopied(true);
            toast.success(label ? `${label}をコピーしました` : 'コピーしました');
            setTimeout(() => setCopied(false), 1500);
        } else {
            toast.error('コピーできませんでした');
        }
    };

    const Icon = copied ? Check : Copy;

    return (
        <Button
            type="button"
            variant="ghost"
            size={size}
            onClick={onClick}
            className={cn(size === 'icon' && 'size-8', className)}
            aria-label={label ? `${label}をコピー` : 'コピー'}
        >
            <Icon className={cn('size-4', copied && 'text-emerald-600')} />
            {size === 'sm' && <span>{copied ? 'コピー済' : 'コピー'}</span>}
        </Button>
    );
}
