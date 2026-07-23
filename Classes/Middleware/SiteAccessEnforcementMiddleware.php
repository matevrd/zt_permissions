<?php

namespace matevrd\ZtPermissions\Middleware;

use matevrd\ZtPermissions\Service\AuditModule;
use matevrd\ZtPermissions\Service\SiteAccessControlModule;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RedirectResponse;

final class SiteAccessEnforcementMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SiteAccessControlModule $siteAccessControlModule,
        private readonly AuditModule $auditModule,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return $handler->handle($request);
        }

        $userUid = (int)($backendUser->user['uid'] ?? 0);
        if ($userUid === 0) {
            return $handler->handle($request);
        }

        $host = $request->getUri()->getHost();
        $site = $this->siteAccessControlModule->resolveSiteByHost($host);
        $decision = $this->siteAccessControlModule->decideBackendAccess($userUid, $site);

        if ($decision->granted) {
            return $handler->handle($request);
        }

        $normalizedParams = $request->getAttribute('normalizedParams');
        $remoteAddress = $normalizedParams !== null
            ? (string)$normalizedParams->getRemoteAddress()
            : (string)($_SERVER['REMOTE_ADDR'] ?? '');

        $enforce = $this->siteAccessControlModule->isEnforcementActive();

        $this->auditModule->recordSecurityCriticalAction(
            $userUid,
            $remoteAddress,
            $enforce ? 'site_access_denied' : 'site_access_violation',
            'site#' . $decision->siteIdentifier,
        );

        if (!$enforce) {
            return $handler->handle($request);
        }
        $backendUser->logoff();
        $loginUri = $this->uriBuilder->buildUriFromRoute('login', ['commandLI' => 1]);
        return new RedirectResponse($loginUri, 303);
    }
}
