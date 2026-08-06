<?php

defined('TYPO3') or die();

(function (): void {
    $extensionConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
    );
    $retentionDays = (int)($extensionConfiguration->get('zt_permissions', 'auditLogRetentionDays') ?? 180);
    if ($retentionDays < 1) {
        $retentionDays = 180;
    }

    $GLOBALS['TCA']['tx_scheduler_task']['types'][\TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask::class]['taskOptions']['tables']['tx_zt_audit_log'] = [
        'dateField' => 'tstamp',
        'expirePeriod' => $retentionDays,
    ];
})();
