<?php

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['zt_permissions']
    = \matevrd\ZtPermissions\Hooks\SecurityCriticalActionHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['zt_permissions']
    = \matevrd\ZtPermissions\Hooks\SecurityCriticalActionHook::class;
$GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides']['EXT:backend/Resources/Private/Language/locallang.xlf'][]
    = 'EXT:zt_permissions/Resources/Private/Language/Overrides/backend.xlf';

// https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Events/Hooks/Index.html
// https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/DataHandler/Database/Index.html
