<?php

namespace matevrd\ZtPermissions\Controller;

use matevrd\ZtPermissions\Domain\Repository\AuditLogRepository;
use matevrd\ZtPermissions\Service\AuditModule;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;

#[AsController]
final class AuditDashboardController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly AuditModule $auditModule,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $moduleTemplate->assignMultiple([
            'flaggedUsers' => $this->auditModule->findCriticalUsers(),
            'flaggedGroups' => $this->auditModule->findCriticalGroups(),
            'recentAdminEvents' => $this->auditLogRepository->findLatestEntryPerUserOrderedByRisk(),
            'recentEntries' => $this->auditLogRepository->findRecentEntries(),
        ]);

        return $moduleTemplate->renderResponse('AuditDashboard/Index');
    }
}
