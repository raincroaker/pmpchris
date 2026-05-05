<?php

namespace App\Enums;

enum AttendanceRecordStatus: string
{
    case Complete = 'complete';
    case Ongoing = 'ongoing';
    case Incomplete = 'incomplete';
}
