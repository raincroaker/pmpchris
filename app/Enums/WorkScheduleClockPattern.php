<?php

namespace App\Enums;

enum WorkScheduleClockPattern: string
{
    case SinglePair = 'single_pair';
    case SplitSessions = 'split_sessions';
}
