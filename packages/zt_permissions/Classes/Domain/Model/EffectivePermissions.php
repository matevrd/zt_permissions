<?php

namespace matevrd\ZtPermissions\Domain\Model;

final class EffectivePermissions
{
    public function __construct(
        public readonly int $userUid,
        public readonly bool $isAdmin,
        public readonly bool $isSystemMaintainer,
        public readonly array $dbMountPoints,
        public readonly array $readTables,
        public readonly array $writeTables,
    ) {}

    public function hasDbMount(): bool
    {
        return $this->dbMountPoints !== [];
    }
}
