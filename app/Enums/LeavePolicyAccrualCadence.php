<?php

namespace App\Enums;

enum LeavePolicyAccrualCadence: string
{
    case Monthly = 'monthly';
    case PayPeriod = 'pay_period';
}
