import { Link } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
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
import attendanceRoutes from '@/routes/attendance';
import dailyScrum from '@/routes/daily-scrum';
import epics from '@/routes/epics';
import issues from '@/routes/issues';
import milestones from '@/routes/milestones';
import retrospectives from '@/routes/retrospectives';
import { general } from '@/routes/settings';
import sprints from '@/routes/sprints';
import workLogs from '@/routes/work-logs';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'ダッシュボード',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'エピック（案件）',
        href: epics.index(),
        icon: Layers,
        sectionLabel: '開発',
    },
    {
        title: 'ストーリー・タスク',
        href: issues.index().url,
        icon: ListChecks,
    },
    {
        title: 'マイルストーン',
        href: milestones.index().url,
        icon: Flag,
        sectionLabel: 'スケジュール管理',
    },
    {
        title: 'スプリント',
        href: sprints.index(),
        icon: ClipboardList,
    },
    {
        title: 'レトロスペクティブ',
        href: retrospectives.index(),
        icon: RotateCcw,
    },
    {
        title: 'デイリースクラム',
        href: dailyScrum.index().url,
        icon: CalendarDays,
        sectionLabel: '記録',
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
