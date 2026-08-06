<?php

namespace matevrd\ZtPermissions\Tests\Functional\Service;

use matevrd\ZtPermissions\Service\AuditModule;
use matevrd\ZtPermissions\Tests\Functional\ZtPermissionsFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class AuditModuleTest extends ZtPermissionsFunctionalTestCase
{
    private const REMOTE_ADDRESS = '203.0.113.7';

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'systemMaintainers' => [922],
        ],
    ];

    private AuditModule $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EffectivePermissions.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/AuditTargets.csv');
        $this->subject = $this->get(AuditModule::class);
    }

    #[Test]
    public function adminRiskIsDifferentiatedIntoThreeCases(): void
    {
        self::assertSame('admin-system-maintainer-access', $this->recordFor(922)['rule_id']);
        self::assertSame('admin-unrestricted-access-no-mount', $this->recordFor(911)['rule_id']);
        self::assertSame('admin-unrestricted-access', $this->recordFor(910)['rule_id']);
    }

    #[Test]
    public function allAdminCasesAreRatedAsHighRisk(): void
    {
        foreach ([922, 911, 910] as $userUid) {
            self::assertSame('hoch', $this->recordFor($userUid)['risk_level']);
        }
    }

    #[Test]
    public function pageScopeIsDerivedWhenNoContextIsGiven(): void
    {
        self::assertSame('pages#*', $this->recordFor(910)['page_context']);
        self::assertSame('pages#1', $this->recordFor(912)['page_context']);
        self::assertSame('pages#kein', $this->recordFor(923)['page_context']);
    }

    #[Test]
    public function explicitPageContextIsPreserved(): void
    {
        self::assertSame('be_users#42', $this->recordFor(910, 'be_users#42')['page_context']);
    }

    #[Test]
    public function timestampIsRecorded(): void
    {
        self::assertGreaterThan(0, (int)$this->recordFor(910)['tstamp']);
    }

    #[Test]
    public function ipAddressIsStoredAsHashOnly(): void
    {
        $entry = $this->recordFor(910);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $entry['ip_hash']);
        self::assertStringNotContainsString(self::REMOTE_ADDRESS, implode('|', $entry));
    }

    #[Test]
    public function ipHashIsNotAPlainHashOfTheAddress(): void
    {
        $ipHash = $this->recordFor(910)['ip_hash'];

        self::assertNotSame(hash('sha256', self::REMOTE_ADDRESS), $ipHash);
        self::assertNotSame(md5(self::REMOTE_ADDRESS), $ipHash);
    }

    #[Test]
    public function ipHashIsStableForSameAddressAndDiffersForOthers(): void
    {
        $first = $this->recordFor(910)['ip_hash'];
        $second = $this->recordFor(910)['ip_hash'];
        $other = $this->recordFor(910, '', '198.51.100.4')['ip_hash'];

        self::assertSame($first, $second);
        self::assertNotSame($first, $other);
    }

    private function recordFor(int $userUid, string $pageContext = '', string $remoteAddress = self::REMOTE_ADDRESS): array
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_zt_audit_log');
        $connection->truncate('tx_zt_audit_log');

        $this->subject->recordSecurityCriticalAction($userUid, $remoteAddress, 'login', $pageContext);

        $entries = $this->getAllRecords('tx_zt_audit_log');
        self::assertCount(1, $entries);

        return $entries[0];
    }
}
