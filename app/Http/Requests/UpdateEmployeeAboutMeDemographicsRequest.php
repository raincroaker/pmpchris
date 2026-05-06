<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Services\EmploymentWorkMutationAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeAboutMeDemographicsRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const SEX_OPTIONS = [
        'Male',
        'Female',
        'Other',
        'Prefer not to say',
    ];

    /**
     * @var list<string>
     */
    private const CIVIL_STATUS_OPTIONS = [
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
    private const NATIONALITY_OPTIONS = [
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
    private const RELIGION_OPTIONS = [
        'Catholic',
        'Protestant',
        'Muslim',
        'Hindu',
        'Buddhist',
        'Jewish',
        'Other',
        'Prefer not to say',
    ];

    public function authorize(EmploymentWorkMutationAccess $access): bool
    {
        $employee = $this->route('employee');
        if (! $employee instanceof Employee) {
            return false;
        }

        return $access->mayHrManageDirectoryVisibleEmployee($this, $employee);
    }

    protected function prepareForValidation(): void
    {
        $fields = ['civil_status', 'nationality', 'religion', 'religion_other'];
        foreach ($fields as $key) {
            $raw = $this->input($key);
            if ($raw !== null && $raw !== '' && is_string($raw) && trim($raw) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'birthdate' => ['required', 'date'],
            'sex' => ['required', 'string', Rule::in(self::SEX_OPTIONS)],
            'civil_status' => ['nullable', 'string', Rule::in(self::CIVIL_STATUS_OPTIONS)],
            'nationality' => ['nullable', 'string', Rule::in(self::NATIONALITY_OPTIONS)],
            'religion' => ['nullable', 'string', Rule::in(self::RELIGION_OPTIONS)],
            'religion_other' => ['nullable', 'string', 'max:255', 'required_if:religion,Other'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'religion_other.required_if' => 'Specify religion when Other is selected.',
        ];
    }
}
