<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Multiple address rows per employee.
 *
 * type is current|permanent (present vs on-file or registered address). is_primary is product-defined
 * (e.g. mark mailing or default rows); permanent and current may both be primary, neither, or one only—enforce in app when persisting if needed.
 */
class EmployeeAddress extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeAddressFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'type',
        'address_line_1',
        'address_line_2',
        'barangay',
        'city',
        'province',
        'zip_code',
        'country',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
