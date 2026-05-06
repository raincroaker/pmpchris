<?php

return [
    'organization_code' => env('PROD_BOOTSTRAP_ORGANIZATION_CODE', env('DEFAULT_ORGANIZATION_CODE', 'PMPC')),

    'super_admin_email' => env('PROD_BOOTSTRAP_SUPER_ADMIN_EMAIL'),

    'hr_head_email' => env('PROD_BOOTSTRAP_HR_HEAD_EMAIL'),

    'super_admin_password' => env('PROD_BOOTSTRAP_SUPER_ADMIN_PASSWORD'),

    'hr_head_password' => env('PROD_BOOTSTRAP_HR_HEAD_PASSWORD'),

    'super_admin_id_number' => env('PROD_BOOTSTRAP_SUPER_ADMIN_ID_NUMBER', 'PMPC-BOOT-001'),

    'hr_head_id_number' => env('PROD_BOOTSTRAP_HR_HEAD_ID_NUMBER', 'PMPC-BOOT-002'),
];
