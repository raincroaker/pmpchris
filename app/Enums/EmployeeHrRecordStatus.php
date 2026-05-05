<?php

namespace App\Enums;

enum EmployeeHrRecordStatus: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
