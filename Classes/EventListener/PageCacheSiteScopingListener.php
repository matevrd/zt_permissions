<?php

namespace matevrd\ZtPermissions\EventListener;

use matevrd\ZtPermissions\Service\SiteAccessControlModule;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Cache\CacheDataCollectorInterface;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Frontend\Event\BeforePageCacheIdentifierIsHashedEvent;

#[AsEventListener(identifier: 'zt-permissions/page-cache-site-scoping')]
final class PageCacheSiteScopingListener
{
    public function __construct(
        private readonly SiteAccessControlModule $siteAccessControlModule,
    ) {}

    public function __invoke(BeforePageCacheIdentifierIsHashedEvent $event): void
    {
        $request = $event->getRequest();

        $granted = $this->siteAccessControlModule->resolveFrontendAccessForCurrentUser(
            $request->getAttribute('site')
        );
        if ($granted === null) {
            return;
        }

        $parameters = $event->getPageCacheIdentifierParameters();
        $parameters['ztSiteAccessGranted'] = $granted;
        $event->setPageCacheIdentifierParameters($parameters);

        $this->tagPageCache($request);
    }

    private function tagPageCache(ServerRequestInterface $request): void
    {
        $cacheDataCollector = $request->getAttribute('frontend.cache.collector');
        if (!$cacheDataCollector instanceof CacheDataCollectorInterface) {
            return;
        }

        $cacheDataCollector->addCacheTags(
            new CacheTag('tx_zt_site_mapping')
        );
    }
}
