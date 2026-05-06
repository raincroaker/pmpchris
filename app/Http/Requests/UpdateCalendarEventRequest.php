<?php

namespace App\Http\Requests;

use App\Models\CalendarEventCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class UpdateCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'ends_at' => ['required', 'date_format:Y-m-d H:i', 'after_or_equal:starts_at'],
            'is_all_day' => ['required', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:calendar_event_categories,id'],
            'recurrence' => ['nullable', 'array'],
            'unit_id' => ['nullable', 'integer'],
            'apply_to' => ['nullable', 'string', 'in:entire_series'],
            'occurrence_date' => ['nullable', 'date_format:Y-m-d'],
            'restore_occurrence_dates' => ['nullable', 'array'],
            'restore_occurrence_dates.*' => ['date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title', '')),
            'starts_at' => trim((string) ($this->input('starts_at', $this->input('startsAt', '')))),
            'ends_at' => trim((string) ($this->input('ends_at', $this->input('endsAt', '')))),
            'is_all_day' => $this->boolean('is_all_day', $this->boolean('isAllDay')),
            'location' => trim((string) $this->input('location', '')),
            'details' => trim((string) ($this->input('details', $this->input('notes', '')))),
            'category_id' => $this->input('category_id', $this->input('categoryId')),
            'unit_id' => $this->input('unit_id', $this->input('unitId')),
            'recurrence' => $this->input('recurrence'),
            'apply_to' => trim((string) $this->input('apply_to', $this->input('applyTo', 'entire_series'))),
            'occurrence_date' => trim((string) $this->input('occurrence_date', $this->input('occurrenceDate', ''))),
            'restore_occurrence_dates' => $this->input(
                'restore_occurrence_dates',
                $this->input('restoreOccurrenceDates', []),
            ),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $organization = app(BranchContextService::class)->defaultOrganization();
            if ($organization === null) {
                $validator->errors()->add('organization', 'Default organization is unavailable.');

                return;
            }

            $validCategory = CalendarEventCategory::query()
                ->whereKey($this->categoryId())
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->exists();
            if (! $validCategory) {
                $validator->errors()->add('category_id', 'Selected category is invalid.');
            }

            $this->validateRecurrenceShape($validator);

            if ($this->applyTo() !== 'entire_series') {
                $validator->errors()->add('apply_to', 'Only entire-series updates are supported.');
            }
        });
    }

    private function validateRecurrenceShape(Validator $validator): void
    {
        $recurrence = $this->recurrence();
        if ($recurrence === null) {
            return;
        }

        $frequency = Arr::get($recurrence, 'frequency');
        if (! in_array($frequency, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            $validator->errors()->add('recurrence.frequency', 'Recurrence frequency is invalid.');
        }

        $interval = (int) Arr::get($recurrence, 'interval', 0);
        if ($interval < 1 || $interval > 365) {
            $validator->errors()->add('recurrence.interval', 'Recurrence interval must be between 1 and 365.');
        }

        $endsType = Arr::get($recurrence, 'ends.type');
        if (! in_array($endsType, ['never', 'until', 'count'], true)) {
            $validator->errors()->add('recurrence.ends.type', 'Recurrence end type is invalid.');

            return;
        }

        if ($endsType === 'until') {
            $until = Arr::get($recurrence, 'ends.date');
            if (! is_string($until) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) !== 1) {
                $validator->errors()->add('recurrence.ends.date', 'Recurrence until date is invalid.');
            } else {
                $startDate = substr($this->startsAt(), 0, 10);
                if ($until < $startDate) {
                    $validator->errors()->add('recurrence.ends.date', 'Recurrence until date cannot be before start date.');
                }
            }
        }

        if ($endsType === 'count') {
            $count = (int) Arr::get($recurrence, 'ends.count', 0);
            if ($count < 1 || $count > 500) {
                $validator->errors()->add('recurrence.ends.count', 'Recurrence count must be between 1 and 500.');
            }
        }

        if ($frequency === 'weekly') {
            $days = Arr::get($recurrence, 'byWeekday');
            if (! is_array($days) || $days === []) {
                $validator->errors()->add('recurrence.byWeekday', 'Weekly recurrence requires at least one weekday.');

                return;
            }

            foreach ($days as $day) {
                $weekday = (int) $day;
                if ($weekday < 0 || $weekday > 6) {
                    $validator->errors()->add('recurrence.byWeekday', 'Weekly recurrence day must be between 0 and 6.');
                    break;
                }
            }
        }
    }

    public function eventTitle(): string
    {
        return (string) $this->string('title');
    }

    public function startsAt(): string
    {
        return (string) $this->string('starts_at');
    }

    public function endsAt(): string
    {
        return (string) $this->string('ends_at');
    }

    public function isAllDay(): bool
    {
        return (bool) $this->boolean('is_all_day');
    }

    public function location(): ?string
    {
        $value = (string) $this->string('location');

        return $value === '' ? null : $value;
    }

    public function details(): ?string
    {
        $value = (string) $this->string('details');

        return $value === '' ? null : $value;
    }

    public function categoryId(): int
    {
        return (int) $this->integer('category_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recurrence(): ?array
    {
        $value = $this->input('recurrence');

        return is_array($value) ? $value : null;
    }

    public function teamUnitId(): ?int
    {
        $value = $this->input('unit_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function applyTo(): string
    {
        return 'entire_series';
    }

    public function occurrenceDate(): ?string
    {
        $value = trim((string) $this->input('occurrence_date', ''));

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    public function restoreOccurrenceDates(): array
    {
        $rows = $this->input('restore_occurrence_dates');
        if (! is_array($rows)) {
            return [];
        }

        $dates = array_values(array_unique(array_filter(
            array_map(
                fn (mixed $row): string => trim((string) $row),
                $rows,
            ),
            fn (string $row): bool => $row !== '',
        )));

        return array_values(array_filter(
            $dates,
            fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1,
        ));
    }
}
