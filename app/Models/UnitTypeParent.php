<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UnitTypeParent extends Model
{
    protected $table = 'unit_type_parents';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_unit_type_id',
        'child_unit_type_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UnitTypeParent $row): void {
            $row->created_at ??= Carbon::now();
        });
    }

    /**
     * @return BelongsTo<UnitType, $this>
     */
    public function parentUnitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'parent_unit_type_id');
    }

    /**
     * @return BelongsTo<UnitType, $this>
     */
    public function childUnitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'child_unit_type_id');
    }
}
