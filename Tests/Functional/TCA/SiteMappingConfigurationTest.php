<?php

namespace matevrd\ZtPermissions\Tests\Functional\TCA;

use matevrd\ZtPermissions\TCA\SiteMappingItemsProvider;
use matevrd\ZtPermissions\Tests\Functional\ZtPermissionsFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SiteMappingConfigurationTest extends ZtPermissionsFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SiteMapping.csv');
    }

    #[Test]
    public function tableIsRestrictedToAdministratorsOnRootLevel(): void
    {
        $ctrl = $GLOBALS['TCA']['tx_zt_site_mapping']['ctrl'];

        self::assertSame(1, $ctrl['rootLevel']);
        self::assertTrue($ctrl['adminOnly']);
        self::assertSame('deleted', $ctrl['delete']);
    }

    #[Test]
    public function userAndSiteFieldsAreFilledAtRuntime(): void
    {
        $columns = $GLOBALS['TCA']['tx_zt_site_mapping']['columns'];

        self::assertSame(
            SiteMappingItemsProvider::class . '->getUserItems',
            $columns['user_id']['config']['itemsProcFunc']
        );
        self::assertSame(
            SiteMappingItemsProvider::class . '->getSiteItems',
            $columns['site_identifier']['config']['itemsProcFunc']
        );
        self::assertSame('reload', $columns['user_type']['onChange']);
    }

    #[Test]
    public function userItemsAreTakenFromBackendUsersByDefault(): void
    {
        $params = ['items' => [], 'row' => ['user_type' => 'be']];
        (new SiteMappingItemsProvider())->getUserItems($params);

        $values = array_column($params['items'], 'value');
        self::assertContains(910, $values);
        self::assertContains(919, $values);
        self::assertNotContains(920, $values, 'Frontend-Benutzer duerfen hier nicht erscheinen.');
    }

    #[Test]
    public function userItemsAreTakenFromFrontendUsersWhenSelected(): void
    {
        $params = ['items' => [], 'row' => ['user_type' => 'fe']];
        (new SiteMappingItemsProvider())->getUserItems($params);

        $values = array_column($params['items'], 'value');
        self::assertContains(920, $values);
        self::assertNotContains(910, $values, 'Backend-Benutzer duerfen hier nicht erscheinen.');
    }

    #[Test]
    public function siteItemsReflectConfiguredSites(): void
    {
        $this->writeSiteConfiguration('main', 1, 'https://main.example.com/');
        $this->writeSiteConfiguration('site_b', 2, 'https://site-b.example.com/');

        $params = ['items' => []];
        (new SiteMappingItemsProvider())->getSiteItems($params);

        self::assertEqualsCanonicalizing(['main', 'site_b'], array_column($params['items'], 'value'));
    }
}
