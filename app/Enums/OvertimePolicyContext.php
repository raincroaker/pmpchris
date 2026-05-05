<?php

namespace App\Enums;

enum OvertimePolicyContext: string
{
    case OrdinaryWeekday = 'ordinary_weekday';
    case RestDay = 'rest_day';
    case RegularHoliday = 'regular_holiday';
    case SpecialHoliday = 'special_holiday';
}
