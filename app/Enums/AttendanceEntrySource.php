<?php

namespace App\Enums;

enum AttendanceEntrySource: string
{
    case Manual = 'manual';
    case Device = 'device';
    case Import = 'import';
}
