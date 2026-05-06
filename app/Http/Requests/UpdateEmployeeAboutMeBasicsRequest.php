<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Services\EmploymentWorkMutationAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateEmployeeAboutMeBasicsRequest extends FormRequest
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
        $this->merge([
            'remove_avatar' => $this->boolean('remove_avatar'),
        ]);

        $attendanceRaw = $this->input('attendance_id');
        if ($attendanceRaw === '' || ($attendanceRaw !== null && is_string($attendanceRaw) && trim($attendanceRaw) === '')) {
            $this->merge(['attendance_id' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        if (! $employee instanceof Employee) {
            return [];
        }

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'id_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'id_number')
                    ->ignore($employee->id)
                    ->whereNull('deleted_at'),
            ],
            'attendance_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'attendance_id')
                    ->ignore($employee->id)
                    ->whereNull('deleted_at'),
            ],
            'avatar' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(3 * 1024)],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }
}
