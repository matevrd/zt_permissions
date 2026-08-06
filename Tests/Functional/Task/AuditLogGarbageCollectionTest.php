<?php

namespace matevrd\ZtPermissions\Tests\Functional\Task;

use matevrd\ZtPermissions\Tests\Functional\ZtPermissionsFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask;

final class AuditLogGarbageCollectionTest extends ZtPermissionsFunctionalTestCase
{
    private const RETENTION_DAYS = 7;

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'zt_permissions' => [
                'auditLogRetentionDays' => self::RETENTION_DAYS,
            ],
        ],
    ];

    #[Test]
    public function retentionPeriodIsRegisteredFromExtensionConfiguration(): void
    {
        $options = $GLOBALS['TCA']['tx_scheduler_task']['types'][TableGarbageCollectionTask::class]['taskOptions']['tables']['tx_zt_audit_log'] ?? null;

        self::assertIsArray($options, 'Die Tabelle ist nicht fuer die Garbage Collection registriert.');
        self::assertSame('tstamp', $options['dateField']);
        self::assertSame(self::RETENTION_DAYS, $options['expirePeriod']);
    }

    #[Test]
    public function entriesOlderThanTheRetentionPeriodAreRemoved(): void
    {
        $this->insertAuditLogEntry(910, 'veraltet', self::RETENTION_DAYS + 3);
        $this->insertAuditLogEntry(911, 'aktuell', self::RETENTION_DAYS - 3);

        self::assertSame(2, $this->countAuditLogEntries());

        $this->runGarbageCollection();

        self::assertSame(['aktuell'], $this->remainingActionTypes());
    }

    #[Test]
    public function recentEntriesAreKept(): void
    {
        $this->insertAuditLogEntry(910, 'heute', 0);
        $this->insertAuditLogEntry(911, 'gestern', 1);

        $this->runGarbageCollection();

        self::assertSame(2, $this->countAuditLogEntries());
    }

    private function runGarbageCollection(): void
    {
        $task = new TableGarbageCollectionTask();
        $task->setTaskParameters([
            'all_tables' => false,
            'selected_tables' => 'tx_zt_audit_log',
            'number_of_days' => 0,
        ]);

        self::assertTrue($task->execute());
    }

    private function insertAuditLogEntry(int $userUid, string $actionType, int $ageInDays): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_zt_audit_log')->insert(
            'tx_zt_audit_log',
            [
                'user_id' => $userUid,
                'tstamp' => $GLOBALS['EXEC_TIME'] - ($ageInDays * 86400),
                'ip_hash' => str_repeat('a', 64),
                'action_type' => $actionType,
                'risk_level' => 'niedrig',
                'rule_id' => '',
                'rule_description' => '',
                'page_context' => 'pages#*',
            ],
            [
                Connection::PARAM_INT,
                Connection::PARAM_INT,
                Connection::PARAM_STR,
                Connection::PARAM_STR,
                Connection::PARAM_STR,
                Connection::PARAM_STR,
                Connection::PARAM_STR,
                Connection::PARAM_STR,
            ]
        );
    }

    private function countAuditLogEntries(): int
    {
        return count($this->getAllRecords('tx_zt_audit_log'));
    }

    private function remainingActionTypes(): array
    {
        return array_column($this->getAllRecords('tx_zt_audit_log'), 'action_type');
    }
}
