<?php

use matevrd\ZtPermissions\Controller\AuditDashboardController;

return [
    'system_ztpermissionsaudit' => [
        'parent' => 'system',
        'position' => [],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/system/zt-permissions-audit',
        'iconIdentifier' => 'module-audit',
        'labels' => 'LLL:EXT:zt_permissions/Resources/Private/Language/locallang.xlf:module.title',
        'routes' => [
            '_default' => [
                'target' => AuditDashboardController::class . '::indexAction',
            ],
        ],
    ],
];
