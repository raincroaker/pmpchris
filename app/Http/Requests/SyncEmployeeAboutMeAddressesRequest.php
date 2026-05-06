<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Services\EmploymentWorkMutationAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncEmployeeAboutMeAddressesRequest extends FormRequest
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
        $rules = [];

        foreach (['current', 'permanent'] as $p) {
            $rules[$p] = ['nullable', 'array'];
            $rules[$p.'.address_line_1'] = ['required_with:'.$p, 'string', 'max:255'];
            $rules[$p.'.address_line_2'] = ['nullable', 'string', 'max:255'];
            $rules[$p.'.barangay'] = ['required_with:'.$p, 'string', 'max:120'];
            $rules[$p.'.barangay_code'] = ['nullable', 'string', 'max:10'];
            $rules[$p.'.city'] = ['required_with:'.$p, 'string', 'max:120'];
            $rules[$p.'.city_code'] = ['nullable', 'string', 'max:10'];
            $rules[$p.'.province'] = ['required_with:'.$p, 'string', 'max:120'];
            $rules[$p.'.province_code'] = ['nullable', 'string', 'max:10'];
            $rules[$p.'.zip_code'] = ['required_with:'.$p, 'string', 'max:20'];
            $rules[$p.'.country'] = ['required_with:'.$p, 'string', 'max:100'];
            $rules[$p.'.is_primary'] = ['required_with:'.$p, 'boolean'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        foreach (['current', 'permanent'] as $key) {
            $raw = $this->input($key);
            if (! is_array($raw) || $raw === []) {
                $this->merge([$key => null]);

                continue;
            }
            /** @var array<string, mixed> $raw */
            $countryRaw = $raw['country'] ?? null;
            if ($countryRaw === null || $countryRaw === '' || (is_string($countryRaw) && trim($countryRaw) === '')) {
                $raw['country'] = 'Philippines';
            }

            $this->merge([$key => $raw]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $currentPresent = $this->hasNonEmptyAddress('current');
            $permanentPresent = $this->hasNonEmptyAddress('permanent');
            if (! $currentPresent && ! $permanentPresent) {
                $validator->errors()->add(
                    'current',
                    'Provide at least a current address or a permanent address.',
                );
            }
        });
    }

    private function hasNonEmptyAddress(string $key): bool
    {
        $raw = $this->input($key);
        if (! is_array($raw)) {
            return false;
        }

        foreach (['address_line_1', 'barangay', 'city', 'province', 'zip_code'] as $field) {
            $v = $raw[$field] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
        }

        foreach (['province_code', 'city_code', 'barangay_code'] as $codeField) {
            $c = $raw[$codeField] ?? null;
            if (is_string($c) && trim($c) !== '') {
                return true;
            }
        }

        return false;
    }
}
