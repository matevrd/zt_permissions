<?php

return [
    'ctrl' => [
        'title' => 'Zero-Trust Site Zuordnung',
        'label' => 'site_identifier',
        'label_alt' => 'user_type, user_id',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'rootLevel' => 1,
        'adminOnly' => true,
        'iconfile' => 'EXT:zt_permissions/Resources/Public/Icons/module-audit.svg',
    ],

    'columns' => [
        'user_type' => [
            'label' => 'Benutzertyp',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'Backend-Benutzer', 'value' => 'be'],
                    ['label' => 'Frontend-Benutzer', 'value' => 'fe'],
                ],
                'default' => 'be',
            ],
        ],
        'user_id' => [
            'label' => 'Benutzer',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'itemsProcFunc' => \matevrd\ZtPermissions\TCA\SiteMappingItemsProvider::class . '->getUserItems',
            ]
        ],
        'site_identifier' => [
            'label' => 'Site',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'itemsProcFunc' => \matevrd\ZtPermissions\TCA\SiteMappingItemsProvider::class . '->getSiteItems',
            ]
        ]
    ],

    'types' => [
        '0' => ['showitem' => 'user_type, user_id, site_identifier'],
    ],
];
