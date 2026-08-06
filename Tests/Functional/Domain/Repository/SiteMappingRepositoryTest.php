<?php

namespace matevrd\ZtPermissions\Tests\Functional\Domain\Repository;

use matevrd\ZtPermissions\Domain\Repository\SiteMappingRepository;
use matevrd\ZtPermissions\Tests\Functional\ZtPermissionsFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SiteMappingRepositoryTest extends ZtPermissionsFunctionalTestCase
{
    private SiteMappingRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteMapping.csv');
        $this->subject = $this->get(SiteMappingRepository::class);
    }

    #[Test]
    public function multipleSiteAssignmentsAreResolvedForOneUser(): void
    {
        self::assertEqualsCanonicalizing(
            ['main', 'site_b'],
            $this->subject->findSiteIdentifiersForUser(919, 'be')
        );
    }

    #[Test]
    public function backendAndFrontendAssignmentsAreKeptSeparate(): void
    {
        self::assertEqualsCanonicalizing(['main', 'site_b'], $this->subject->findSiteIdentifiersForUser(919, 'be'));
        self::assertSame(['site_b'], $this->subject->findSiteIdentifiersForUser(919, 'fe'));
    }

    #[Test]
    public function hasMappingForSiteDistinguishesAssignedFromUnassignedSites(): void
    {
        self::assertTrue($this->subject->hasMappingForSite(910, 'be', 'main'));
        self::assertFalse($this->subject->hasMappingForSite(910, 'be', 'site_b'));
    }

    #[Test]
    public function userWithoutAssignmentResolvesToEmptyList(): void
    {
        self::assertSame([], $this->subject->findSiteIdentifiersForUser(911, 'be'));
        self::assertFalse($this->subject->hasMappingForSite(911, 'be', 'main'));
    }

    #[Test]
    public function deletedAssignmentsAreIgnored(): void
    {
        self::assertSame([], $this->subject->findSiteIdentifiersForUser(917, 'be'));
    }

    #[Test]
    public function lookupResultIsCachedWithinTheRequest(): void
    {
        self::assertSame(['main'], $this->subject->findSiteIdentifiersForUser(910, 'be'));

        $this->getConnectionPool()
            ->getConnectionForTable('tx_zt_site_mapping')
            ->delete('tx_zt_site_mapping', ['uid' => 930]);

        self::assertSame(['main'], $this->subject->findSiteIdentifiersForUser(910, 'be'));
    }

    #[Test]
    public function negativeLookupResultIsCachedAsWell(): void
    {
        self::assertSame([], $this->subject->findSiteIdentifiersForUser(911, 'be'));

        $this->getConnectionPool()
            ->getConnectionForTable('tx_zt_site_mapping')
            ->insert('tx_zt_site_mapping', ['uid' => 947, 'user_id' => 911, 'user_type' => 'be', 'site_identifier' => 'main']);

        self::assertSame([], $this->subject->findSiteIdentifiersForUser(911, 'be'));
    }
}
