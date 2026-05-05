<?php

namespace App\Http\Requests\Concerns;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;

trait AuthorizesWorkScheduleTemplateMutation
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        if (! $user->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ])) {
            return false;
        }

        return app(BranchContextService::class)->defaultOrganization() instanceof Organization;
    }
}
