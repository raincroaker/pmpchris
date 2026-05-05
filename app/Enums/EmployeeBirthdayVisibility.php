<?php

namespace App\Enums;

/**
 * Where an employee’s birthday may appear once calendar wiring exists (not on the company operational calendar).
 * New rows default to {@see self::Team} at the database level unless overridden.
 */
enum EmployeeBirthdayVisibility: string
{
    /** Hidden from branch/team birthday surfaces. */
    case Private = 'private';
    /** Visible on team/unit calendars for units the employee belongs to. */
    case Team = 'team';
    /** Visible on branch calendar(s) for the employee’s branch. */
    case Branch = 'branch';
}
