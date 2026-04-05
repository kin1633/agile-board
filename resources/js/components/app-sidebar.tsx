import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Clock,
    ClipboardList,
    CalendarCheck,
    Flag,
    FolderGit2,
    LayoutGrid,
    Layers,
    ListChecks,
    Settings,
    RotateCcw,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import milestones from '@/routes/milestones';
import sprints from '@/routes/sprints';
import epics from '@/routes/epics';
import issues from '@/routes/issues';
import retrospectives from '@/routes/retrospectives';
import workLogs from '@/routes/work-logs';
import attendanceRoutes from '@/routes/attendance';
import { general } from '@/routes/settings';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'ダッシュボード',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'スプリント',
        href: sprints.index(),
        icon: ClipboardList,
    },
    {
        title: 'マイルストーン',
        href: milestones.index().url,
        icon: Flag,
    },
    {
        title: 'エピック（案件）',
        href: epics.index(),
        icon: Layers,
    },
    {
        title: 'ストーリー・タスク',
        href: issues.index().url,
        icon: ListChecks,
    },
    {
        title: 'レトロスペクティブ',
        href: retrospectives.index(),
        icon: RotateCcw,
    },
    {
        title: '実績入力',
        href: workLogs.index().url,
        icon: Clock,
    },
    {
        title: '勤怠管理',
        href: attendanceRoutes.index().url,
        icon: CalendarCheck,
    },
    {
        title: '設定',
        href: general(),
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/kin1633/agile-board/tree/main',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://github.com/kin1633/agile-board/tree/main/docs',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
