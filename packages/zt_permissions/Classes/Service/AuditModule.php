<?php

namespace matevrd\ZtPermissions\Service;

use matevrd\ZtPermissions\Domain\Model\EffectivePermissions;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class AuditModule
{
    public function __construct(
        private readonly PermissionAnalyzer $permissionAnalyzer,
        private readonly RiskRuleEvaluator $riskRuleEvaluator,
        private readonly ConnectionPool $connectionPool,
        private readonly Context $context,
        private readonly HashService $hashService,
    ) {}

    public function recordSecurityCriticalAction(
        int $userUid,
        string $remoteAddress,
        string $actionType,
        string $pageContext,
    ): void {
        $permissions = $this->permissionAnalyzer->analyseUser($userUid);

        if ($permissions->isAdmin) {
            [$riskLevel, $ruleId, $ruleDescription] = $this->determineAdminRisk($permissions);
        } else {
            $matches = $this->riskRuleEvaluator->evaluate($permissions);
            if ($matches !== []) {
                [$riskLevel, $ruleId, $ruleDescription] = $this->determineHighestRiskMatch($matches);
            } else {
                $riskLevel = 'niedrig';
                $ruleId = '';
                $ruleDescription = '';
            }
        }

        $this->connectionPool
            ->getConnectionForTable('tx_zt_audit_log')
            ->insert(
                'tx_zt_audit_log',
                [
                    'user_id' => $userUid,
                    'tstamp' => $this->context->getPropertyFromAspect('date', 'timestamp'),
                    'ip_hash' => $this->hashService->hmac($remoteAddress, self::class, HashAlgo::SHA256),
                    'action_type' => $actionType,
                    'risk_level' => $riskLevel,
                    'rule_id' => $ruleId,
                    'rule_description' => $ruleDescription,
                    'page_context' => $pageContext !== '' ? $pageContext : $this->describePageScope($permissions),
                ],
                [
                    Connection::PARAM_INT,
                    Connection::PARAM_INT,
                    Connection::PARAM_STR,
                    Connection::PARAM_STR,
                    Connection::PARAM_STR,
                    Connection::PARAM_STR,
                    Connection::PARAM_STR,
                    Connection::PARAM_STR,
                ],
            );
    }

    public function findCriticalUsers(): array
    {
        $rows = [];

        foreach ($this->fetchEntities('be_users', ['uid', 'username']) as $user) {
            $userUid = (int)$user['uid'];
            $permissions = $this->permissionAnalyzer->analyseUser($userUid);

            if ($permissions->isAdmin) {
                [$riskLevel, $ruleId, $ruleDescription, $matchedCombination] = $this->determineAdminRisk($permissions);
            } else {
                $matches = $this->riskRuleEvaluator->evaluate($permissions);
                if ($matches === []) {
                    continue;
                }
                [$riskLevel, $ruleId, $ruleDescription, $matchedCombination] = $this->determineHighestRiskMatch($matches);
            }

            $rows[] = [
                'entityId' => $userUid,
                'entityLabel' => (string)$user['username'],
                'isAdmin' => $permissions->isAdmin,
                'isSystemMaintainer' => $permissions->isSystemMaintainer,
                'hasDbMount' => $permissions->hasDbMount(),
                'pageScope' => $this->describePageScope($permissions),
                'riskLevel' => $riskLevel,
                'ruleId' => $ruleId,
                'ruleDescription' => $ruleDescription,
                'matchedCombination' => $matchedCombination,
            ];
        }

        return $this->sortByRiskDescending($rows);
    }

    public function findCriticalGroups(): array
    {
        $rows = [];

        foreach ($this->fetchEntities('be_groups', ['uid', 'title']) as $group) {
            $groupUid = (int)$group['uid'];
            $tables = $this->permissionAnalyzer->analyseGroup($groupUid);
            $matches = $this->riskRuleEvaluator->evaluateTables($tables['readTables'], $tables['writeTables']);

            if ($matches === []) {
                continue;
            }

            [$riskLevel, $ruleId, $ruleDescription, $matchedCombination] = $this->determineHighestRiskMatch($matches);

            $rows[] = [
                'entityId' => $groupUid,
                'entityLabel' => (string)$group['title'],
                'riskLevel' => $riskLevel,
                'ruleId' => $ruleId,
                'ruleDescription' => $ruleDescription,
                'matchedCombination' => $matchedCombination,
            ];
        }

        return $this->sortByRiskDescending($rows);
    }

    private function fetchEntities(string $table, array $fields): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        return $queryBuilder->select(...$fields)->from($table)->executeQuery()->fetchAllAssociative();
    }

    private function sortByRiskDescending(array $rows): array
    {
        $riskOrder = ['niedrig' => 0, 'mittel' => 1, 'hoch' => 2];
        usort(
            $rows,
            static fn(array $a, array $b): int => ($riskOrder[$b['riskLevel']] ?? 0) <=> ($riskOrder[$a['riskLevel']] ?? 0)
        );

        return $rows;
    }

    private function determineAdminRisk(EffectivePermissions $permissions): array
    {
        if ($permissions->isSystemMaintainer) {
            return ['hoch', 'admin-system-maintainer-access', 'Administratorkonto mit zusätzlicher System-Maintainer-Berechtigung', ''];
        }

        if (!$permissions->hasDbMount()) {
            return ['hoch', 'admin-unrestricted-access-no-mount', 'Administratorkonto ohne konfigurierten Database Mount mit uneingeschränktem Zugriff auf den vollständigen Seitenbaum', ''];
        }

        return ['hoch', 'admin-unrestricted-access', 'Administratorkonto mit uneingeschränktem Zugriff auf den vollständigen Seitenbaum', ''];
    }

    private function describePageScope(EffectivePermissions $permissions): string
    {
        if ($permissions->isAdmin) {
            return 'pages#*';
        }

        if (!$permissions->hasDbMount()) {
            return 'pages#kein';
        }

        return mb_substr(
            'pages#' . implode(',', $permissions->dbMountPoints),
            0,
            255
        );
    }

    private function determineHighestRiskMatch(array $matches): array
    {
        $riskOrder = ['niedrig' => 0, 'mittel' => 1, 'hoch' => 2];
        $highestLevel = 'niedrig';
        $highestRuleId = '';
        $highestDescription = '';
        $highestCombination = '';

        foreach ($matches as $match) {
            $level = $match['riskLevel'];
            if (($riskOrder[$level] ?? 0) >= ($riskOrder[$highestLevel] ?? 0)) {
                $highestLevel = $level;
                $highestRuleId = $match['id'];
                $highestDescription = (string)($match['description'] ?? '');
                $highestCombination = $this->formatCombination($match['permissions'] ?? []);
            }
        }

        return [$highestLevel, $highestRuleId, $highestDescription, $highestCombination];
    }

    private function formatCombination(array $permissions): string
    {
        $parts = [];
        foreach ($permissions as $table => $level) {
            $parts[] = $table . ': ' . $level;
        }

        return implode(', ', $parts);
    }
}
