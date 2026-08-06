<?php

namespace matevrd\ZtPermissions\EventListener;

use matevrd\ZtPermissions\Service\SiteAccessControlModule;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\Event\ModifyRecordsAfterFetchingContentEvent;

#[AsEventListener(identifier: 'zt-permissions/fe-content-site-scoping')]
final class FeContentSiteScopingListener
{
    public function __construct(
        private readonly SiteAccessControlModule $siteAccessControlModule,
    ) {}

    public function __invoke(ModifyRecordsAfterFetchingContentEvent $event): void
    {
        if (($event->getConfiguration()['table'] ?? '') !== 'tt_content') {
            return;
        }

        $site = ($GLOBALS['TYPO3_REQUEST'] ?? null)?->getAttribute('site');
        if ($this->siteAccessControlModule->resolveFrontendAccessForCurrentUser($site) !== false) {
            return;
        }

        $filtered = array_filter(
            $event->getRecords(),
            static fn(array $record): bool
            => !GeneralUtility::inList((string)($record['fe_group'] ?? ''), '-2')
        );
        $event->setRecords(array_values($filtered));
    }
}
