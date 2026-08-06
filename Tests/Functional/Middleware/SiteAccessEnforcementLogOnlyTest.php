<?php

namespace matevrd\ZtPermissions\Tests\Functional\Middleware;

use PHPUnit\Framework\Attributes\Test;

final class SiteAccessEnforcementLogOnlyTest extends SiteAccessEnforcementTestCase
{
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'systemMaintainers' => [964],
        ],
        'EXTENSIONS' => [
            'zt_permissions' => [
                'siteAccessEnforcementMode' => 'log-only',
            ],
        ],
    ];

    #[Test]
    public function accessIsNotDeniedWithoutEnforcement(): void
    {
        [, $wasPassedOn] = $this->processAs(960, 'site-b.example.com');

        self::assertTrue($wasPassedOn);
    }

    #[Test]
    public function violationIsStillRecordedUnderItsOwnActionType(): void
    {
        $this->processAs(960, 'site-b.example.com');

        $entries = $this->getAllRecords('tx_zt_audit_log');
        self::assertCount(1, $entries);
        self::assertSame('site_access_violation', $entries[0]['action_type']);
        self::assertSame('site#site_b', $entries[0]['page_context']);
    }

    #[Test]
    public function assignedUserProducesNoViolation(): void
    {
        [, $wasPassedOn] = $this->processAs(960, 'main.example.com');

        self::assertTrue($wasPassedOn);
        self::assertCount(0, $this->getAllRecords('tx_zt_audit_log'));
    }
}
