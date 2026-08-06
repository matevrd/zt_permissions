<?php

namespace matevrd\ZtPermissions\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class PageCacheSiteScopingTest extends FeContentSiteScopingTestCase
{
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'pages' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
        'EXTENSIONS' => [
            'zt_permissions' => [
                'siteAccessEnforcementMode' => 'enforce',
            ],
        ],
    ];

    #[Test]
    public function pageCacheIsActiveInThisTestInstance(): void
    {
        self::assertSame(0, $this->countPageCacheEntries());

        $this->renderMainSubpageAs(920);

        self::assertGreaterThan(0, $this->countPageCacheEntries());
    }

    #[Test]
    public function separateCacheEntriesAreCreatedPerSiteAssignment(): void
    {
        $this->renderMainSubpageAs(920);
        self::assertSame(1, $this->countPageCacheEntries());

        $this->renderMainSubpageAs(921);
        self::assertSame(2, $this->countPageCacheEntries());
    }

    #[Test]
    public function cachedContentDoesNotLeakToUnassignedUser(): void
    {
        $mapped = $this->renderMainSubpageAs(920);
        $unmapped = $this->renderMainSubpageAs(921);

        self::assertStringContainsString('MAIN_SHOW_AT_ANY_LOGIN', $mapped);
        self::assertStringNotContainsString('MAIN_SHOW_AT_ANY_LOGIN', $unmapped);
    }

    #[Test]
    public function cacheIsInvalidatedWhenSiteMappingChanges(): void
    {
        $this->renderMainSubpageAs(920);
        self::assertGreaterThan(0, $this->countPageCacheEntries());

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendAdmin.csv');
        $this->setUpBackendUser(1);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['tx_zt_site_mapping' => [941 => ['site_identifier' => 'main']]],
            []
        );
        $dataHandler->process_datamap();

        self::assertSame(0, $this->countPageCacheEntries());
    }

    private function countPageCacheEntries(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('cache_pages')
            ->count('*', 'cache_pages', []);
    }
}
