<?php

namespace matevrd\ZtPermissions\Tests\Functional;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class ZtPermissionsFunctionalTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'matevrd/zt_permissions',
    ];

    protected function setUpBackendUserWithLanguage(int $userUid): BackendUserAuthentication
    {
        $backendUser = $this->setUpBackendUser($userUid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)
            ->createFromUserPreferences($backendUser);

        return $backendUser;
    }

    protected function writeSiteConfiguration(string $identifier, int $rootPageId, string $base): void
    {
        $this->get(SiteWriter::class)->createNewBasicSite($identifier, $rootPageId, $base);
    }
}
