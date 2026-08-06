<?php

namespace matevrd\ZtPermissions\Tests\Unit\Domain\Model;

use matevrd\ZtPermissions\Domain\Model\EffectivePermissions;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class EffectivePermissionsTest extends UnitTestCase
{
    #[Test]
    public function hasDbMountReturnsFalseWithoutMountPoints(): void
    {
        self::assertFalse($this->createPermissions([])->hasDbMount());
    }

    #[Test]
    public function hasDbMountReturnsTrueWithMountPoints(): void
    {
        self::assertTrue($this->createPermissions([1, 2])->hasDbMount());
    }

    private function createPermissions(array $dbMountPoints): EffectivePermissions
    {
        return new EffectivePermissions(
            userUid: 910,
            isAdmin: false,
            isSystemMaintainer: false,
            dbMountPoints: $dbMountPoints,
            readTables: [],
            writeTables: [],
        );
    }
}
