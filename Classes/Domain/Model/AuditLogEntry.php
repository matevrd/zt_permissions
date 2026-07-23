<?php

namespace matevrd\ZtPermissions\Domain\Model;

final class AuditLogEntry
{
    public function __construct(
        public readonly int $uid,
        public readonly int $userUid,
        public readonly string $username,
        public readonly int $timestamp,
        public readonly string $actionType,
        public readonly string $riskLevel,
        public readonly string $ruleId,
        public readonly string $ruleDescription,
        public readonly string $pageContext,
    ) {}
}
