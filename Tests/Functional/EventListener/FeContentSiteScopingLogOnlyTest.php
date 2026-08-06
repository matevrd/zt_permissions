<?php

namespace matevrd\ZtPermissions\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;

final class FeContentSiteScopingLogOnlyTest extends FeContentSiteScopingTestCase
{
    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'zt_permissions' => [
                'siteAccessEnforcementMode' => 'log-only',
            ],
        ],
    ];

    #[Test]
    public function showAtAnyLoginIsVisibleAcrossSitesWithoutEnforcement(): void
    {
        self::assertStringContainsString('MAIN_SHOW_AT_ANY_LOGIN', $this->renderMainSubpageAs(921));
    }

    #[Test]
    public function showAtAnyLoginRemainsHiddenForAnonymousVisitorsWithoutEnforcement(): void
    {
        self::assertStringNotContainsString('MAIN_SHOW_AT_ANY_LOGIN', $this->renderMainSubpageAs(null));
    }
}
