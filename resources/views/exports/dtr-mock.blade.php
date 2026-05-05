@php
    $mono = "font-family:Consolas,'Courier New',monospace;";
    $cell = 'border:1px solid #000;padding:8px 6px;vertical-align:top;font-weight:400;font-size:10px;line-height:1.35;'.$mono;
    $cellData = $cell;
    $hdr = $cell.'background:#f9f9f9;';
    $hdrData = $hdr;
    $title = 'border:none;padding:16px 8px 14px;text-align:center;vertical-align:middle;font-weight:700;font-size:14px;letter-spacing:0.08em;line-height:1.25;background:transparent;'.$mono;
    $meta = $cell.'word-wrap:break-word;';
    $label = $cell.'color:#333;font-weight:700;white-space:nowrap;text-align:right;vertical-align:middle;';
    $detailColPx = '60px';
    $detailCell = $cellData."text-align:center;vertical-align:middle;width:{$detailColPx};min-width:{$detailColPx};max-width:{$detailColPx};word-wrap:break-word;overflow-wrap:break-word;";
    $hdrDetailCell = $hdrData."text-align:center;vertical-align:middle;width:{$detailColPx};min-width:{$detailColPx};max-width:{$detailColPx};word-wrap:break-word;overflow-wrap:break-word;";
    $time = $cellData."text-align:center;min-width:{$detailColPx};width:{$detailColPx};max-width:{$detailColPx};white-space:nowrap;";
    $hdrGrossNet = $hdr.'text-align:center;vertical-align:middle;line-height:1.2;';
    $bandCell = $cellData.'text-align:center;vertical-align:middle;word-wrap:break-word;';
    $footerSection = $hdr.'font-weight:700;text-align:left;';
    $footerLine = $cell.'text-align:left;';
    $isOvernightSchedule = ! empty($workScheduleReference) && (bool) ($workScheduleReference['is_overnight'] ?? false);
    $firstBandLabel = $isOvernightSchedule ? 'START' : 'AM';
    $secondBandLabel = $isOvernightSchedule ? 'END' : 'PM';
@endphp
{{-- Unified 10-column grid: stacked layout — DTR → work schedule → legend → OT → holidays --}}
<table style="border-collapse:collapse;">
    <tbody>
        <tr style="height:44px;">
            <th colspan="10" style="{{ $title }}">DAILY TIME RECORD</th>
        </tr>
        <tr>
            <td colspan="10" style="border:none;height:12px;"></td>
        </tr>
        <tr>
            <td colspan="2" style="{{ $label }}">Duration</td>
            <td colspan="3" style="{{ $meta }}">{{ $durationLabel }}</td>
            <td colspan="2" style="{{ $label }}">Generated at</td>
            <td colspan="3" style="{{ $meta }}">{{ $generatedAt }}</td>
            <td colspan="1" style="border:none;"></td>
            <td colspan="5" style="{{ $footerSection }}">Legend: OL = On leave · OT = Overtime</td>
        </tr>
        <tr>
            <td colspan="2" style="{{ $label }}">Name</td>
            <td colspan="8" style="{{ $cell }}word-wrap:break-word;">{{ $employeeName }}</td>
        </tr>
        <tr>
            <td colspan="2" style="{{ $label }}">Position</td>
            <td colspan="3" style="{{ $cell }}word-wrap:break-word;">{{ $position }}</td>
            <td colspan="2" style="{{ $label }}">Unit</td>
            <td colspan="3" style="{{ $cell }}word-wrap:break-word;">{{ $unit }}</td>
            @if (! empty($workScheduleReference))
                <td colspan="1" style="border:none;"></td>
                <td colspan="10" style="{{ $footerSection }}">Expected work schedule (reference)</td>
            @endif
        </tr>
        <tr>
            <td colspan="2" style="{{ $label }}">Employee ID</td>
            <td colspan="3" style="{{ $cell }}">{{ $employeeId }}</td>
            <td colspan="2" style="{{ $label }}">Attendance ID</td>
            <td colspan="3" style="{{ $cell }}">{{ $attendanceId }}</td>
            @if (! empty($workScheduleReference))
                <td colspan="1" style="border:none;"></td>
                <td colspan="10" style="{{ $footerLine }}">
                    Expected times match the assigned template from Scheduling → Work Schedules.
                </td>
            @endif
        </tr>
        <tr>
            <th colspan="2" rowspan="2" style="{{ $hdr }} text-align:center;vertical-align:middle;">Absence</th>
            <th colspan="2" rowspan="2" style="{{ $hdr }} text-align:center;vertical-align:middle;">Leaves</th>
            <th colspan="2" style="{{ $hdr }} text-align:center;">Late</th>
            <th colspan="2" style="{{ $hdr }} text-align:center;">Early Out</th>
            <th colspan="1" rowspan="2" style="{{ $hdrGrossNet }}">Gross<br>time</th>
            <th colspan="1" rowspan="2" style="{{ $hdrGrossNet }}">Net<br>time</th>
            @if (! empty($workScheduleReference))
                <td colspan="1" style="border:none;"></td>
                <td colspan="3" style="{{ $label }}">Work schedule</td>
                <td colspan="7" style="{{ $meta }}">{{ $workScheduleReference['template_name'] }}</td>
            @endif
        </tr>
        <tr>
            <th colspan="1" style="{{ $hdr }} text-align:center;">(x)</th>
            <th colspan="1" style="{{ $hdr }} text-align:center;">(Min)</th>
            <th colspan="1" style="{{ $hdr }} text-align:center;">(x)</th>
            <th colspan="1" style="{{ $hdr }} text-align:center;">(Min)</th>
            @if (! empty($workScheduleReference))
                <td colspan="1" style="border:none;"></td>
                <td colspan="3" style="{{ $label }}">Overnight</td>
                <td colspan="7" style="{{ $meta }}">{{ $workScheduleReference['is_overnight'] ? 'Yes' : 'No' }}</td>
            @endif
        </tr>
        <tr>
            <td colspan="2" style="{{ $cellData }} text-align:center;">{{ $absenceCount }}</td>
            <td colspan="2" style="{{ $cellData }} text-align:center;">{{ $leaveCountMonth }}</td>
            <td colspan="1" style="{{ $time }}">{{ $lateIncidentCount }}</td>
            <td colspan="1" style="{{ $time }}">{{ $lateMinutesTotal }}</td>
            <td colspan="1" style="{{ $time }}">{{ $earlyOutIncidentCount }}</td>
            <td colspan="1" style="{{ $time }}">{{ $earlyOutMinutesTotal }}</td>
            <td colspan="1" style="{{ $cellData }} text-align:center;">{{ $grossTimeTotal }}</td>
            <td colspan="1" style="{{ $cellData }} text-align:center;">{{ $netTimeTotal }}</td>
            @if (! empty($workScheduleReference))
                <td colspan="1" style="border:none;"></td>
                <td colspan="3" style="{{ $label }}">Status</td>
                <td colspan="7" style="{{ $meta }}">{{ $workScheduleReference['is_active'] ? 'Active' : 'Inactive' }}</td>
            @endif
        </tr>
        <tr>
            <th colspan="10" style="{{ $cell }}text-align:center;">Period: {{ $monthLabel }} — {{ $branchOrDept }}</th>
            @if (! empty($workScheduleReference))
                <td colspan="1" style="border:none;"></td>
                <td colspan="3" style="{{ $label }}">Scheduled days</td>
                <td colspan="7" style="{{ $meta }}">{{ $workScheduleReference['scheduled_days'] }}</td>
            @endif
        </tr>
        <tr>
            <th rowspan="2" style="{{ $hdrDetailCell }}">dd</th>
            <th rowspan="2" style="{{ $hdrDetailCell }}">ww</th>
            <th colspan="2" style="{{ $hdrData }} text-align:center;">{{ $firstBandLabel }}</th>
            <th colspan="2" style="{{ $hdrData }} text-align:center;">{{ $secondBandLabel }}</th>
            <th colspan="2" style="{{ $hdrData }} text-align:center;">OT</th>
            <th rowspan="2" style="{{ $hdrDetailCell }}">Remarks</th>
            <th rowspan="2" style="{{ $hdrDetailCell }}">Net Hours</th>
            @if (! empty($workScheduleReference))
                <td colspan="1" style="border:none;"></td>
                <td colspan="3" style="{{ $label }}">Clock pattern</td>
                <td colspan="7" style="{{ $meta }}">{{ $workScheduleReference['clock_pattern'] }}</td>
            @endif
        </tr>
        <tr>
            <th style="{{ $hdrDetailCell }}">In</th>
            <th style="{{ $hdrDetailCell }}">Out</th>
            <th style="{{ $hdrDetailCell }}">In</th>
            <th style="{{ $hdrDetailCell }}">Out</th>
            <th style="{{ $hdrDetailCell }}">In</th>
            <th style="{{ $hdrDetailCell }}">Out</th>
            @if (! empty($workScheduleReference))
                <td colspan="1" style="border:none;"></td>
                <td colspan="3" rowspan="3" style="{{ $label }}vertical-align:top;">Expected windows</td>
                <td colspan="7" rowspan="3" style="{{ $meta }}vertical-align:top;">{!! nl2br(e($workScheduleReference['expected_windows'])) !!}</td>
            @endif
        </tr>
        @foreach ($rows as $row)
            <tr>
                <td style="{{ $detailCell }}">{{ str_pad((string) $row['day'], 2, '0', STR_PAD_LEFT) }}</td>
                <td style="{{ $detailCell }}">{{ $row['dayName'] }}</td>
                @if (! empty($row['spanMiddle']))
                    <td colspan="6" style="{{ $bandCell }}">{{ $row['spanMiddle'] }}</td>
                @else
                    <td style="{{ $detailCell }}">{{ $row['amIn'] }}</td>
                    <td style="{{ $detailCell }}">{{ $row['amOut'] }}</td>
                    <td style="{{ $detailCell }}">{{ $row['pmIn'] }}</td>
                    <td style="{{ $detailCell }}">{{ $row['pmOut'] }}</td>
                    <td style="{{ $detailCell }}">{{ $row['otIn'] }}</td>
                    <td style="{{ $detailCell }}">{{ $row['otOut'] }}</td>
                @endif
                <td style="{{ $detailCell }}">{{ $row['remarks'] }}</td>
                <td style="{{ $detailCell }}">{{ $row['hours'] }}</td>
                @if (! empty($workScheduleReference) && $loop->iteration === 3)
                    <td colspan="1" style="border:none;"></td>
                    <td colspan="3" style="{{ $label }}">Unpaid break</td>
                    <td colspan="7" style="{{ $meta }}">{{ $workScheduleReference['unpaid_break'] }}</td>
                @elseif (! empty($workScheduleReference) && $loop->iteration === 4)
                    <td colspan="1" style="border:none;"></td>
                    <td colspan="3" style="{{ $label }}">Late arrival grace</td>
                    <td colspan="7" style="{{ $meta }}">{{ $workScheduleReference['late_grace_minutes'] }} min after first time-in</td>
                @elseif (! empty($workScheduleReference) && $loop->iteration === 5)
                    <td colspan="1" style="border:none;"></td>
                    <td colspan="3" rowspan="3" style="{{ $label }}vertical-align:top;">Notes</td>
                    <td colspan="7" rowspan="3" style="{{ $meta }}vertical-align:top;">{{ $workScheduleReference['notes'] ?? '—' }}</td>
                @endif
            </tr>
        @endforeach
        <tr>
            <td colspan="10" style="border:none;height:20px;"></td>
        </tr>
        <tr>
            <td colspan="10" style="{{ $footerSection }}">OT requests (this period)</td>
        </tr>
        @forelse ($otRequests as $ot)
            <tr>
                <td colspan="10" style="{{ $footerLine }}">{{ $ot['date'] }} — {{ $ot['type'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" style="{{ $footerLine }}">— None</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="10" style="border:none;height:20px;"></td>
        </tr>
        <tr>
            <td colspan="10" style="{{ $footerSection }}">Leave requests (this period)</td>
        </tr>
        @forelse ($leaveRequests as $leave)
            <tr>
                <td colspan="10" style="{{ $footerLine }}">{{ $leave['date'] }} — {{ $leave['type'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" style="{{ $footerLine }}">— None</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="10" style="border:none;height:20px;"></td>
        </tr>
        <tr>
            <td colspan="10" style="{{ $footerSection }}">Holidays (this month)</td>
        </tr>
        @forelse ($holidaysInMonth as $holiday)
            <tr>
                <td colspan="10" style="{{ $footerLine }}">{{ $holiday['date'] }} — {{ $holiday['name'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" style="{{ $footerLine }}">— None</td>
            </tr>
        @endforelse
    </tbody>
</table>
