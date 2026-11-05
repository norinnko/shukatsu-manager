import { Building2, CalendarDays, FileText, Inbox, LayoutGrid, UserRound } from 'lucide-react';
import { dashboard } from '@/routes';
import { index as calendar } from '@/routes/calendar';
import { index as companies } from '@/routes/companies';
import { index as es } from '@/routes/es';
import { index as inbox } from '@/routes/inbox';
import { show as profile } from '@/routes/profile';
import type { NavItem } from '@/types';

/** Main navigation, shared by the sidebar and the mobile tab bar. */
export const mainNavItems: NavItem[] = [
    { title: 'ホーム', href: dashboard(), icon: LayoutGrid },
    { title: '企業', href: companies(), icon: Building2 },
    { title: 'カレンダー', href: calendar(), icon: CalendarDays },
    { title: '受信箱', href: inbox(), icon: Inbox },
    { title: 'ES回答', href: es(), icon: FileText },
    { title: '基本情報', href: profile(), icon: UserRound },
];
