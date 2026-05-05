/**
 * Attendance / overtime rule panel state for work schedule templates.
 * Persisted as JSON on `work_schedule_templates.attendance_rules` and `overtime_rules`.
 */

export type ClockInRoundingOption = 'none' | '5' | '15' | 'custom';

export type WorkScheduleAttendanceRulesDraft = {
    /** Optional cap on how many grace arrivals count per employee per month (UI placeholder). */
    graceUsesLimitEnabled: boolean;
    graceUsesPerMonth: number;
    clockInRounding: ClockInRoundingOption;
    clockInRoundingCustomMinutes: number;
    /** Cap credited regular net hours (e.g. 8h standard day). */
    netRegularHoursCapEnabled: boolean;
    netRegularHoursCap: number;
    advancedOpen: boolean;
};

/**
 * Overtime is modeled as its own scheduled time in/out (not a third row on the Schedule tab).
 * Duration is implied by those times; policy adds continuity and grace only.
 */
export type WorkScheduleOvertimeRulesDraft = {
    /** When true, show and use scheduled OT time in / out. */
    otBlockEnabled: boolean;
    otTimeIn: string;
    otTimeOut: string;
    /** OT block can span midnight (e.g. night shift extension). */
    otIsOvernight: boolean;
    /**
     * When true, treat the regular day and this OT block as one continuous work story
     * (no required gap between last regular end and OT start for policy).
     */
    continuousAfterRegularNet: boolean;
    /** Minutes of grace at the OT start boundary (UI placeholder for engine). */
    otGraceMinutes: number;
};

export function defaultWorkScheduleAttendanceRulesDraft(): WorkScheduleAttendanceRulesDraft {
    return {
        graceUsesLimitEnabled: false,
        graceUsesPerMonth: 3,
        clockInRounding: 'none',
        clockInRoundingCustomMinutes: 1,
        netRegularHoursCapEnabled: true,
        netRegularHoursCap: 8,
        advancedOpen: false,
    };
}

export function defaultWorkScheduleOvertimeRulesDraft(): WorkScheduleOvertimeRulesDraft {
    return {
        otBlockEnabled: true,
        otTimeIn: '18:00',
        otTimeOut: '20:00',
        otIsOvernight: false,
        continuousAfterRegularNet: true,
        otGraceMinutes: 5,
    };
}
