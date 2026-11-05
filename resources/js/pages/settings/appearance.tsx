import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="表示" />

            <div className="space-y-6">
                <Heading variant="small" title="表示テーマ" description="ライト・ダーク・端末の設定に合わせる、から選べます。" />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [{ title: '表示', href: editAppearance() }],
};
