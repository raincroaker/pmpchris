<?php

use App\Models\Role;

return [

    /*
    |--------------------------------------------------------------------------
    | Branch picker (post-login)
    |--------------------------------------------------------------------------
    |
    | When enabled, users with any of picker_role_codes must choose a branch
    | root (organizational unit) before using routes protected by branch.selected
    | middleware. Session keys: selected_branch_id, selected_branch_meta.
    |
    */

    'branch_picker_enabled' => filter_var(env('BRANCH_PICKER_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'picker_role_codes' => [
        Role::CODE_HR_HEAD,
        Role::CODE_HR_MANAGER,
        Role::CODE_SUPER_ADMIN,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default organization for branch lists
    |--------------------------------------------------------------------------
    |
    | Used to scope selectable branch roots. When null, the first active
    | organization by id is used.
    |
    */

    'default_organization_code' => env('DEFAULT_ORGANIZATION_CODE', 'PMPC'),

];
