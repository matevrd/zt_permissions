<?php

namespace matevrd\ZtPermissions\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use matevrd\ZtPermissions\Service\AuditModule;

#[AsEventListener(identifier: 'zt-permissions/backend-login-audit')]
final class BackendLoginAuditListener
{
    public function __construct(
        private readonly AuditModule $auditModule,
    ) {}

    public function __invoke(AfterUserLoggedInEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof BackendUserAuthentication) {
            return;
        }

        $request = $event->getRequest();
        $normalizedParams = $request?->getAttribute('normalizedParams');
        $remoteAddress = $normalizedParams?->getRemoteAddress() ?? '';

        $this->auditModule->recordSecurityCriticalAction(
            (int)$user->user['uid'],
            $remoteAddress,
            'login',
            '',
        );
    }
}
