<?php

namespace matevrd\ZtPermissions\Service;

use matevrd\ZtPermissions\Domain\Model\SiteAccessDecision;
use matevrd\ZtPermissions\Domain\Repository\SiteMappingRepository;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;

final class SiteAccessControlModule
{
    public function __construct(
        private readonly SiteMappingRepository $siteMappingRepository,
        private readonly PermissionAnalyzer $permissionAnalyzer,
        private readonly SiteFinder $siteFinder,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly Context $context,
    ) {}

    public function resolveSiteByHost(string $host): ?Site
    {
        foreach ($this->siteFinder->getAllSites() as $site) {
            if ($site->getBase()->getHost() === $host) {
                return $site;
            }
        }
        return null;
    }

    public function decideBackendAccess(int $userUid, ?Site $site): SiteAccessDecision
    {
        if ($this->permissionAnalyzer->isSystemMaintainer($userUid)) {
            return SiteAccessDecision::grant(SiteAccessDecision::REASON_SYSTEM_MAINTAINER, '');
        }

        if ($site === null) {
            return SiteAccessDecision::deny(SiteAccessDecision::REASON_SITE_UNRESOLVED, '');
        }

        $siteIdentifier = $site->getIdentifier();

        if ($this->siteMappingRepository->hasMappingForSite(
            $userUid,
            'be',
            $siteIdentifier
        )) {
            return SiteAccessDecision::grant(SiteAccessDecision::REASON_MAPPED, $siteIdentifier);
        }

        return SiteAccessDecision::deny(SiteAccessDecision::REASON_NO_MAPPING, $siteIdentifier);
    }

    public function isFrontendUserGrantedForSite(int $userId, Site $site): bool
    {
        return $this->siteMappingRepository->hasMappingForSite(
            $userId,
            'fe',
            $site->getIdentifier()
        );
    }

    public function resolveFrontendAccessForCurrentUser(mixed $site): ?bool
    {
        if (
            !$this->isEnforcementActive()
            || !$this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn')
            || !$site instanceof Site
        ) {
            return null;
        }

        return $this->isFrontendUserGrantedForSite(
            $this->getCurrentFrontendUserId(),
            $site
        );
    }

    private function getCurrentFrontendUserId(): int
    {
        return (int)$this->context->getPropertyFromAspect('frontend.user', 'id');
    }

    public function isEnforcementActive(): bool
    {
        try {
            $mode = trim((string)$this->extensionConfiguration->get('zt_permissions', 'siteAccessEnforcementMode'));
        } catch (ExtensionConfigurationExtensionNotConfiguredException | ExtensionConfigurationPathDoesNotExistException) {
            return true;
        }
        return $mode !== 'log-only';
    }
}
