import { Link } from '@inertiajs/react';
import {
    BookOpen,
    ClipboardList,
    FolderGit2,
    LayoutGrid,
    Layers,
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
import sprints from '@/routes/sprints';
import epics from '@/routes/epics';
import retrospectives from '@/routes/retrospectives';
import settings from '@/routes/settings';
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
        title: 'エピック（案件）',
        href: epics.index(),
        icon: Layers,
    },
    {
        title: 'レトロスペクティブ',
        href: retrospectives.index(),
        icon: RotateCcw,
    },
    {
        title: '設定',
        href: settings.repositories(),
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
