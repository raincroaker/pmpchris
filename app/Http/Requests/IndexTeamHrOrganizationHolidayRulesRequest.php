<?php

namespace App\Http\Requests;

use App\Services\EmployeeTeamHrPagesAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class IndexTeamHrOrganizationHolidayRulesRequest extends FormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function authorize(): bool
    {
        return app(EmployeeTeamHrPagesAccess::class)->allows($this->user(), $this);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $fromRaw = substr((string) $this->input('date_from'), 0, 10);
            $toRaw = substr((string) $this->input('date_to'), 0, 10);

            $from = CarbonImmutable::parse($fromRaw)->startOfDay();
            $to = CarbonImmutable::parse($toRaw)->startOfDay();

            if ($from->diffInDays($to) > 731) {
                $v->errors()->add(
                    'date_to',
                    'The date range may not exceed two years.',
                );
            }
        });
    }

    public function dateFromYmd(): string
    {
        return substr((string) $this->validated('date_from'), 0, 10);
    }

    public function dateToYmd(): string
    {
        return substr((string) $this->validated('date_to'), 0, 10);
    }
}
