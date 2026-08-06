<?php

namespace matevrd\ZtPermissions\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;

final class PageCacheSiteScopingLogOnlyTest extends FeContentSiteScopingTestCase
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
                'siteAccessEnforcementMode' => 'log-only',
            ],
        ],
    ];

    #[Test]
    public function bothUsersShareASingleCacheEntryWithoutEnforcement(): void
    {
        $this->renderMainSubpageAs(920);
        self::assertSame(1, $this->countPageCacheEntries());

        $this->renderMainSubpageAs(921);
        self::assertSame(1, $this->countPageCacheEntries());
    }

    private function countPageCacheEntries(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('cache_pages')
            ->count('*', 'cache_pages', []);
    }
}
