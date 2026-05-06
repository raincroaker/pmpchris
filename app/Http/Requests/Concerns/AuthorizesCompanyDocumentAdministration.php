<?php

namespace App\Http\Requests\Concerns;

use App\Models\Role;
use App\Models\User;

trait AuthorizesCompanyDocumentAdministration
{
    protected function userCanAdministerCompanyDocuments(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]);
    }
}
