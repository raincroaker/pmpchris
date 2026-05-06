<?php

namespace App\Models;

use Database\Factories\EmployeeContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Multiple contact rows per employee (UI: add-contact repeater).
 *
 * category personal|emergency drives which fields apply: personal uses type mobile|home|work and keeps
 * contact_person and relationship null; emergency uses contact_person and relationship, with type optional (mobile or null).
 * is_primary: primary personal number among personal rows, or primary emergency when several emergency rows exist.
 * Enforce at-most-one-primary rules in validation/services, not in the database.
 */
class EmployeeContact extends Model
{
    /** @use HasFactory<EmployeeContactFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'category',
        'type',
        'contact_person',
        'relationship',
        'contact_number',
        'email',
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
