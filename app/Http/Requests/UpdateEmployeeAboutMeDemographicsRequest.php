<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Services\EmploymentWorkMutationAccess;
use App\Support\EmployeeDemographicsFormOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeAboutMeDemographicsRequest extends FormRequest
{
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
            'sex' => ['required', 'string', Rule::in(EmployeeDemographicsFormOptions::SEX_OPTIONS)],
            'civil_status' => ['nullable', 'string', Rule::in(EmployeeDemographicsFormOptions::CIVIL_STATUS_OPTIONS)],
            'nationality' => ['nullable', 'string', Rule::in(EmployeeDemographicsFormOptions::NATIONALITY_OPTIONS)],
            'religion' => ['nullable', 'string', Rule::in(EmployeeDemographicsFormOptions::RELIGION_OPTIONS)],
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
