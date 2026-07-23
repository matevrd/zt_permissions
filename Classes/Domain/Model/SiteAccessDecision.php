<?php

namespace matevrd\ZtPermissions\Domain\Model;

final class SiteAccessDecision
{
    public const REASON_SYSTEM_MAINTAINER = 'system_maintainer';
    public const REASON_MAPPED = 'mapped';
    public const REASON_NO_MAPPING = 'no_mapping';
    public const REASON_SITE_UNRESOLVED = 'site_unresolved';

    public function __construct(
        public readonly bool $granted,
        public readonly string $reason,
        public readonly string $siteIdentifier,
    ) {}

    public static function grant(string $reason, string $siteIdentifier): self
    {
        return new self(true, $reason, $siteIdentifier);
    }

    public static function deny(string $reason, string $siteIdentifier): self
    {
        return new self(false, $reason, $siteIdentifier);
    }
}
