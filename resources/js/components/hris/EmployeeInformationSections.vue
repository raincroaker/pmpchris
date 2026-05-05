<script setup lang="ts">
import {
    BookUser,
    Building2,
    CalendarRange,
    Clock,
    Home,
    IdCard,
    Info,
    Lock,
    MapPin,
    Pencil,
    Phone,
    User,
    UserCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { EmployeeProfileDisplay } from '@/pages/Employees/employeeProfileDisplay';

const props = defineProps<{
    variant: 'self' | 'hr';
    profile: EmployeeProfileDisplay;
}>();

const isSelf = computed(() => props.variant === 'self');

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

const scheduleParts = computed(() =>
    props.profile.schedule_label
        .split('·')
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

const scheduleDayTokens = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const;

const scheduleDays = [
    { label: 'Mon', token: 'mon' },
    { label: 'Tue', token: 'tue' },
    { label: 'Wed', token: 'wed' },
    { label: 'Thu', token: 'thu' },
    { label: 'Fri', token: 'fri' },
    { label: 'Sat', token: 'sat' },
    { label: 'Sun', token: 'sun' },
] as const;

const scheduleActiveDays = computed((): Set<string> => {
    const active = new Set<string>();
    const dayPart =
        scheduleParts.value.find((part) => /mon|tue|wed|thu|fri|sat|sun/i.test(part)) ??
        '';
    const normalized = dayPart.toLowerCase();

    if (normalized.includes('mon') && normalized.includes('fri')) {
        active.add('mon');
        active.add('tue');
        active.add('wed');
        active.add('thu');
        active.add('fri');
    }
    if (normalized.includes('mon') && normalized.includes('sat')) {
        active.add('mon');
        active.add('tue');
        active.add('wed');
        active.add('thu');
        active.add('fri');
        active.add('sat');
    }

    for (const token of scheduleDayTokens) {
        if (normalized.includes(token)) {
            active.add(token);
        }
    }

    return active;
});

const affiliationCode = computed((): string => {
    return (
        props.profile.assignment_history[0]?.code ??
        props.profile.org_scope_label ??
        '—'
    );
});

const statusBadgeClass = computed((): string => {
    const normalizedStatus = props.profile.status_label.trim().toLowerCase();
    if (normalizedStatus === 'active') {
        return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    }
    if (normalizedStatus === 'resigned' || normalizedStatus === 'contract ended') {
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
            <Alert
                v-if="profile.layout_notice"
                variant="default"
                class="border-border/70 bg-muted/30 text-foreground"
            >
                <Info class="text-muted-foreground" aria-hidden="true" />
                <AlertTitle class="text-sm font-medium">Notice</AlertTitle>
                <AlertDescription class="text-muted-foreground">
                    {{ profile.layout_notice }}
                </AlertDescription>
            </Alert>

            <!-- Hero -->
            <div
                class="relative flex flex-col gap-6 overflow-hidden rounded-2xl border border-border/80 bg-card p-6 shadow-sm"
            >
                <div
                    class="pointer-events-none absolute -left-16 top-1/2 size-48 -translate-y-1/2 rounded-full bg-primary/10 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-primary/10 via-primary/40 to-primary/10"
                    aria-hidden="true"
                />
                <div class="flex items-center justify-between gap-3">
                    <h1
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        About Me
                    </h1>
                    <Tooltip v-if="isSelf">
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 rounded-md"
                                disabled
                                aria-label="Edit about me profile"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="left">
                            Profile editing actions will be added here.
                        </TooltipContent>
                    </Tooltip>
                </div>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_1px_minmax(0,1.2fr)]">
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
                                <AvatarFallback class="text-xl font-semibold text-muted-foreground sm:text-2xl">
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
                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                <Badge
                                    v-for="day in scheduleDays"
                                    :key="day.token"
                                    variant="outline"
                                    class="rounded-md px-1.5 py-0 text-[10px] font-medium"
                                    :class="
                                        scheduleActiveDays.has(day.token)
                                            ? 'border-border bg-background/90 text-foreground'
                                            : 'border-border/60 bg-muted/40 text-muted-foreground'
                                    "
                                >
                                    {{ day.label }}
                                </Badge>
                            </div>
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

            <Tabs default-value="overview" class="gap-5">
                <section
                    aria-label="Profile sections"
                    class="border-b border-border/70"
                >
                    <TabsList
                        class="no-scrollbar flex h-auto w-full flex-wrap justify-start gap-4 rounded-none bg-transparent p-0 text-foreground shadow-none"
                    >
                        <TabsTrigger
                            value="overview"
                            class="rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 py-2 text-xs font-medium text-muted-foreground shadow-none transition-colors hover:text-foreground data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none sm:text-sm"
                        >
                            Overview
                        </TabsTrigger>
                        <TabsTrigger
                            value="work"
                            class="rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 py-2 text-xs font-medium text-muted-foreground shadow-none transition-colors hover:text-foreground data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none sm:text-sm"
                        >
                            Work
                        </TabsTrigger>
                        <TabsTrigger
                            value="profile"
                            class="rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 py-2 text-xs font-medium text-muted-foreground shadow-none transition-colors hover:text-foreground data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none sm:text-sm"
                        >
                            Profile
                        </TabsTrigger>
                        <TabsTrigger
                            value="comp-time"
                            class="rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 py-2 text-xs font-medium text-muted-foreground shadow-none transition-colors hover:text-foreground data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none sm:text-sm"
                        >
                            Comp &amp; Time
                        </TabsTrigger>
                    </TabsList>
                </section>

                <!-- Overview -->
                <TabsContent
                    value="overview"
                    class="mt-0 flex flex-col gap-6 outline-none"
                >
                    <Card class="border-border/70 shadow-sm">
                        <CardHeader class="border-b border-border/60 pb-4">
                            <CardTitle class="text-base">Overview</CardTitle>
                            <CardDescription>
                                Quick identity, status, affiliation, and primary metrics are shown in the hero above.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">
                                Use <span class="font-medium text-foreground">Profile</span> for personal details, <span class="font-medium text-foreground">Work</span> for employment/organization details, and <span class="font-medium text-foreground">Comp &amp; Time</span> for schedule and attendance.
                            </p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Profile -->
                <TabsContent
                    value="profile"
                    class="mt-0 flex flex-col gap-6 outline-none"
                >
                    <!-- Demographics -->
                    <Card class="overflow-hidden border-border/70 shadow-sm">
                        <div
                            class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                        >
                            <div class="flex flex-row items-start gap-4">
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
                                        Legal identity and civil status as kept
                                        on the employee record.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <CardHeader class="border-b border-border/60 pb-4">
                            <CardTitle class="text-base"
                                >Demographics</CardTitle
                            >
                            <CardDescription>
                                Date of birth visibility follows your birthday
                                settings.
                            </CardDescription>
                            <CardAction>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="rounded-lg"
                                            disabled
                                            :aria-label="
                                                isSelf
                                                    ? 'Edit demographics'
                                                    : 'Edit demographics (HR)'
                                            "
                                        >
                                            <Pencil
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{
                                            isSelf
                                                ? 'Self-service editing is coming soon.'
                                                : 'HR edit workflow is coming soon.'
                                        }}
                                    </TooltipContent>
                                </Tooltip>
                            </CardAction>
                        </CardHeader>
                        <CardContent class="grid gap-4 pt-6">
                            <dl
                                class="grid grid-cols-1 gap-x-4 gap-y-5 md:grid-cols-2 lg:grid-cols-3"
                            >
                                <div class="grid gap-1.5">
                                    <dt
                                        class="text-xs font-medium text-muted-foreground"
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
                                <div class="grid gap-1.5">
                                    <dt
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Sex
                                    </dt>
                                    <dd
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{ profile.demographics.sex }}
                                    </dd>
                                </div>
                                <div class="grid gap-1.5">
                                    <dt
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Civil status
                                    </dt>
                                    <dd
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{ profile.demographics.civil_status }}
                                    </dd>
                                </div>
                                <div class="grid gap-1.5">
                                    <dt
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Nationality
                                    </dt>
                                    <dd
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{ profile.demographics.nationality }}
                                    </dd>
                                </div>
                                <div class="grid gap-1.5">
                                    <dt
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Religion
                                    </dt>
                                    <dd
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{
                                            profile.demographics.religion ?? '—'
                                        }}
                                    </dd>
                                </div>
                                <div
                                    class="grid gap-1.5 md:col-span-2 lg:col-span-3"
                                >
                                    <dt
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Birthday visibility
                                    </dt>
                                    <dd class="text-sm text-foreground">
                                        {{
                                            profile.demographics
                                                .birthday_visibility_label
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
                                class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                            >
                                <div class="flex flex-row items-start gap-4">
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
                                            Personal channels from your HR file.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <CardHeader class="border-b border-border/60 pb-4">
                                <CardTitle class="text-base"
                                    >Personal contacts</CardTitle
                                >
                                <CardDescription>
                                    Primary flag indicates the default channel
                                    for reach-outs.
                                </CardDescription>
                                <CardAction>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="rounded-lg"
                                                disabled
                                                aria-label="Edit personal contacts"
                                            >
                                                <Pencil
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {{
                                                isSelf
                                                    ? 'Update requests will open here soon.'
                                                    : 'Contact editing for HR is coming soon.'
                                            }}
                                        </TooltipContent>
                                    </Tooltip>
                                </CardAction>
                            </CardHeader>
                            <CardContent class="space-y-3 pt-6">
                                <div
                                    v-for="(
                                        c, idx
                                    ) in profile.personal_contacts"
                                    :key="`p-${idx}`"
                                    class="flex flex-col gap-1 rounded-lg border border-border/60 bg-muted/10 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="text-sm font-medium text-foreground"
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
                                    <div
                                        class="text-right text-sm sm:text-left"
                                    >
                                        <p
                                            class="font-mono text-foreground tabular-nums"
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
                                class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                            >
                                <div class="flex flex-row items-start gap-4">
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
                            </div>
                            <CardHeader class="border-b border-border/60 pb-4">
                                <CardTitle class="text-base"
                                    >Emergency</CardTitle
                                >
                                <CardDescription>
                                    Keep at least one reachable contact current.
                                </CardDescription>
                                <CardAction>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="rounded-lg"
                                                disabled
                                                aria-label="Edit emergency contacts"
                                            >
                                                <Pencil
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Emergency contact editing opens here
                                            soon.
                                        </TooltipContent>
                                    </Tooltip>
                                </CardAction>
                            </CardHeader>
                            <CardContent class="space-y-3 pt-6">
                                <div
                                    v-for="(
                                        c, idx
                                    ) in profile.emergency_contacts"
                                    :key="`e-${idx}`"
                                    class="rounded-lg border border-border/60 bg-muted/10 px-3 py-2.5"
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
                                    <p class="text-xs text-muted-foreground">
                                        {{ c.relationship }} ·
                                        {{ c.channel_label }}
                                    </p>
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
                            class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
                        >
                            <div class="flex flex-row items-start gap-4">
                                <span
                                    class="inline-flex shrink-0 rounded-full bg-primary/10 p-3 text-primary"
                                >
                                    <Home class="size-6" aria-hidden="true" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <h3
                                        class="text-base font-semibold text-foreground"
                                    >
                                        Addresses
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        Current residence and permanent address
                                        on file (PSGC-ready in the create flow).
                                    </p>
                                </div>
                            </div>
                        </div>
                        <CardHeader class="border-b border-border/60 pb-4">
                            <CardTitle class="text-base">Residential</CardTitle>
                            <CardDescription>
                                Current vs permanent — align with official
                                documents when possible.
                            </CardDescription>
                            <CardAction>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="rounded-lg"
                                            disabled
                                            aria-label="Edit addresses"
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
                            </CardAction>
                        </CardHeader>
                        <CardContent class="grid gap-6 pt-6 md:grid-cols-2">
                            <div
                                v-if="profile.current_address"
                                class="flex flex-col gap-2"
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
                                        variant="outline"
                                        class="text-[10px] font-normal"
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
                                class="flex flex-col gap-2"
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
                                        variant="outline"
                                        class="text-[10px] font-normal"
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
                    <Card class="border-border/70 shadow-sm">
                        <CardHeader class="border-b border-border/60 pb-4">
                            <CardTitle class="text-base"
                                >Employment record</CardTitle
                            >
                            <CardDescription>
                                Hire dates, status, and separation — ties to
                                employments table.
                            </CardDescription>
                            <CardAction>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="rounded-lg"
                                            disabled
                                            :aria-label="
                                                isSelf
                                                    ? 'Employment locked'
                                                    : 'Edit employment'
                                            "
                                        >
                                            <Lock
                                                v-if="isSelf"
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            <Pencil
                                                v-else
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{
                                            isSelf
                                                ? 'Employment changes are managed by HR.'
                                                : 'HR employment editing is coming soon.'
                                        }}
                                    </TooltipContent>
                                </Tooltip>
                            </CardAction>
                        </CardHeader>
                        <CardContent class="pt-6">
                            <dl class="grid gap-3 sm:grid-cols-2">
                                <div
                                    v-for="row in profile.employment_rows"
                                    :key="row.label"
                                    class="rounded-lg border border-border/60 bg-muted/10 px-4 py-3"
                                >
                                    <dt
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        {{ row.label }}
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm font-medium text-foreground"
                                    >
                                        {{ row.value }}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="work" class="mt-0 outline-none">
                    <Card class="border-border/70 shadow-sm">
                        <CardHeader class="border-b border-border/60 pb-4">
                            <CardTitle class="text-base"
                                >Unit assignments</CardTitle
                            >
                            <CardDescription>
                                Organizational placements — current row ends
                                with Present.
                            </CardDescription>
                            <CardAction>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="rounded-lg"
                                            disabled
                                            :aria-label="
                                                isSelf
                                                    ? 'Assignments locked'
                                                    : 'Edit assignments'
                                            "
                                        >
                                            <Lock
                                                v-if="isSelf"
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            <Pencil
                                                v-else
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{
                                            isSelf
                                                ? 'Assignment changes go through HR or your manager.'
                                                : 'Org chart assignment editing is coming soon.'
                                        }}
                                    </TooltipContent>
                                </Tooltip>
                            </CardAction>
                        </CardHeader>
                        <CardContent class="overflow-x-auto pt-6">
                            <table
                                class="w-full min-w-lg border-collapse text-left text-sm"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-border/80 text-muted-foreground"
                                    >
                                        <th class="pr-4 pb-2 font-medium">
                                            Unit
                                        </th>
                                        <th class="pr-4 pb-2 font-medium">
                                            Type
                                        </th>
                                        <th class="pr-4 pb-2 font-medium">
                                            Code
                                        </th>
                                        <th class="pr-4 pb-2 font-medium">
                                            Start
                                        </th>
                                        <th class="pb-2 font-medium">End</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(
                                            row, idx
                                        ) in profile.assignment_history"
                                        :key="idx"
                                        class="border-b border-border/40 transition-colors last:border-0 hover:bg-muted/30"
                                    >
                                        <td
                                            class="py-3 pr-4 align-top font-medium text-foreground"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1.5"
                                            >
                                                <MapPin
                                                    class="size-3.5 shrink-0 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                {{ row.unit }}
                                            </span>
                                        </td>
                                        <td
                                            class="py-3 pr-4 align-top text-muted-foreground"
                                        >
                                            {{ row.unit_type }}
                                        </td>
                                        <td
                                            class="py-3 pr-4 align-top font-mono text-xs text-muted-foreground tabular-nums"
                                        >
                                            {{ row.code ?? '—' }}
                                        </td>
                                        <td
                                            class="py-3 pr-4 align-top tabular-nums"
                                        >
                                            {{ row.start_date }}
                                        </td>
                                        <td class="py-3 align-top">
                                            <Badge
                                                v-if="row.end_date === null"
                                                variant="secondary"
                                                class="font-normal"
                                            >
                                                Present
                                            </Badge>
                                            <span
                                                v-else
                                                class="text-muted-foreground tabular-nums"
                                            >
                                                {{ row.end_date }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="work" class="mt-0 outline-none">
                    <Card class="border-border/70 shadow-sm">
                        <CardHeader class="border-b border-border/60 pb-4">
                            <CardTitle class="text-base">Positions</CardTitle>
                            <CardDescription>
                                Job titles linked to the positions catalog.
                            </CardDescription>
                            <CardAction>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="rounded-lg"
                                            disabled
                                            aria-label="Edit positions"
                                        >
                                            <Pencil
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Position history editing is coming soon.
                                    </TooltipContent>
                                </Tooltip>
                            </CardAction>
                        </CardHeader>
                        <CardContent class="space-y-3 pt-6">
                            <div
                                v-for="(pos, idx) in profile.positions"
                                :key="idx"
                                class="flex flex-col gap-2 rounded-xl border border-border/70 bg-muted/10 px-4 py-3 transition-colors hover:bg-muted/20 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex items-start gap-2">
                                    <User
                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <p class="font-medium text-foreground">
                                            {{ pos.title }}
                                            <Badge
                                                v-if="pos.is_primary"
                                                variant="secondary"
                                                class="ml-2 align-middle text-[10px] font-normal tracking-wide uppercase"
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
                                </div>
                                <div
                                    class="text-xs text-muted-foreground sm:text-right"
                                >
                                    <p class="tabular-nums">
                                        {{ pos.start_date }} →
                                        {{ pos.end_date ?? 'Present' }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Comp & Time -->
                <TabsContent value="comp-time" class="mt-0 outline-none">
                    <div
                        class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_1px_minmax(0,1fr)] lg:gap-6"
                    >
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <h3 class="text-base font-semibold">
                                        Work schedule
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        Template from work schedules —
                                        exceptions appear in Attendance.
                                    </p>
                                </div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="rounded-lg"
                                            disabled
                                            aria-label="Edit schedule assignment"
                                        >
                                            <Pencil
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{
                                            isSelf
                                                ? 'Request schedule changes through HR.'
                                                : 'Reassign template from Employee Schedules when wired.'
                                        }}
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        </div>
                        <div
                            class="hidden h-full bg-border/80 lg:block"
                            role="presentation"
                            aria-hidden="true"
                        />
                        <div class="space-y-2 text-sm">
                            <p class="font-semibold text-foreground">
                                {{ profile.schedule_label }}
                            </p>
                            <p
                                v-if="profile.schedule_template_code"
                                class="font-mono text-xs text-muted-foreground tabular-nums"
                            >
                                Code {{ profile.schedule_template_code }}
                            </p>
                            <p class="text-muted-foreground">
                                Rotations, overnight flags, and grace rules
                                live on the template definition.
                            </p>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="work" class="mt-0 outline-none">
                    <div
                        class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_1px_minmax(0,1fr)] lg:gap-6"
                    >
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <h3 class="text-base font-semibold">
                                        Affiliation
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        Branch root vs org-wide — drives
                                        directory scoping.
                                    </p>
                                </div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="rounded-lg"
                                            disabled
                                            aria-label="Edit affiliation"
                                        >
                                            <Pencil
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Affiliation edits follow the org chart
                                        flow.
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                            <div class="space-y-1 text-sm">
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Primary branch / root
                                </p>
                                <p class="font-semibold text-foreground">
                                    {{ profile.branch_label }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="hidden h-full bg-border/80 lg:block"
                            role="presentation"
                            aria-hidden="true"
                        />
                        <div class="space-y-1 text-sm">
                            <p class="text-xs font-medium text-muted-foreground">
                                Organization scope
                            </p>
                            <p class="font-semibold text-foreground">
                                {{ profile.org_scope_label }}
                            </p>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="comp-time" class="mt-0 outline-none">
                    <Card class="border-border/70 shadow-sm">
                        <CardHeader class="border-b border-border/60 pb-4">
                            <CardTitle class="text-base">Attendance</CardTitle>
                            <CardDescription>
                                Summary only — open Attendance for clocks and
                                corrections.
                            </CardDescription>
                            <CardAction>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="rounded-lg"
                                            disabled
                                            aria-label="Attendance information"
                                        >
                                            <Info
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Detailed logs stay in the Attendance
                                        module.
                                    </TooltipContent>
                                </Tooltip>
                            </CardAction>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-3 pt-6">
                            <div
                                v-for="(row, idx) in profile.attendance_summary"
                                :key="idx"
                                class="rounded-xl border border-border/70 bg-muted/10 px-4 py-3"
                            >
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    {{ row.label }}
                                </p>
                                <p class="mt-1 font-medium text-foreground">
                                    {{ row.value }}
                                </p>
                                <p
                                    v-if="row.hint"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ row.hint }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </TooltipProvider>
</template>
