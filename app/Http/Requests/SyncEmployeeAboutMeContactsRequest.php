<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Services\EmploymentWorkMutationAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncEmployeeAboutMeContactsRequest extends FormRequest
{
    public function authorize(EmploymentWorkMutationAccess $access): bool
    {
        $employee = $this->route('employee');
        if (! $employee instanceof Employee) {
            return false;
        }

        return $access->mayHrManageDirectoryVisibleEmployee($this, $employee);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'personal' => ['required', 'array', 'min:1', 'max:3'],
            'personal.*.channel_label' => ['required', 'string', Rule::in(['Mobile', 'Home', 'Work'])],
            'personal.*.contact_number' => ['required', 'string', 'max:50'],
            'personal.*.email' => ['nullable', 'email', 'max:255'],
            'personal.*.is_primary' => ['required', 'boolean'],

            'emergency' => ['required', 'array', 'min:1', 'max:3'],
            'emergency.*.channel_label' => ['nullable', 'string', Rule::in(['Mobile', 'Home', 'Work'])],
            'emergency.*.contact_person' => ['required', 'string', 'max:150'],
            'emergency.*.relationship' => ['nullable', 'string', 'max:100'],
            'emergency.*.contact_number' => ['required', 'string', 'max:50'],
            'emergency.*.email' => ['nullable', 'email', 'max:255'],
            'emergency.*.is_primary' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $personal = $this->input('personal', []);
            if (! is_array($personal) || $personal === []) {
                return;
            }
            $primaryCount = 0;
            foreach ($personal as $row) {
                if (is_array($row) && ! empty($row['is_primary'])) {
                    $primaryCount++;
                }
            }
            if ($primaryCount !== 1) {
                $validator->errors()->add('personal', 'Mark exactly one personal contact as primary.');
            }

            $emergency = $this->input('emergency', []);
            if (! is_array($emergency) || $emergency === []) {
                return;
            }
            $ePrimaryCount = 0;
            foreach ($emergency as $row) {
                if (is_array($row) && ! empty($row['is_primary'])) {
                    $ePrimaryCount++;
                }
            }
            if ($ePrimaryCount !== 1) {
                $validator->errors()->add('emergency', 'Mark exactly one emergency contact as primary.');
            }
        });
    }
}
