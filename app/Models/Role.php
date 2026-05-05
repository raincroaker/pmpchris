<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const CODE_EMPLOYEE = 'employee';

    public const CODE_HR_HEAD = 'hr_head';

    public const CODE_HR_MANAGER = 'hr_manager';

    public const CODE_SUPER_ADMIN = 'super_admin';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    /**
     * Role codes allowed to mutate organization-structure catalog via Edit Structure endpoints.
     *
     * @return list<string>
     */
    public static function organizationStructureEditorRoleCodes(): array
    {
        return [
            self::CODE_SUPER_ADMIN,
            self::CODE_HR_HEAD,
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
