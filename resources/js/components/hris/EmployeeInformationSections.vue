<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookUser,
    Building2,
    CalendarRange,
    Clock,
    Home,
    IdCard,
    Info,
    ExternalLink,
    Lock,
    MapPin,
    Pencil,
    Phone,
    User,
    UserCircle,
    UserX,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AboutMeAddressesEditDialog from '@/components/hris/AboutMeAddressesEditDialog.vue';
import AboutMeEmergencyContactsEditDialog from '@/components/hris/AboutMeEmergencyContactsEditDialog.vue';
import AboutMeIdentityEditDialog from '@/components/hris/AboutMeIdentityEditDialog.vue';
import AboutMePersonalContactsEditDialog from '@/components/hris/AboutMePersonalContactsEditDialog.vue';
import AboutMeProfileEditDialog from '@/components/hris/AboutMeProfileEditDialog.vue';
import AboutMeWorkAffiliationsDialog from '@/components/hris/AboutMeWorkAffiliationsDialog.vue';
import AboutMeWorkPositionsDialog from '@/components/hris/AboutMeWorkPositionsDialog.vue';
import EmployeeInformationSectionCard from '@/components/hris/EmployeeInformationSectionCard.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { AboutMeWorkPayload } from '@/pages/Employees/aboutMeWorkTypes';
import AdjustEmploymentDatesDialog from '@/pages/Employees/AdjustEmploymentDatesDialog.vue';
import type { EmployeeProfileDisplay } from '@/pages/Employees/employeeProfileDisplay';
import type {
    EmploymentHistoryDialogMode,
    EmploymentHistoryRow,
} from '@/pages/Employees/employmentHistoryTypes';
import { organizationChart } from '@/routes';
import { employeeSchedules } from '@/routes/attendance';

const props = withDefaults(
    defineProps<{
        variant: 'self' | 'hr';
        profile: EmployeeProfileDisplay;
        /** Present on About Me for active current-employment edits (hire dates, catalog positions, affiliations). */
        aboutMeWork?: AboutMeWorkPayload | null;
        /** HR staff (Super Admin / HR Head / scoped HR Manager) may open About Me edits; plain employees view only. */
        canEditAboutMeHris?: boolean;
    }>(),
    {
        aboutMeWork: null,
        canEditAboutMeHris: false,
    },
);

const isSelf = computed(() => props.variant === 'self');

const canEditAboutMe = computed(() => props.canEditAboutMeHris === true);

const aboutMeProfileEditOpen = ref(false);
const identityEditOpen = ref(false);
const personalContactsEditOpen = ref(false);
const emergencyContactsEditOpen = ref(false);
const addressesEditOpen = ref(false);
const page = usePage();
const aboutMeAdjustEmploymentOpen = ref(false);
const aboutMeEmploymentDialogMode =
    ref<EmploymentHistoryDialogMode>('adjust_dates');
const aboutMeWorkPositionsOpen = ref(false);
const aboutMeWorkAffiliationsOpen = ref(false);

/** Employment / positions / affiliations dialogs: HR or self viewer with composer payload + access. */
const allowAboutMeWorkEditor = computed((): boolean => {
    return (
        props.canEditAboutMeHris === true &&
        props.aboutMeWork !== null &&
        props.aboutMeWork !== undefined
    );
});

const canRecordEmploymentSeparation = computed((): boolean =>
    Boolean(
        (
            page.props as {
                can?: { canRecordEmploymentSeparation?: boolean };
            }
        ).can?.canRecordEmploymentSeparation,
    ),
);

/** HR viewer on employee Show: separation entry matches Employees index / employment history menus. */
const showHrRecordSeparationEntry = computed(
    (): boolean =>
        !isSelf.value &&
        allowAboutMeWorkEditor.value &&
        canRecordEmploymentSeparation.value,
);

function openAboutMeAdjustDatesDialog(): void {
    aboutMeEmploymentDialogMode.value = 'adjust_dates';
    aboutMeAdjustEmploymentOpen.value = true;
}

function openAboutMeRecordSeparationDialog(): void {
    aboutMeEmploymentDialogMode.value = 'record_separation';
    aboutMeAdjustEmploymentOpen.value = true;
}

const selfAboutMeEmploymentRow = computed((): EmploymentHistoryRow | null => {
    const w = props.aboutMeWork;
    if (
        !w ||
        typeof w.employment_id !== 'number' ||
        typeof w.employee_id !== 'number'
    ) {
        return null;
    }

    return {
        id: w.employment_id,
        hire_date: w.hire_date,
        hire_adjustment_max_date: w.hire_adjustment_max_date ?? null,
        separation_date: null,
        employment_status: 'active',
        separation_reason: null,
        notes: null,
        tenure_days: 0,
        employee: {
            id: w.employee_id,
            display_name: props.profile.display_name,
            id_number: props.profile.id_number,
            avatar_url: props.profile.avatar_url,
            is_org_wide:
                props.profile.org_scope_label.includes('Organization-wide'),
        },
    };
});

const employmentEditSelfIcon = computed(() =>
    allowAboutMeWorkEditor.value ? Pencil : Lock,
);

const profileInitials = computed((): string => {
    const name = props.profile.display_name.trim();
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return 'E';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 1).toUpperCase();
    }

    return `${parts[0].slice(0, 1)}${parts[parts.length - 1].slice(0, 1)}`.toUpperCase();
});

/** Same separator as PHP `implode(' · ', $labelPieces)` (U+00B7 middle dot, optional spaces). */
const scheduleLabelPartSeparator = /\s*[\u00B7]\s*/u;

const scheduleParts = computed(() =>
    props.profile.schedule_label
        .split(scheduleLabelPartSeparator)
        .map((part) => part.trim())
        .filter((part) => part !== ''),
);

const scheduleName = computed((): string => {
    return scheduleParts.value[0] ?? props.profile.schedule_label;
});

const scheduleTimeRange = computed((): string => {
    if (scheduleParts.value.length <= 1) {
        return '';
    }

    return scheduleParts.value[scheduleParts.value.length - 1] ?? '';
});

/**
 * Working days comma list from PHP (`normalizeScheduleDayTokens` + sorted); avoids misleading Mon–Sun chips.
 */
const scheduleWorkingDaysDisplay = computed((): string => {
    const raw = (props.profile.schedule_working_days ?? '').trim();

    return raw !== '' ? raw : '—';
});

const affiliationCode = computed((): string => {
    return (
        props.profile.affiliation_history[0]?.code ??
        props.profile.org_scope_label ??
        '—'
    );
});

const statusBadgeClass = computed((): string => {
    const normalizedStatus = props.profile.status_label.trim().toLowerCase();
    if (normalizedStatus === 'active') {
        return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    }
    if (
        normalizedStatus === 'resigned' ||
        normalizedStatus === 'contract ended'
    ) {
        return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
    }
    if (normalizedStatus === 'terminated') {
        return 'bg-rose-500/15 text-rose-700 dark:text-rose-300';
    }

    return 'bg-muted text-foreground';
});

function quickStatIcon(label: string) {
    const normalizedLabel = label.trim().toLowerCase();
    if (normalizedLabel === 'hire date') {
        return CalendarRange;
    }
    if (normalizedLabel === 'tenure') {
        return Clock;
    }
    if (normalizedLabel === 'primary position') {
        return User;
    }
    if (normalizedLabel === 'primary unit') {
        return Building2;
    }

    return Info;
}
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <div class="flex flex-col gap-6">
            <!-- Hero -->
            <div
                class="relative flex flex-col gap-6 overflow-hidden rounded-2xl border border-border/80 bg-card p-6 shadow-sm"
            >
                <div
                    class="pointer-events-none absolute top-1/2 -left-16 size-48 -translate-y-1/2 rounded-full bg-primary/10 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-primary/10 via-primary/40 to-primary/10"
                    aria-hidden="true"
                />
                <div class="flex items-start justify-between gap-3">
                    <h1
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        About Me
                    </h1>
                    <div
                        class="flex shrink-0 flex-wrap items-center justify-end gap-2"
                    >
                        <Tooltip v-if="isSelf && canEditAboutMe">
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 rounded-md"
                                    aria-label="Edit name, IDs, and profile photo"
                                    @click="aboutMeProfileEditOpen = true"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="left">
                                Edit profile photo and directory details
                            </TooltipContent>
                        </Tooltip>
                        <template v-if="!isSelf">
                            <Tooltip v-if="canEditAboutMe">
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="rounded-lg"
                                        aria-label="Edit name, IDs, and profile photo"
                                        @click="aboutMeProfileEditOpen = true"
                                    >
                                        Edit
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="left">
                                    Edit profile photo and directory details
                                </TooltipContent>
                            </Tooltip>
                            <Tooltip v-if="showHrRecordSeparationEntry">
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        class="rounded-lg"
                                        @click="
                                            openAboutMeRecordSeparationDialog
                                        "
                                    >
                                        End employment
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="left">
                                    Record separation — same flow as Employment
                                    History (password + placement checks).
                                </TooltipContent>
                            </Tooltip>
                        </template>
                    </div>
                </div>

                <div
                    class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_1px_minmax(0,1.2fr)]"
                >
                    <div class="flex min-w-0 items-center gap-5">
                        <div class="relative shrink-0">
                            <Avatar
                                class="size-24 border-2 border-border/80 bg-muted/50 shadow-sm ring-2 ring-background sm:size-28"
                            >
                                <AvatarImage
                                    v-if="profile.avatar_url"
                                    :src="profile.avatar_url"
                                    :alt="profile.display_name"
                                />
                                <AvatarFallback
                                    class="text-xl font-semibold text-muted-foreground sm:text-2xl"
                                >
                                    {{ profileInitials }}
                                </AvatarFallback>
                            </Avatar>
                        </div>
                        <div class="flex min-w-0 flex-col justify-center gap-2">
                            <h2
                                class="text-xl font-semibold tracking-tight text-pretty text-foreground sm:text-2xl"
                            >
                                {{ profile.display_name }}
                            </h2>
                            <div class="flex flex-col items-start gap-2">
                                <Badge
                                    variant="secondary"
                                    class="rounded-lg bg-muted/90 font-normal tabular-nums"
                                >
                                    <IdCard
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {{ profile.id_number }}
                                </Badge>
                                <Badge
                                    v-if="profile.attendance_id"
                                    variant="outline"
                                    class="rounded-lg bg-background/70 font-normal tabular-nums"
                                >
                                    <Clock
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {{ profile.attendance_id }}
                                </Badge>
                                <Badge
                                    class="rounded-lg font-normal"
                                    :class="statusBadgeClass"
                                >
                                    {{ profile.status_label }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <Separator
                        orientation="vertical"
                        class="hidden h-full bg-border/80 lg:block"
                    />

                    <div
                        class="relative grid w-full gap-4 rounded-xl border border-border/60 bg-card/70 p-4 text-sm shadow-sm md:grid-cols-2 md:gap-0"
                    >
                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-primary/10 via-primary/30 to-primary/10"
                            aria-hidden="true"
                        />
                        <div
                            class="pointer-events-none absolute inset-y-0 left-1/2 hidden w-px -translate-x-1/2 bg-border/80 md:block"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 space-y-1.5 md:pr-4">
                            <div class="flex min-h-7 items-center gap-1.5">
                                <span
                                    class="inline-grid size-7 place-items-center rounded-full bg-primary/10 text-primary"
                                >
                                    <Building2
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <p
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    Affiliation
                                </p>
                            </div>
                            <p class="text-base font-semibold text-foreground">
                                {{ profile.branch_label }}
                            </p>
                            <p
                                class="font-mono text-xs text-muted-foreground tabular-nums"
                            >
                                {{ affiliationCode }}
                            </p>
                        </div>
                        <div class="min-w-0 space-y-1.5 md:pl-4">
                            <div class="flex min-h-7 items-center gap-1.5">
                                <span
                                    class="inline-grid size-7 place-items-center rounded-full bg-primary/10 text-primary"
                                >
                                    <CalendarRange
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <p
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    Work schedule
                                </p>
                            </div>
                            <p class="text-base font-semibold text-foreground">
                                {{ scheduleName }}
                            </p>
                            <p
                                v-if="scheduleTimeRange !== ''"
                                class="font-mono text-sm text-foreground tabular-nums"
                            >
                                {{ scheduleTimeRange }}
                            </p>
                            <p
                                class="pt-0.5 text-xs leading-relaxed text-muted-foreground"
                            >
                                {{ scheduleWorkingDaysDisplay }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Quick stats -->
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="(tile, idx) in profile.stats"
                        :key="idx"
                        class="relative rounded-xl border border-border/60 bg-card/70 px-4 py-3 shadow-sm transition-colors hover:bg-card"
                    >
                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-primary/10 via-primary/30 to-primary/10"
                            aria-hidden="true"
                        />
                        <p
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground"
                        >
                            <span
                                class="inline-grid size-6 place-items-center rounded-md bg-muted/60 text-muted-foreground"
                            >
                                <component
                                    :is="quickStatIcon(tile.label)"
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                            </span>
                            <span>{{ tile.label }}</span>
                        </p>
                        <p
                            class="mt-1.5 text-lg font-semibold tracking-tight text-foreground tabular-nums"
                        >
                            {{ tile.value }}
                        </p>
                        <p
                            v-if="tile.hint"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{ tile.hint }}
                        </p>
                    </div>
                </div>
            </div>

            <Tabs default-value="profile" class="gap-5">
                <section
                    aria-label="Profile sections"
                    class="border-b border-border/70 px-2 sm:px-3"
                >
                    <TabsList
                        class="no-scrollbar flex h-auto w-full flex-nowrap justify-start gap-4 overflow-x-auto rounded-none bg-transparent px-1 py-0 text-foreground shadow-none sm:px-2"
                    >
                        <TabsTrigger
                            value="profile"
                            class="relative w-auto! flex-none! cursor-pointer justify-start! rounded-none border-0 border-b-2 border-transparent bg-transparent px-2 py-2 text-sm font-medium text-muted-foreground shadow-none transition-colors hover:text-foreground data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none data-[state=active]:after:absolute data-[state=active]:after:-bottom-0.5 data-[state=active]:after:left-1/2 data-[state=active]:after:h-0.5 data-[state=active]:after:w-4/5 data-[state=active]:after:-translate-x-1/2 data-[state=active]:after:rounded-full data-[state=active]:after:bg-primary/60 data-[state=active]:after:blur-[1px] data-[state=active]:after:content-[''] sm:text-base"
                        >
                            Profile
                        </TabsTrigger>
                        <TabsTrigger
                            value="work"
                            class="relative w-auto! flex-none! cursor-pointer justify-start! rounded-none border-0 border-b-2 border-transparent bg-transparent px-2 py-2 text-sm font-medium text-muted-foreground shadow-none transition-colors hover:text-foreground data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none data-[state=active]:after:absolute data-[state=active]:after:-bottom-0.5 data-[state=active]:after:left-1/2 data-[state=active]:after:h-0.5 data-[state=active]:after:w-4/5 data-[state=active]:after:-translate-x-1/2 data-[state=active]:after:rounded-full data-[state=active]:after:bg-primary/60 data-[state=active]:after:blur-[1px] data-[state=active]:after:content-[''] sm:text-base"
                        >
                            Work
                        </TabsTrigger>
                        <TabsTrigger
                            value="comp-time"
                            class="relative w-auto! flex-none! cursor-pointer justify-start! rounded-none border-0 border-b-2 border-transparent bg-transparent px-2 py-2 text-sm font-medium text-muted-foreground shadow-none transition-colors hover:text-foreground data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none data-[state=active]:after:absolute data-[state=active]:after:-bottom-0.5 data-[state=active]:after:left-1/2 data-[state=active]:after:h-0.5 data-[state=active]:after:w-4/5 data-[state=active]:after:-translate-x-1/2 data-[state=active]:after:rounded-full data-[state=active]:after:bg-primary/60 data-[state=active]:after:blur-[1px] data-[state=active]:after:content-[''] sm:text-base"
                        >
                            Schedule
                        </TabsTrigger>
                    </TabsList>
                </section>

                <!-- Profile -->
                <TabsContent
                    value="profile"
                    class="mt-0 flex flex-col gap-6 outline-none"
                >
                    <!-- Demographics -->
                    <Card class="overflow-hidden border-border/70 shadow-sm">
                        <div
                            class="mx-4 mt-0 mb-0 rounded-lg border border-primary/30 bg-muted/30 p-4 sm:mx-6 dark:bg-muted/25"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div
                                    class="flex min-w-0 flex-row items-start gap-4"
                                >
                                    <span
                                        class="inline-flex shrink-0 rounded-full bg-primary/10 p-3 text-primary"
                                    >
                                        <UserCircle
                                            class="size-7"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div class="min-w-0 flex-1 space-y-0.5">
                                        <h3
                                            class="text-base font-semibold text-foreground"
                                        >
                                            Identity & demographics
                                        </h3>
                                        <p
                                            class="text-sm leading-relaxed text-muted-foreground"
                                        >
                                            Legal identity and civil status as
                                            kept on the employee record.
                                        </p>
                                    </div>
                                </div>
                                <Tooltip v-if="canEditAboutMe">
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="shrink-0 cursor-pointer border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                            :aria-label="
                                                isSelf
                                                    ? 'Edit demographics'
                                                    : 'Edit demographics (HR)'
                                            "
                                            @click="identityEditOpen = true"
                                        >
                                            <Pencil
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Edit identity fields in a dialog (local
                                        draft until APIs are wired).
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        </div>
                        <CardContent
                            class="grid gap-4 px-5 pt-4 pb-4 sm:px-6 sm:pb-6 lg:px-7"
                        >
                            <dl
                                class="grid grid-cols-1 gap-x-4 gap-y-5 md:grid-cols-2 lg:grid-cols-3"
                            >
                                <div
                                    class="grid gap-1.5 border-l-2 border-primary/20 py-1 pl-3"
                                >
                                    <dt
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Date of birth
                                    </dt>
                                    <dd
                                        class="text-sm font-medium text-foreground tabular-nums"
                                    >
                                        {{
                                            profile.demographics
                                                .birthdate_display
                                        }}
                                    </dd>
                                </div>
                                <div
                                    class="grid gap-1.5 border-l-2 border-primary/20 py-1 pl-3"
                                >
                                    <dt
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Sex
                                    </dt>
                                    <dd
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{ profile.demographics.sex }}
                                    </dd>
                                </div>
                                <div
                                    class="grid gap-1.5 border-l-2 border-primary/20 py-1 pl-3"
                                >
                                    <dt
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Civil status
                                    </dt>
                                    <dd
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{ profile.demographics.civil_status }}
                                    </dd>
                                </div>
                                <div
                                    class="grid gap-1.5 border-l-2 border-primary/20 py-1 pl-3"
                                >
                                    <dt
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Nationality
                                    </dt>
                                    <dd
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{ profile.demographics.nationality }}
                                    </dd>
                                </div>
                                <div
                                    class="grid gap-1.5 border-l-2 border-primary/20 py-1 pl-3"
                                >
                                    <dt
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Religion
                                    </dt>
                                    <dd
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{
                                            profile.demographics.religion ===
                                                'Other' &&
                                            profile.demographics.religion_other
                                                ? profile.demographics
                                                      .religion_other
                                                : (profile.demographics
                                                      .religion ?? '—')
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <!-- Contacts -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <Card class="border-border/70 shadow-sm">
                            <div
                                class="mx-4 mt-0 mb-0 rounded-lg border border-primary/30 bg-muted/30 p-4 sm:mx-6 dark:bg-muted/25"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 flex-row items-start gap-4"
                                    >
                                        <span
                                            class="inline-flex shrink-0 rounded-full bg-primary/10 p-3 text-primary"
                                        >
                                            <Phone
                                                class="size-6"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <h3
                                                class="text-base font-semibold text-foreground"
                                            >
                                                Contact numbers & email
                                            </h3>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                Personal channels from your HR
                                                file.
                                            </p>
                                        </div>
                                    </div>
                                    <Tooltip v-if="canEditAboutMe">
                                        <TooltipTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="shrink-0 cursor-pointer border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                                aria-label="Edit personal contacts"
                                                @click="
                                                    personalContactsEditOpen = true
                                                "
                                            >
                                                <Pencil
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Edit personal contacts in a dialog.
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                            </div>
                            <CardContent
                                class="space-y-3 px-5 pt-4 pb-4 sm:px-6 sm:pb-6 lg:px-7"
                            >
                                <div
                                    v-for="(
                                        c, idx
                                    ) in profile.personal_contacts"
                                    :key="`p-${idx}`"
                                    class="flex flex-col gap-2 rounded-lg border border-l-2 border-border/60 border-l-primary/20 bg-muted/10 px-4 py-3"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            {{ c.channel_label }}
                                        </span>
                                        <Badge
                                            v-if="c.is_primary"
                                            variant="secondary"
                                            class="text-[10px] font-normal uppercase"
                                        >
                                            Primary
                                        </Badge>
                                    </div>
                                    <div class="text-left text-sm">
                                        <p
                                            class="font-mono text-sm text-foreground tabular-nums"
                                        >
                                            {{ c.contact_number }}
                                        </p>
                                        <p
                                            v-if="c.email"
                                            class="text-xs break-all text-muted-foreground"
                                        >
                                            {{ c.email }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="border-border/70 shadow-sm">
                            <div
                                class="mx-4 mt-0 mb-0 rounded-lg border border-primary/30 bg-muted/30 p-4 sm:mx-6 dark:bg-muted/25"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 flex-row items-start gap-4"
                                    >
                                        <span
                                            class="inline-flex shrink-0 rounded-full bg-primary/10 p-3 text-primary"
                                        >
                                            <BookUser
                                                class="size-6"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <h3
                                                class="text-base font-semibold text-foreground"
                                            >
                                                Emergency contacts
                                            </h3>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                Used for duty-of-care and
                                                after-hours escalation.
                                            </p>
                                        </div>
                                    </div>
                                    <Tooltip v-if="canEditAboutMe">
                                        <TooltipTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="shrink-0 cursor-pointer border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                                aria-label="Edit emergency contacts"
                                                @click="
                                                    emergencyContactsEditOpen = true
                                                "
                                            >
                                                <Pencil
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Edit emergency contacts in a dialog.
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                            </div>
                            <CardContent
                                class="space-y-3 px-5 pt-4 pb-4 sm:px-6 sm:pb-6 lg:px-7"
                            >
                                <div
                                    v-for="(
                                        c, idx
                                    ) in profile.emergency_contacts"
                                    :key="`e-${idx}`"
                                    class="rounded-lg border border-l-2 border-border/60 border-l-primary/20 bg-muted/10 px-4 py-3"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            {{ c.contact_person }}
                                        </span>
                                        <Badge
                                            v-if="c.is_primary"
                                            variant="secondary"
                                            class="text-[10px] font-normal uppercase"
                                        >
                                            Primary
                                        </Badge>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <Badge
                                            variant="outline"
                                            class="text-[10px] font-normal"
                                        >
                                            {{ c.relationship }}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            class="text-[10px] font-normal"
                                        >
                                            {{ c.channel_label }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="mt-1 font-mono text-sm text-foreground tabular-nums"
                                    >
                                        {{ c.contact_number }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Addresses -->
                    <Card class="border-border/70 shadow-sm">
                        <div
                            class="mx-4 mt-0 mb-0 rounded-lg border border-primary/30 bg-muted/30 p-4 sm:mx-6 dark:bg-muted/25"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div
                                    class="flex min-w-0 flex-row items-start gap-4"
                                >
                                    <span
                                        class="inline-flex shrink-0 rounded-full bg-primary/10 p-3 text-primary"
                                    >
                                        <Home
                                            class="size-6"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3
                                            class="text-base font-semibold text-foreground"
                                        >
                                            Addresses
                                        </h3>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            Current residence and permanent
                                            address on file (PSGC-ready in the
                                            create flow).
                                        </p>
                                    </div>
                                </div>
                                <Tooltip v-if="canEditAboutMe">
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="shrink-0 cursor-pointer border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                            aria-label="Edit addresses"
                                            @click="addressesEditOpen = true"
                                        >
                                            <Pencil
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Address updates will be available here
                                        soon.
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        </div>
                        <CardContent
                            class="grid gap-6 px-5 pt-4 pb-4 sm:px-6 sm:pb-6 md:grid-cols-2 lg:px-7"
                        >
                            <div
                                v-if="profile.current_address"
                                class="flex flex-col gap-2 border-l-2 border-primary/20 py-1 pl-3"
                            >
                                <div class="flex items-center gap-2">
                                    <p
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Current
                                    </p>
                                    <Badge
                                        v-if="
                                            profile.current_address.is_primary
                                        "
                                        variant="secondary"
                                        class="text-[10px] font-normal uppercase"
                                    >
                                        Primary
                                    </Badge>
                                </div>
                                <address
                                    class="text-sm leading-relaxed text-foreground not-italic"
                                >
                                    <span
                                        v-for="(line, i) in profile
                                            .current_address.lines"
                                        :key="i"
                                        class="block"
                                    >
                                        {{ line }}
                                    </span>
                                </address>
                            </div>
                            <div
                                v-if="profile.permanent_address"
                                class="flex flex-col gap-2 border-l-2 border-primary/20 py-1 pl-3"
                            >
                                <div class="flex items-center gap-2">
                                    <p
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Permanent
                                    </p>
                                    <Badge
                                        v-if="
                                            profile.permanent_address.is_primary
                                        "
                                        variant="secondary"
                                        class="text-[10px] font-normal uppercase"
                                    >
                                        Primary
                                    </Badge>
                                </div>
                                <address
                                    class="text-sm leading-relaxed text-foreground not-italic"
                                >
                                    <span
                                        v-for="(line, i) in profile
                                            .permanent_address.lines"
                                        :key="i"
                                        class="block"
                                    >
                                        {{ line }}
                                    </span>
                                </address>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Work -->
                <TabsContent value="work" class="mt-0 outline-none">
                    <EmployeeInformationSectionCard
                        :icon="CalendarRange"
                        iconClass="size-7"
                        title="Employment record"
                        description="Hire dates, status, and separation — ties to employments table."
                        :isSelf="isSelf"
                        :editIconSelf="employmentEditSelfIcon"
                        :editIconHr="Pencil"
                        editAriaLabelSelf="Edit hire date"
                        editAriaLabelHr="Adjust employment dates"
                        :tooltipSelf="
                            allowAboutMeWorkEditor
                                ? 'Adjust your hire date (password protected). Separation is recorded by HR.'
                                : 'Employment changes are managed by HR.'
                        "
                        :tooltipHr="
                            allowAboutMeWorkEditor
                                ? 'Adjust hire date (password protected).'
                                : 'No active employment to edit.'
                        "
                        :disabled="!allowAboutMeWorkEditor"
                        :show-edit-button="allowAboutMeWorkEditor"
                        cardContentClass="grid gap-4 px-5 pb-4 pt-4 sm:px-6 lg:px-7 sm:pb-6"
                        @edit="openAboutMeAdjustDatesDialog"
                    >
                        <template
                            v-if="showHrRecordSeparationEntry"
                            #header-actions
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="hidden shrink-0 border-amber-200 bg-amber-50 text-xs text-amber-900 hover:bg-amber-100 sm:inline-flex dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100 dark:hover:bg-amber-950/70"
                                        @click="
                                            openAboutMeRecordSeparationDialog
                                        "
                                    >
                                        <UserX
                                            class="mr-1.5 size-3.5"
                                            aria-hidden="true"
                                        />
                                        Record separation
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    Record separation date and status when all
                                    placements are ended for this employment.
                                </TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="inline-flex shrink-0 border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 sm:hidden dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100 dark:hover:bg-amber-950/70"
                                        aria-label="Record separation"
                                        @click="
                                            openAboutMeRecordSeparationDialog
                                        "
                                    >
                                        <UserX
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    Record separation
                                </TooltipContent>
                            </Tooltip>
                        </template>
                        <dl
                            class="grid gap-x-4 gap-y-5 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                v-for="row in profile.employment_rows"
                                :key="row.label"
                                class="grid gap-1.5 border-l-2 border-primary/20 py-1 pl-3"
                            >
                                <dt
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ row.label }}
                                </dt>
                                <dd
                                    class="text-sm font-medium text-foreground tabular-nums"
                                >
                                    {{ row.value }}
                                </dd>
                            </div>
                        </dl>
                    </EmployeeInformationSectionCard>
                </TabsContent>

                <TabsContent value="work" class="mt-0 outline-none">
                    <EmployeeInformationSectionCard
                        :icon="MapPin"
                        iconClass="size-7"
                        title="Unit assignments"
                        description="Organizational placements — current row ends with Present."
                        :isSelf="isSelf"
                        :editIconSelf="Pencil"
                        :editIconHr="Pencil"
                        editAriaLabelSelf="Edit assignments"
                        editAriaLabelHr="Edit assignments"
                        tooltipSelf="Edit assignments"
                        tooltipHr="Org chart assignment editing is coming soon."
                        :disabled="true"
                        :show-edit-button="false"
                        cardContentClass="space-y-3 px-5 pb-4 pt-4 sm:px-6 lg:px-7 sm:pb-6"
                    >
                        <template #header-actions>
                            <Tooltip v-if="canEditAboutMe">
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="shrink-0 border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                        as-child
                                    >
                                        <Link
                                            :href="organizationChart().url"
                                            aria-label="Open organization chart"
                                        >
                                            <ExternalLink
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="left">
                                    Organization chart
                                </TooltipContent>
                            </Tooltip>
                        </template>
                        <div
                            v-if="profile.unit_assignment_history.length === 0"
                            class="rounded-lg border border-dashed border-border/70 bg-muted/10 px-4 py-3 text-sm text-muted-foreground"
                        >
                            No assignment history.
                        </div>
                        <div
                            v-for="(
                                row, idx
                            ) in profile.unit_assignment_history"
                            :key="`assign-${idx}`"
                            class="flex flex-col gap-2 rounded-lg border border-l-2 border-border/60 border-l-primary/20 bg-muted/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0 space-y-1">
                                <span
                                    class="inline-flex items-center gap-1.5 text-sm font-semibold text-foreground"
                                >
                                    <MapPin
                                        class="size-3.5 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    {{ row.unit }}
                                </span>
                                <div
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <Badge
                                        variant="outline"
                                        class="text-[10px] font-normal"
                                    >
                                        {{ row.unit_type }}
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        class="font-mono text-[10px] font-normal tabular-nums"
                                    >
                                        {{ row.code ?? '—' }}
                                    </Badge>
                                </div>
                            </div>
                            <p
                                class="text-xs text-muted-foreground tabular-nums sm:text-right"
                            >
                                {{ row.start_date }} →
                                {{ row.end_date ?? 'Present' }}
                            </p>
                        </div>
                    </EmployeeInformationSectionCard>
                </TabsContent>

                <TabsContent value="work" class="mt-0 outline-none">
                    <EmployeeInformationSectionCard
                        :icon="User"
                        iconClass="size-7"
                        title="Positions"
                        description="Job titles linked to the positions catalog."
                        :isSelf="isSelf"
                        :editIconSelf="Pencil"
                        :editIconHr="Pencil"
                        editAriaLabelSelf="Edit positions"
                        editAriaLabelHr="Edit positions"
                        :tooltipSelf="
                            allowAboutMeWorkEditor
                                ? 'Edit catalog positions tied to HR reporting.'
                                : 'Position history editing is coming soon.'
                        "
                        tooltipHr="Position history editing is coming soon."
                        :disabled="!allowAboutMeWorkEditor"
                        :show-edit-button="allowAboutMeWorkEditor"
                        cardContentClass="space-y-3 px-5 pb-4 pt-4 sm:px-6 lg:px-7 sm:pb-6"
                        @edit="aboutMeWorkPositionsOpen = true"
                    >
                        <div
                            v-for="(pos, idx) in profile.positions"
                            :key="idx"
                            class="flex flex-col gap-2 rounded-lg border border-l-2 border-border/60 border-l-primary/20 bg-muted/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p
                                    class="inline-flex items-center gap-1.5 font-medium text-foreground"
                                >
                                    <User
                                        class="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        pos.title
                                    }}</span>
                                    <Badge
                                        v-if="pos.is_primary"
                                        variant="secondary"
                                        class="ml-1 align-middle text-[10px] font-normal tracking-wide uppercase"
                                    >
                                        Primary
                                    </Badge>
                                </p>
                                <p
                                    class="font-mono text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ pos.code }}
                                </p>
                            </div>
                            <p
                                class="text-xs text-muted-foreground tabular-nums sm:text-right"
                            >
                                {{ pos.start_date }} →
                                {{ pos.end_date ?? 'Present' }}
                            </p>
                        </div>
                    </EmployeeInformationSectionCard>
                </TabsContent>

                <!-- Comp & Time -->
                <TabsContent value="comp-time" class="mt-0 outline-none">
                    <EmployeeInformationSectionCard
                        :icon="CalendarRange"
                        iconClass="size-7"
                        title="Work schedule"
                        description="Template from work schedules — exceptions appear in Attendance."
                        :isSelf="isSelf"
                        :editIconSelf="Pencil"
                        :editIconHr="Pencil"
                        editAriaLabelSelf="Edit schedule assignment"
                        editAriaLabelHr="Edit schedule assignment"
                        tooltipSelf="Request schedule changes through HR."
                        tooltipHr="Reassign template from Employee Schedules when wired."
                        :disabled="true"
                        :show-edit-button="false"
                        cardContentClass="space-y-3 px-5 pb-4 pt-4 sm:px-6 lg:px-7 sm:pb-6"
                    >
                        <template #header-actions>
                            <Tooltip v-if="canEditAboutMe">
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="shrink-0 border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                        as-child
                                    >
                                        <Link
                                            :href="employeeSchedules().url"
                                            aria-label="Open Employee Schedules"
                                        >
                                            <ExternalLink
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="left">
                                    Employee Schedules
                                </TooltipContent>
                            </Tooltip>
                        </template>
                        <div
                            class="space-y-3 rounded-lg border border-l-2 border-border/60 border-l-primary/20 bg-muted/10 px-4 py-3 text-sm"
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="space-y-1">
                                    <p class="font-semibold text-foreground">
                                        {{ scheduleName }}
                                    </p>
                                    <p
                                        v-if="scheduleTimeRange !== ''"
                                        class="font-mono text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{ scheduleTimeRange }}
                                    </p>
                                </div>
                                <Badge
                                    v-if="profile.schedule_template_code"
                                    variant="outline"
                                    class="font-mono text-[10px] font-normal tabular-nums"
                                >
                                    Code {{ profile.schedule_template_code }}
                                </Badge>
                            </div>
                            <p
                                class="text-xs leading-relaxed text-muted-foreground"
                            >
                                {{ scheduleWorkingDaysDisplay }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Rotations, overnight flags, and grace rules live
                                on the template definition.
                            </p>
                        </div>
                    </EmployeeInformationSectionCard>
                </TabsContent>

                <TabsContent value="work" class="mt-0 outline-none">
                    <EmployeeInformationSectionCard
                        :icon="Building2"
                        iconClass="size-7"
                        title="Affiliation"
                        description="All current and historical organizational affiliations."
                        :isSelf="isSelf"
                        :editIconSelf="Pencil"
                        :editIconHr="Pencil"
                        editAriaLabelSelf="Edit affiliation"
                        editAriaLabelHr="Edit affiliation"
                        :tooltipSelf="
                            allowAboutMeWorkEditor
                                ? 'Edit organizational affiliations (branch vs org-wide).'
                                : 'Affiliation edits follow the org chart flow.'
                        "
                        tooltipHr="Affiliation edits follow the org chart flow."
                        :disabled="!allowAboutMeWorkEditor"
                        :show-edit-button="allowAboutMeWorkEditor"
                        cardContentClass="space-y-3 px-5 pb-4 pt-4 sm:px-6 lg:px-7 sm:pb-6"
                        @edit="aboutMeWorkAffiliationsOpen = true"
                    >
                        <div
                            v-if="profile.affiliation_history.length === 0"
                            class="rounded-lg border border-dashed border-border/70 bg-muted/10 px-4 py-3 text-sm text-muted-foreground"
                        >
                            No affiliation records.
                        </div>
                        <div
                            v-for="(row, idx) in profile.affiliation_history"
                            :key="`aff-${idx}`"
                            class="flex flex-col gap-2 rounded-lg border border-l-2 border-border/60 border-l-primary/20 bg-muted/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="space-y-1.5">
                                <p class="font-medium text-foreground">
                                    {{ row.unit }}
                                </p>
                                <div
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <Badge
                                        variant="outline"
                                        class="text-[10px] font-normal"
                                    >
                                        {{ row.unit_type }}
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        class="font-mono text-[10px] font-normal tabular-nums"
                                    >
                                        {{ row.code ?? '—' }}
                                    </Badge>
                                </div>
                            </div>
                            <p
                                class="text-xs text-muted-foreground tabular-nums sm:text-right"
                            >
                                {{ row.start_date }} →
                                {{ row.end_date ?? 'Present' }}
                            </p>
                        </div>
                    </EmployeeInformationSectionCard>
                </TabsContent>

                <TabsContent value="comp-time" class="mt-0 outline-none">
                    <EmployeeInformationSectionCard
                        :icon="Info"
                        iconClass="size-7"
                        title="Attendance"
                        description="Summary only — open Attendance for clocks and corrections."
                        :isSelf="isSelf"
                        :editIconSelf="ExternalLink"
                        :editIconHr="ExternalLink"
                        editAriaLabelSelf="Open attendance page"
                        editAriaLabelHr="Open attendance page"
                        tooltipSelf="Attendance page link will be added here."
                        tooltipHr="Attendance page link will be added here."
                        :disabled="true"
                        cardContentClass="hidden"
                    />
                </TabsContent>
            </Tabs>
        </div>

        <AboutMeProfileEditDialog
            v-if="canEditAboutMe"
            v-model:open="aboutMeProfileEditOpen"
            :profile="profile"
        />

        <AboutMeIdentityEditDialog
            v-if="canEditAboutMe"
            v-model:open="identityEditOpen"
            :profile="profile"
        />

        <AboutMePersonalContactsEditDialog
            v-if="canEditAboutMe"
            v-model:open="personalContactsEditOpen"
            :profile="profile"
        />

        <AboutMeEmergencyContactsEditDialog
            v-if="canEditAboutMe"
            v-model:open="emergencyContactsEditOpen"
            :profile="profile"
        />

        <AboutMeAddressesEditDialog
            v-if="canEditAboutMe"
            v-model:open="addressesEditOpen"
            :profile="profile"
        />

        <AdjustEmploymentDatesDialog
            v-if="allowAboutMeWorkEditor && selfAboutMeEmploymentRow"
            :row="selfAboutMeEmploymentRow"
            :open="aboutMeAdjustEmploymentOpen"
            :mode="aboutMeEmploymentDialogMode"
            :inertia-reload-only="['profile', 'aboutMeWork']"
            @update:open="aboutMeAdjustEmploymentOpen = $event"
        />

        <AboutMeWorkPositionsDialog
            v-if="allowAboutMeWorkEditor && props.aboutMeWork"
            :work="props.aboutMeWork"
            :open="aboutMeWorkPositionsOpen"
            :inertia-reload-only="['profile', 'aboutMeWork']"
            @update:open="aboutMeWorkPositionsOpen = $event"
        />

        <AboutMeWorkAffiliationsDialog
            v-if="allowAboutMeWorkEditor && props.aboutMeWork"
            :work="props.aboutMeWork"
            :open="aboutMeWorkAffiliationsOpen"
            :inertia-reload-only="['profile', 'aboutMeWork']"
            @update:open="aboutMeWorkAffiliationsOpen = $event"
        />
    </TooltipProvider>
</template>
