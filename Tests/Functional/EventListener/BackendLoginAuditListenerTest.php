<?php

namespace matevrd\ZtPermissions\Tests\Functional\EventListener;

use matevrd\ZtPermissions\Tests\Functional\ZtPermissionsFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;

final class BackendLoginAuditListenerTest extends ZtPermissionsFunctionalTestCase
{
    private const REMOTE_ADDRESS = '203.0.113.7';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EffectivePermissions.csv');
    }

    #[Test]
    public function adminLoginIsLoggedAsHighRisk(): void
    {
        $this->dispatchLoginFor(910);

        $entry = $this->fetchSingleAuditLogEntry();
        self::assertSame('login', $entry['action_type']);
        self::assertSame(910, (int)$entry['user_id']);
        self::assertSame('hoch', $entry['risk_level']);
        self::assertSame('admin-unrestricted-access', $entry['rule_id']);
        self::assertSame('pages#*', $entry['page_context']);
    }

    #[Test]
    public function adminLoginWithoutDatabaseMountIsDistinguished(): void
    {
        $this->dispatchLoginFor(911);

        self::assertSame('admin-unrestricted-access-no-mount', $this->fetchSingleAuditLogEntry()['rule_id']);
    }

    #[Test]
    public function loginOfUserWithCriticalCombinationIsLoggedAsHighRisk(): void
    {
        $this->dispatchLoginFor(914);

        $entry = $this->fetchSingleAuditLogEntry();
        self::assertSame('hoch', $entry['risk_level']);
        self::assertSame('pages-write-beusers-access', $entry['rule_id']);
        self::assertSame('pages#1', $entry['page_context']);
    }

    #[Test]
    public function loginOfHarmlessUserIsLoggedWithLowRisk(): void
    {
        $this->dispatchLoginFor(912);

        $entry = $this->fetchSingleAuditLogEntry();
        self::assertSame('login', $entry['action_type']);
        self::assertSame('niedrig', $entry['risk_level']);
        self::assertSame('', $entry['rule_id']);
    }

    #[Test]
    public function remoteAddressIsStoredPseudonymisedOnly(): void
    {
        $this->dispatchLoginFor(912);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            $this->fetchSingleAuditLogEntry()['ip_hash']
        );
    }

    #[Test]
    public function differentRemoteAddressesProduceDifferentHashes(): void
    {
        $this->dispatchLoginFor(912, self::REMOTE_ADDRESS);
        $first = $this->fetchSingleAuditLogEntry()['ip_hash'];

        $this->getConnectionPool()->getConnectionForTable('tx_zt_audit_log')->truncate('tx_zt_audit_log');

        $this->dispatchLoginFor(912, '198.51.100.4');
        $second = $this->fetchSingleAuditLogEntry()['ip_hash'];

        self::assertNotSame($first, $second);
    }

    private function dispatchLoginFor(int $userUid, string $remoteAddress = self::REMOTE_ADDRESS): void
    {
        $backendUser = $this->setUpBackendUserWithLanguage($userUid);

        $serverParams = [
            'REMOTE_ADDR' => $remoteAddress,
            'HTTP_HOST' => 'typo3-testing.local',
        ];
        $request = new ServerRequest('https://typo3-testing.local/typo3/', 'GET', null, [], $serverParams);
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        $this->get(EventDispatcherInterface::class)
            ->dispatch(new AfterUserLoggedInEvent($backendUser, $request));
    }

    private function fetchSingleAuditLogEntry(): array
    {
        $entries = $this->getAllRecords('tx_zt_audit_log');
        self::assertCount(1, $entries, 'Es wurde genau ein Protokolleintrag erwartet.');

        return $entries[0];
    }
}
