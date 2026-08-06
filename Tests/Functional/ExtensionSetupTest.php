<?php

namespace matevrd\ZtPermissions\Tests\Functional;

use matevrd\ZtPermissions\Domain\Repository\SiteMappingRepository;
use matevrd\ZtPermissions\Service\PermissionAnalyzer;
use PHPUnit\Framework\Attributes\Test;

final class ExtensionSetupTest extends ZtPermissionsFunctionalTestCase
{
    #[Test]
    public function extensionTablesAreCreated(): void
    {
        self::assertTrue(
            $this->getConnectionPool()->getConnectionForTable('tx_zt_audit_log')->createSchemaManager()->tableExists('tx_zt_audit_log')
        );
        self::assertTrue(
            $this->getConnectionPool()->getConnectionForTable('tx_zt_site_mapping')->createSchemaManager()->tableExists('tx_zt_site_mapping')
        );
    }

    #[Test]
    public function serviceLayerIsResolvableFromContainer(): void
    {
        self::assertInstanceOf(PermissionAnalyzer::class, $this->get(PermissionAnalyzer::class));
        self::assertInstanceOf(SiteMappingRepository::class, $this->get(SiteMappingRepository::class));
    }

    #[Test]
    public function siteMappingRepositoryReturnsEmptyResultWithoutFixtures(): void
    {
        $repository = $this->get(SiteMappingRepository::class);

        self::assertSame([], $repository->findSiteIdentifiersForUser(910, 'be'));
        self::assertFalse($repository->hasMappingForSite(910, 'be', 'main'));
    }
}
