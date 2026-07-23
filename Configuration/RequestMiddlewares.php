<?php

return [
    'backend' => [
        'matevrd/zt-permissions/site-access-enforcement' => [
            'target' => \matevrd\ZtPermissions\Middleware\SiteAccessEnforcementMiddleware::class,
            'after' => [
                'typo3/cms-backend/authentication',
            ],
            'before' => [
                'matevrd/zt-permissions/security-critical-module-audit',
            ],
        ],
        'matevrd/zt-permissions/security-critical-module-audit' => [
            'target' => \matevrd\ZtPermissions\Middleware\SecurityCriticalModuleAuditMiddleware::class,
            'after' => [
                'typo3/cms-backend/authentication',
                'typo3/cms-backend/backend-module-validator',
            ],
        ],
    ],
];
