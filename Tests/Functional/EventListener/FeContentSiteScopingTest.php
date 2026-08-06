<?php

namespace matevrd\ZtPermissions\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

final class FeContentSiteScopingTest extends FeContentSiteScopingTestCase
{
    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'zt_permissions' => [
                'siteAccessEnforcementMode' => 'enforce',
            ],
        ],
    ];

    #[Test]
    public function showAtAnyLoginIsHiddenForUserOfAnotherSite(): void
    {
        self::assertStringNotContainsString('MAIN_SHOW_AT_ANY_LOGIN', $this->renderMainSubpageAs(921));
    }

    #[Test]
    public function showAtAnyLoginRemainsVisibleForUserOfSameSite(): void
    {
        self::assertStringContainsString('MAIN_SHOW_AT_ANY_LOGIN', $this->renderMainSubpageAs(920));
    }

    #[Test]
    public function showAtAnyLoginIsHiddenForAnonymousVisitors(): void
    {
        self::assertStringNotContainsString('MAIN_SHOW_AT_ANY_LOGIN', $this->renderMainSubpageAs(null));
    }

    #[Test]
    public function showAtAnyLoginRemainsVisibleWithinOwnSite(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://site-b.example.com/sub-b'),
            (new InternalRequestContext())->withFrontendUserId(921)
        );

        self::assertStringContainsString('SITEB_SHOW_AT_ANY_LOGIN', (string)$response->getBody());
    }

    #[Test]
    public function publicContentIsUnaffected(): void
    {
        self::assertStringContainsString('MAIN_PUBLIC', $this->renderMainSubpageAs(null));
        self::assertStringContainsString('MAIN_PUBLIC', $this->renderMainSubpageAs(920));
        self::assertStringContainsString('MAIN_PUBLIC', $this->renderMainSubpageAs(921));
    }

    #[Test]
    public function regularGroupRestrictedContentIsUnaffected(): void
    {
        self::assertStringContainsString('MAIN_GROUP_PRIVATE', $this->renderMainSubpageAs(920));
        self::assertStringNotContainsString('MAIN_GROUP_PRIVATE', $this->renderMainSubpageAs(null));
    }
}
