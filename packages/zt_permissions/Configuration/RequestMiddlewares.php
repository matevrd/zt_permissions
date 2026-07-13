<?php

return [
    'backend' => [
        'matevrd/zt-permissions/security-critical-module-audit' => [
            'target' => \matevrd\ZtPermissions\Middleware\SecurityCriticalModuleAuditMiddleware::class,
            'after' => [
                'typo3/cms-backend/authentication',
                'typo3/cms-backend/backend-module-validator',
            ],
        ],
    ],
];
