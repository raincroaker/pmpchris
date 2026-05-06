<?php

namespace App\Models;

use Database\Factories\UnitTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitType extends Model
{
    /** @use HasFactory<UnitTypeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
        'can_be_root',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_be_root' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<OrganizationalUnit, $this>
     */
    public function organizationalUnits(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class);
    }

    /**
     * Allowed child unit types (this type as parent).
     *
     * @return HasMany<UnitTypeParent, $this>
     */
    public function allowedChildLinks(): HasMany
    {
        return $this->hasMany(UnitTypeParent::class, 'parent_unit_type_id');
    }

    /**
     * Allowed parent unit types (this type as child).
     *
     * @return HasMany<UnitTypeParent, $this>
     */
    public function allowedParentLinks(): HasMany
    {
        return $this->hasMany(UnitTypeParent::class, 'child_unit_type_id');
    }
}
