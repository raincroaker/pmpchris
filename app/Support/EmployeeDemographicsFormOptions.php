<?php

namespace App\Support;

/**
 * Canonical option lists for employee demographics (About Me + Add Employee wizard).
 * Keep in sync with `resources/js/pages/Employees/employeeStepOneOptions.ts`.
 */
final class EmployeeDemographicsFormOptions
{
    /**
     * @var list<string>
     */
    public const SEX_OPTIONS = [
        'Male',
        'Female',
        'Other',
        'Prefer not to say',
    ];

    /**
     * @var list<string>
     */
    public const CIVIL_STATUS_OPTIONS = [
        'Single',
        'Married',
        'Widowed',
        'Divorced',
        'Legally separated',
        'Annulled',
        'Domestic partnership',
        'Other',
    ];

    /**
     * @var list<string>
     */
    public const NATIONALITY_OPTIONS = [
        'Filipino',
        'American',
        'British',
        'Canadian',
        'Australian',
        'Chinese',
        'Japanese',
        'Indian',
        'Malaysian',
        'Singaporean',
        'Indonesian',
        'Thai',
        'Vietnamese',
        'Korean',
        'German',
        'French',
        'Spanish',
        'Italian',
        'Mexican',
        'Brazilian',
        'Other',
    ];

    /**
     * @var list<string>
     */
    public const RELIGION_OPTIONS = [
        'Catholic',
        'Protestant',
        'Muslim',
        'Hindu',
        'Buddhist',
        'Jewish',
        'Other',
        'Prefer not to say',
    ];
}
