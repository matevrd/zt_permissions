<?php

namespace matevrd\ZtPermissions\Tests\Functional\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\MiddlewareStackResolver;

final class SiteAccessEnforcementMiddlewareTest extends SiteAccessEnforcementTestCase
{
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'systemMaintainers' => [964],
        ],
        'EXTENSIONS' => [
            'zt_permissions' => [
                'siteAccessEnforcementMode' => 'enforce',
            ],
        ],
    ];

    public static function accessMatrixProvider(): array
    {
        return [
            'main-Benutzer auf eigener Site'     => [960, 'main.example.com', true],
            'main-Benutzer auf fremder Site'     => [960, 'site-b.example.com', false],
            'site_b-Benutzer auf fremder Site'   => [961, 'main.example.com', false],
            'site_b-Benutzer auf eigener Site'   => [961, 'site-b.example.com', true],
            'Benutzer beider Sites auf main'     => [962, 'main.example.com', true],
            'Benutzer beider Sites auf site_b'   => [962, 'site-b.example.com', true],
            'Benutzer ohne Zuordnung auf main'   => [963, 'main.example.com', false],
            'Benutzer ohne Zuordnung auf site_b' => [963, 'site-b.example.com', false],
            'System-Maintainer auf main'         => [964, 'main.example.com', true],
            'System-Maintainer auf site_b'       => [964, 'site-b.example.com', true],
        ];
    }

    #[Test]
    #[DataProvider('accessMatrixProvider')]
    public function accessIsGrantedOnlyForAssignedSites(int $userUid, string $host, bool $expectedGranted): void
    {
        [$response, $wasPassedOn] = $this->processAs($userUid, $host);

        self::assertSame($expectedGranted, $wasPassedOn, 'Weiterleitung an den naechsten Handler');

        if (!$expectedGranted) {
            self::assertSame(303, $response->getStatusCode());
            self::assertStringContainsString('login', $response->getHeaderLine('location'));
        }
    }

    #[Test]
    public function deniedAccessIsRecordedWithSiteContext(): void
    {
        $this->processAs(960, 'site-b.example.com');

        $entries = $this->getAllRecords('tx_zt_audit_log');
        self::assertCount(1, $entries);
        self::assertSame('site_access_denied', $entries[0]['action_type']);
        self::assertSame('site#site_b', $entries[0]['page_context']);
    }

    #[Test]
    public function middlewareIsOrderedAfterAuthenticationAndBeforeModuleAudit(): void
    {
        $stack = array_keys((array)$this->get(MiddlewareStackResolver::class)->resolve('backend'));

        $executionOrder = array_reverse($stack);

        $authentication = array_search('typo3/cms-backend/authentication', $executionOrder, true);
        $enforcement = array_search('matevrd/zt-permissions/site-access-enforcement', $executionOrder, true);
        $moduleAudit = array_search('matevrd/zt-permissions/security-critical-module-audit', $executionOrder, true);

        self::assertIsInt($authentication, 'Native Backend-Authentifizierung nicht im Stack gefunden.');
        self::assertIsInt($enforcement, 'Die Middleware der Extension ist nicht registriert.');
        self::assertIsInt($moduleAudit, 'Die Audit-Middleware der Extension ist nicht registriert.');

        self::assertGreaterThan($authentication, $enforcement, 'Muss nach der Authentifizierung laufen.');
        self::assertLessThan($moduleAudit, $enforcement, 'Muss vor der Modul-Protokollierung laufen.');
    }
}
