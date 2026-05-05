<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeEmployment extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeEmploymentFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESIGNED = 'resigned';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUS_RETIRED = 'retired';

    public const STATUS_CONTRACT_ENDED = 'contract_ended';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_RESIGNED,
        self::STATUS_TERMINATED,
        self::STATUS_RETIRED,
        self::STATUS_CONTRACT_ENDED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'employee_id',
        'hire_date',
        'separation_date',
        'separation_reason',
        'employment_status',
        'is_current',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'separation_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmployeeEmployment $employment): void {
            if (blank($employment->uuid)) {
                $employment->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (EmployeeEmployment $employment): void {
            if ($employment->is_current && $employment->employee_id !== null) {
                self::query()
                    ->where('employee_id', $employment->employee_id)
                    ->when(
                        $employment->exists,
                        fn ($q) => $q->whereKeyNot($employment->getKey()),
                    )
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }
        });
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<EmployeeAffiliation, $this>
     */
    public function affiliations(): HasMany
    {
        return $this->hasMany(EmployeeAffiliation::class);
    }

    /**
     * @return HasMany<EmployeeAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    /**
     * @return HasMany<EmployeePosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }
}
