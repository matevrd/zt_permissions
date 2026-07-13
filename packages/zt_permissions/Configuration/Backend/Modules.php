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

//https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Backend/BackendModules/ModuleConfiguration/Index.html
//https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/ExtensionArchitecture/HowTo/BackendModule/BackendModuleApi/Index.html
