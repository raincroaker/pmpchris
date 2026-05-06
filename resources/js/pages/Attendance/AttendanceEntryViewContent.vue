<script setup lang="ts">
import { Info } from 'lucide-vue-next';
import HrisEmployeeDirectoryUnitAndPositions from '@/components/hris/HrisEmployeeDirectoryUnitAndPositions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatIsoCalendarDate } from '@/lib/teamRequestDateRange';
import type { TeamAttendanceRow } from '@/pages/Attendance/teamAttendanceTypes';
import {
    ATTENDANCE_VIEW_AUDIT_LAST_SAVED_VIA_TOOLTIP,
    ATTENDANCE_VIEW_AUDIT_ORIGINAL_SOURCE_TOOLTIP,
    ATTENDANCE_VIEW_BREAK_FIELD_TOOLTIP,
    ATTENDANCE_VIEW_FIRST_IN_GRACE_TOOLTIP,
    ATTENDANCE_VIEW_GROSS_PUNCH_SUM_TOOLTIP,
    ATTENDANCE_VIEW_INGEST_KEY_TOOLTIP,
    ATTENDANCE_VIEW_LATE_MINUTES_TOOLTIP,
    ATTENDANCE_VIEW_NET_PUNCH_SUM_TOOLTIP,
    ATTENDANCE_VIEW_PROFILE_ATTENDANCE_ID_TOOLTIP,
    ATTENDANCE_VIEW_PUNCTUALITY_TOOLTIP,
    TEAM_ATTENDANCE_FIRST_IN_GRACE_MINUTES,
    attendanceActualDutyGrossDisplay,
    attendanceFirstSegmentLateMinutesDisplay,
    attendanceNetWithinScheduledOverlapDisplay,
    attendanceRecordingStyleShortLabel,
    attendanceScheduledBreakFieldDisplay,
    attendanceScheduledWindowBody,
    attendanceSourceBadgeClass,
    attendanceSourceLabel,
    attendanceStatusBadgeClass,
    attendanceStatusLabel,
    clockInOutDisplay,
    formatIsoDateTimeLocal,
    punctualityBadgeClass,
    punctualityLabel,
} from '@/pages/Attendance/teamAttendanceUi';

defineProps<{
    row: TeamAttendanceRow;
}>();
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <div class="grid gap-3 px-2 py-2 text-sm sm:grid-cols-2">
            <div class="grid gap-1.5 sm:col-span-2">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Name
                </p>
                <div
                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm leading-snug text-foreground"
                >
                    <span class="font-medium">{{
                        row.employee.display_name
                    }}</span>
                    <span
                        class="mt-1 block font-mono text-xs text-muted-foreground tabular-nums"
                    >
                        {{ row.employee.id_number }}
                    </span>
                </div>
            </div>

            <div class="grid gap-1.5">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Work date
                </p>
                <p
                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground tabular-nums"
                >
                    {{ formatIsoCalendarDate(row.work_date) }}
                </p>
            </div>

            <div class="grid gap-1.5">
                <div class="flex h-6 min-h-6 shrink-0 items-center gap-1.5">
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Attendance ID
                    </p>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                aria-label="Explain attendance ID"
                            >
                                <Info
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top" class="max-w-xs text-pretty">
                            {{ ATTENDANCE_VIEW_PROFILE_ATTENDANCE_ID_TOOLTIP }}
                        </TooltipContent>
                    </Tooltip>
                </div>
                <p
                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-sm tabular-nums"
                    :class="
                        row.attendance_id?.trim()
                            ? 'text-foreground'
                            : 'text-muted-foreground'
                    "
                >
                    {{ row.attendance_id?.trim() || '—' }}
                </p>
            </div>

            <div class="grid gap-1.5 sm:col-span-2">
                <div class="flex h-6 min-h-6 shrink-0 items-center gap-1.5">
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Device / ingest row ID
                    </p>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                aria-label="Explain device or ingest row ID"
                            >
                                <Info
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top" class="max-w-xs text-pretty">
                            {{ ATTENDANCE_VIEW_INGEST_KEY_TOOLTIP }}
                        </TooltipContent>
                    </Tooltip>
                </div>
                <p
                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-sm tabular-nums"
                    :class="
                        row.ingest_key?.trim()
                            ? 'text-foreground'
                            : 'text-muted-foreground'
                    "
                >
                    {{ row.ingest_key?.trim() || '—' }}
                </p>
            </div>

            <HrisEmployeeDirectoryUnitAndPositions
                :unit-name="row.unit_name"
                :unit-code="row.unit_code"
                :unit-directory-type="
                    row.placement_unit_type?.trim()
                        ? row.placement_unit_type
                        : 'Organizational unit'
                "
                :unit-directory-color="row.placement_unit_type_color ?? null"
                :placement-is-primary="row.placement_is_primary ?? false"
                :positions="row.positions ?? []"
            />

            <div class="grid gap-1.5 sm:col-span-2">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Recording style
                </p>
                <p
                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm leading-relaxed text-foreground"
                >
                    {{
                        attendanceRecordingStyleShortLabel(
                            row.clock_pattern,
                            row.is_overnight_schedule,
                        )
                    }}
                </p>
            </div>

            <div class="grid gap-1.5 sm:col-span-2">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Work schedule
                </p>
                <p
                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm leading-snug text-foreground"
                >
                    {{ row.work_schedule_name }}
                </p>
            </div>

            <div
                class="grid gap-3 sm:col-span-2 sm:grid-cols-2 sm:items-stretch"
            >
                <div class="flex min-h-0 min-w-0 flex-col gap-1.5">
                    <div class="flex h-6 min-h-6 shrink-0 items-center gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Scheduled window
                        </p>
                        <span class="size-6 shrink-0" aria-hidden="true"></span>
                    </div>
                    <p
                        class="flex min-h-11 flex-1 items-center rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-xs leading-relaxed whitespace-pre-line text-muted-foreground tabular-nums"
                    >
                        {{
                            attendanceScheduledWindowBody(
                                row.segments,
                                row.clock_pattern,
                            )
                        }}
                    </p>
                </div>
                <div class="flex min-h-0 min-w-0 flex-col gap-1.5">
                    <div class="flex h-6 min-h-6 shrink-0 items-center gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Breaktime
                        </p>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                    aria-label="Explain breaktime on this row"
                                >
                                    <Info
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent
                                side="top"
                                class="max-w-xs text-pretty"
                            >
                                {{ ATTENDANCE_VIEW_BREAK_FIELD_TOOLTIP }}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    <p
                        class="flex min-h-11 flex-1 items-center rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-xs leading-snug whitespace-pre-wrap text-foreground tabular-nums"
                    >
                        {{
                            attendanceScheduledBreakFieldDisplay(
                                row.segments,
                                row.clock_pattern,
                                row.unpaid_break_minutes,
                            )
                        }}
                    </p>
                </div>
            </div>

            <div
                class="grid gap-3 sm:col-span-2 sm:grid-cols-2 sm:items-stretch"
            >
                <div class="flex min-h-0 min-w-0 flex-col gap-1.5">
                    <div class="flex h-6 min-h-6 shrink-0 items-center gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Time (actual)
                        </p>
                        <span class="size-6 shrink-0" aria-hidden="true"></span>
                    </div>
                    <p
                        class="flex min-h-11 flex-1 items-center rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-xs leading-relaxed whitespace-pre-line text-foreground tabular-nums"
                    >
                        {{ clockInOutDisplay(row.clock_pattern, row.segments) }}
                    </p>
                </div>
                <div class="flex min-h-0 min-w-0 flex-col gap-1.5">
                    <div class="flex h-6 min-h-6 shrink-0 items-center gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Grace period
                        </p>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                    aria-label="Explain grace period"
                                >
                                    <Info
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent
                                side="top"
                                class="max-w-xs text-pretty"
                            >
                                {{ ATTENDANCE_VIEW_FIRST_IN_GRACE_TOOLTIP }}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    <div
                        class="flex min-h-11 flex-1 items-center rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-xs leading-snug text-foreground tabular-nums"
                    >
                        +{{ TEAM_ATTENDANCE_FIRST_IN_GRACE_MINUTES }} min
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2 sm:items-start">
                <div class="grid gap-1.5">
                    <div class="flex items-center gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Gross time
                        </p>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                    aria-label="Explain Gross time"
                                >
                                    <Info
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent
                                side="top"
                                class="max-w-xs text-pretty"
                            >
                                {{ ATTENDANCE_VIEW_GROSS_PUNCH_SUM_TOOLTIP }}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    <p
                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-sm text-foreground tabular-nums"
                    >
                        {{ attendanceActualDutyGrossDisplay(row.segments) }}
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <div class="flex items-center gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Net time
                        </p>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                    aria-label="Explain Net time"
                                >
                                    <Info
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent
                                side="top"
                                class="max-w-xs text-pretty"
                            >
                                {{ ATTENDANCE_VIEW_NET_PUNCH_SUM_TOOLTIP }}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    <p
                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-sm text-foreground tabular-nums"
                    >
                        {{
                            attendanceNetWithinScheduledOverlapDisplay(
                                row.segments,
                                row.punctuality,
                                {
                                    clockPattern: row.clock_pattern,
                                    unpaidBreakMinutesFromTemplate:
                                        row.unpaid_break_minutes ?? 0,
                                },
                            )
                        }}
                    </p>
                </div>
            </div>

            <div
                class="grid gap-3 sm:col-span-2 sm:grid-cols-3 sm:items-start sm:gap-x-6"
            >
                <div class="grid gap-1.5">
                    <div class="flex h-6 min-h-6 shrink-0 items-center gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Punctuality
                        </p>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                    aria-label="Explain Punctuality"
                                >
                                    <Info
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent
                                side="top"
                                class="max-w-xs text-pretty"
                            >
                                {{ ATTENDANCE_VIEW_PUNCTUALITY_TOOLTIP }}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    <div
                        class="flex min-h-11 items-center rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                    >
                        <Badge
                            variant="outline"
                            class="text-xs leading-tight font-medium shadow-none"
                            :class="punctualityBadgeClass(row.punctuality)"
                        >
                            {{ punctualityLabel(row.punctuality) }}
                        </Badge>
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <div class="flex h-6 min-h-6 shrink-0 items-center gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Late minutes
                        </p>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                    aria-label="Explain late minutes"
                                >
                                    <Info
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent
                                side="top"
                                class="max-w-xs text-pretty"
                            >
                                {{ ATTENDANCE_VIEW_LATE_MINUTES_TOOLTIP }}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    <div
                        class="flex min-h-11 items-center rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-sm leading-snug text-foreground tabular-nums"
                    >
                        {{
                            attendanceFirstSegmentLateMinutesDisplay(
                                row.punctuality,
                                row.segments,
                            )
                        }}
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <div class="flex h-6 min-h-6 shrink-0 items-center gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Status
                        </p>
                        <span class="size-6 shrink-0" aria-hidden="true"></span>
                    </div>
                    <div
                        class="flex min-h-11 items-center rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                    >
                        <Badge
                            variant="outline"
                            class="text-xs leading-tight font-medium shadow-none"
                            :class="attendanceStatusBadgeClass(row.status)"
                        >
                            {{ attendanceStatusLabel(row.status) }}
                        </Badge>
                    </div>
                </div>
            </div>

            <Separator class="sm:col-span-2" />

            <div class="grid gap-3 sm:col-span-2">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Audit trail
                </p>
                <div
                    class="grid gap-4 rounded-lg border border-border/60 bg-muted/15 px-3 py-3 sm:grid-cols-2"
                >
                    <div class="grid gap-1.5">
                        <div
                            class="flex h-6 min-h-6 shrink-0 items-center gap-1.5"
                        >
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Original source
                            </p>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                        aria-label="Explain original source"
                                    >
                                        <Info
                                            class="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="top"
                                    class="max-w-xs text-pretty"
                                >
                                    {{
                                        ATTENDANCE_VIEW_AUDIT_ORIGINAL_SOURCE_TOOLTIP
                                    }}
                                </TooltipContent>
                            </Tooltip>
                        </div>
                        <Badge
                            variant="outline"
                            class="w-fit text-xs leading-tight font-medium shadow-none"
                            :class="
                                attendanceSourceBadgeClass(row.original_source)
                            "
                        >
                            {{ attendanceSourceLabel(row.original_source) }}
                        </Badge>
                    </div>
                    <div class="grid gap-1.5">
                        <div
                            class="flex h-6 min-h-6 shrink-0 items-center gap-1.5"
                        >
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Last saved via
                            </p>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                        aria-label="Explain last saved via"
                                    >
                                        <Info
                                            class="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="top"
                                    class="max-w-xs text-pretty"
                                >
                                    {{
                                        ATTENDANCE_VIEW_AUDIT_LAST_SAVED_VIA_TOOLTIP
                                    }}
                                </TooltipContent>
                            </Tooltip>
                        </div>
                        <Badge
                            variant="outline"
                            class="w-fit text-xs leading-tight font-medium shadow-none"
                            :class="
                                attendanceSourceBadgeClass(
                                    row.last_modified_source,
                                )
                            "
                        >
                            {{
                                attendanceSourceLabel(row.last_modified_source)
                            }}
                        </Badge>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            Recorded
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm leading-snug text-foreground"
                        >
                            <span class="tabular-nums">{{
                                formatIsoDateTimeLocal(row.created_at)
                            }}</span>
                            <span
                                v-if="row.created_by?.trim()"
                                class="mt-1 block text-muted-foreground"
                            >
                                Added by
                                <span class="font-medium text-foreground">{{
                                    row.created_by
                                }}</span>
                            </span>
                            <span
                                v-else
                                class="mt-1 block text-muted-foreground"
                            >
                                No in-app creator (e.g. device ingest only).
                            </span>
                        </p>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            Last updated
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm leading-snug text-foreground"
                        >
                            <span class="tabular-nums">{{
                                formatIsoDateTimeLocal(row.updated_at)
                            }}</span>
                            <span
                                v-if="row.updated_by?.trim()"
                                class="mt-1 block text-muted-foreground"
                            >
                                Updated by
                                <span class="font-medium text-foreground">{{
                                    row.updated_by
                                }}</span>
                            </span>
                            <span
                                v-else
                                class="mt-1 block text-muted-foreground"
                            >
                                —
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </TooltipProvider>
</template>
