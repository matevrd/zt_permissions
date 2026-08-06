<?php

namespace matevrd\ZtPermissions\Tests\Functional\EventListener;

use matevrd\ZtPermissions\Tests\Functional\ZtPermissionsFunctionalTestCase;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

abstract class FeContentSiteScopingTestCase extends ZtPermissionsFunctionalTestCase
{
    protected const TYPOSCRIPT_FIXTURE = 'EXT:zt_permissions/Tests/Functional/Fixtures/TypoScript/Rendering.typoscript';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SiteScoping.csv');
        $this->writeSiteConfiguration('main', 1, 'https://main.example.com/');
        $this->writeSiteConfiguration('site_b', 2, 'https://site-b.example.com/');
        $this->setUpFrontendRootPage(1, ['setup' => [self::TYPOSCRIPT_FIXTURE]]);
        $this->setUpFrontendRootPage(2, ['setup' => [self::TYPOSCRIPT_FIXTURE]]);
    }

    protected function renderMainSubpageAs(?int $frontendUserId): string
    {
        $context = new InternalRequestContext();
        if ($frontendUserId !== null) {
            $context = $context->withFrontendUserId($frontendUserId);
        }

        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://main.example.com/sub-a'),
            $context
        );

        return (string)$response->getBody();
    }
}
