import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** サブメニュー項目。指定するとアコーディオン展開になる */
    children?: Pick<NavItem, 'title' | 'href'>[];
    /** 指定するとこの項目の前にセクション見出しを表示する */
    sectionLabel?: string;
};
