<?php

namespace matevrd\ZtPermissions\Tests\Functional\Hooks;

use matevrd\ZtPermissions\Tests\Functional\ZtPermissionsFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SecurityCriticalActionHookTest extends ZtPermissionsFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EffectivePermissions.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/AuditTargets.csv');
        $this->setUpBackendUserWithLanguage(910);
    }

    #[Test]
    public function creationOfBackendUserIsLogged(): void
    {
        $dataHandler = $this->processDatamap([
            'be_users' => [
                'NEW1234' => ['pid' => 0, 'username' => 'newly_created', 'password' => 'irrelevant'],
            ],
        ]);

        $newUid = (int)$dataHandler->substNEWwithIDs['NEW1234'];
        self::assertGreaterThan(0, $newUid);

        $entry = $this->fetchSingleAuditLogEntry();
        self::assertSame('record_created', $entry['action_type']);
        self::assertSame('be_users#' . $newUid, $entry['page_context']);
        self::assertSame(910, (int)$entry['user_id']);
    }

    #[Test]
    public function modificationOfBackendGroupIsLogged(): void
    {
        $this->processDatamap([
            'be_groups' => [
                900 => ['title' => 'zt_content_read_renamed'],
            ],
        ]);

        $entry = $this->fetchSingleAuditLogEntry();
        self::assertSame('record_updated', $entry['action_type']);
        self::assertSame('be_groups#900', $entry['page_context']);
    }

    #[Test]
    public function deletionOfBackendUserIsLogged(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], ['be_users' => [912 => ['delete' => 1]]]);
        $dataHandler->process_cmdmap();

        $entry = $this->fetchSingleAuditLogEntry();
        self::assertSame('record_deleted', $entry['action_type']);
        self::assertSame('be_users#912', $entry['page_context']);
    }

    #[Test]
    public function pagePermissionChangeIsLogged(): void
    {
        $this->processDatamap([
            'pages' => [
                10 => ['perms_everybody' => 31],
            ],
        ]);

        $entry = $this->fetchSingleAuditLogEntry();
        self::assertSame('page_permissions_changed', $entry['action_type']);
        self::assertSame('pages#10', $entry['page_context']);
    }

    #[Test]
    public function pageChangeWithoutPermissionFieldsIsNotLogged(): void
    {
        $this->processDatamap([
            'pages' => [
                10 => ['title' => 'Harmlose Titeländerung'],
            ],
        ]);

        self::assertSame(0, $this->countAuditLogEntries());
    }

    #[Test]
    public function changesToUnmonitoredTablesAreNotLogged(): void
    {
        $this->processDatamap([
            'tt_content' => [
                960 => ['header' => 'Geänderter Inhalt'],
            ],
        ]);

        self::assertSame(0, $this->countAuditLogEntries());
    }

    private function processDatamap(array $datamap): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();

        return $dataHandler;
    }

    private function fetchSingleAuditLogEntry(): array
    {
        $entries = $this->getAllRecords('tx_zt_audit_log');
        self::assertCount(1, $entries, 'Es wurde genau ein Protokolleintrag erwartet.');

        return $entries[0];
    }

    private function countAuditLogEntries(): int
    {
        return count($this->getAllRecords('tx_zt_audit_log'));
    }
}
