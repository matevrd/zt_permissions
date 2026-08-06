<?php

namespace matevrd\ZtPermissions\Tests\Functional\Service;

use matevrd\ZtPermissions\Service\PermissionAnalyzer;
use matevrd\ZtPermissions\Tests\Functional\ZtPermissionsFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class PermissionAnalyzerTest extends ZtPermissionsFunctionalTestCase
{
    private PermissionAnalyzer $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EffectivePermissions.csv');
        $this->subject = $this->get(PermissionAnalyzer::class);
    }

    #[Test]
    public function multipleGroupMembershipsAreAggregatedAdditively(): void
    {
        $permissions = $this->subject->analyseUser(914);

        self::assertEqualsCanonicalizing(['pages', 'be_users'], $permissions->readTables);
        self::assertEqualsCanonicalizing(['pages'], $permissions->writeTables);
    }

    #[Test]
    public function writePermissionImpliesReadPermission(): void
    {
        $tables = $this->subject->analyseGroup(901);

        self::assertSame(['pages'], $tables['writeTables']);
        self::assertContains('pages', $tables['readTables']);
    }

    #[Test]
    public function nestedSubgroupsAreResolvedRecursively(): void
    {
        $permissions = $this->subject->analyseUser(916);

        self::assertEqualsCanonicalizing(['tt_content', 'pages', 'be_users'], $permissions->readTables);
        self::assertEqualsCanonicalizing(['pages'], $permissions->writeTables);
    }

    #[Test]
    public function cyclicSubgroupRelationsTerminate(): void
    {
        $permissions = $this->subject->analyseUser(917);

        self::assertEqualsCanonicalizing(['tt_content', 'pages'], $permissions->readTables);
        self::assertEqualsCanonicalizing(['pages'], $permissions->writeTables);
    }

    #[Test]
    public function adminAccountsCollectNoTablePermissions(): void
    {
        $permissions = $this->subject->analyseUser(910);

        self::assertTrue($permissions->isAdmin);
        self::assertSame([], $permissions->readTables);
        self::assertSame([], $permissions->writeTables);
    }

    #[Test]
    public function adminWithoutDatabaseMountIsDetected(): void
    {
        self::assertTrue($this->subject->analyseUser(910)->hasDbMount());
        self::assertFalse($this->subject->analyseUser(911)->hasDbMount());
    }

    #[Test]
    public function unknownUserThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->subject->analyseUser(999);
    }
}
