import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

/** Href for nav links (Wayfinder route helpers return route definitions compatible with Inertia Link). */
export type NavHref = NonNullable<InertiaLinkProps['href']>;

export type NavLeaf = {
    title: string;
    /** Wayfinder helper, e.g. () => employees() */
    href: () => NavHref;
    /** Hide unless HR admin nav is enabled (shared Inertia prop). */
    adminOnly?: boolean;
    /** Hide unless user has this shared capability key. */
    requiredPermission?:
        | 'canAddEmployee'
        | 'canViewWorkSchedules'
        | 'canManageScheduleAssignments'
        | 'canEditOrganizationStructure'
        | 'canViewEmployeeTeamLeaveOvertime';
};

export type NavTreeLink = {
    kind: 'link';
    title: string;
    icon: LucideIcon;
    href: () => NavHref;
    /** Hide unless user has this shared capability key. */
    requiredPermission?:
        | 'canAddEmployee'
        | 'canViewWorkSchedules'
        | 'canManageScheduleAssignments'
        | 'canEditOrganizationStructure'
        | 'canViewEmployeeTeamLeaveOvertime';
};

export type NavTreeGroup = {
    kind: 'group';
    label: string;
};

export type NavTreeCollapsible = {
    kind: 'collapsible';
    title: string;
    icon: LucideIcon;
    items: NavLeaf[];
    /** When true, entire block (all items) is admin-only. */
    adminOnly?: boolean;
};

export type NavTreeEntry = NavTreeLink | NavTreeGroup | NavTreeCollapsible;
