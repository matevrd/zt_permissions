<?php

namespace matevrd\ZtPermissions\Middleware;

use matevrd\ZtPermissions\Service\AuditModule;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

final class SecurityCriticalModuleAuditMiddleware implements MiddlewareInterface
{
    private const MONITORED_MODULE_IDENTIFIERS = [
        'extensionmanager' => 'extension_manager_access',
        'system_environment' => 'environment_tool_access',
        'system_upgrade' => 'upgrade_wizard_access',
    ];

    public function __construct(
        private readonly AuditModule $auditModule,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->recordIfMonitored($request);

        return $handler->handle($request);
    }

    private function recordIfMonitored(ServerRequestInterface $request): void
    {
        $actionType = $this->findActionTypeForModule($request);
        if ($actionType !== null) {
            $this->record($request, $actionType, $request->getUri()->getPath());
        }
    }

    private function findActionTypeForModule(ServerRequestInterface $request): ?string
    {
        $module = $request->getAttribute('module');
        if (!$module instanceof ModuleInterface) {
            return null;
        }

        return self::MONITORED_MODULE_IDENTIFIERS[$module->getIdentifier()] ?? null;
    }

    private function record(ServerRequestInterface $request, string $actionType, string $path): void
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return;
        }

        $userUid = (int)($backendUser->user['uid'] ?? 0);
        if ($userUid === 0) {
            return;
        }

        $normalizedParams = $request->getAttribute('normalizedParams');
        $remoteAddress = $normalizedParams !== null ? (string)$normalizedParams->getRemoteAddress() : (string)($_SERVER['REMOTE_ADDR'] ?? '');

        $this->auditModule->recordSecurityCriticalAction($userUid, $remoteAddress, $actionType, $path);
    }
}
