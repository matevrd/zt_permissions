<?php

namespace matevrd\ZtPermissions\Service;

use matevrd\ZtPermissions\Domain\Model\AuditLogEntry;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class AuditLogRepository
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function findLatestEntryPerUserOrderedByRisk(int $limit = 50): array
    {
        $latestUidSubQuery = $this->connectionPool->getQueryBuilderForTable('tx_zt_audit_log');
        $latestUidSubQuery->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $latestUidSubQuery
            ->selectLiteral('MAX(' . $latestUidSubQuery->quoteIdentifier('uid') . ') AS ' . $latestUidSubQuery->quoteIdentifier('uid'))
            ->from('tx_zt_audit_log')
            ->groupBy('user_id');

        $queryBuilder = $this->createLogSelectQueryBuilder();
        $rows = $queryBuilder
            ->where($queryBuilder->expr()->in('log.uid', $latestUidSubQuery->getSQL()))
            ->orderBy('log.tstamp', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        usort(
            $rows,
            static function (array $a, array $b): int {
                $riskOrder = ['hoch' => 3, 'mittel' => 2, 'niedrig' => 1];

                return ($riskOrder[$b['risk_level']] ?? 0) <=> ($riskOrder[$a['risk_level']] ?? 0);
            }
        );

        return array_map(
            $this->mapRowToEntry(...),
            array_slice($rows, 0, $limit)
        );
    }

    public function findRecentEntries(int $limit = 20): array
    {
        $queryBuilder = $this->createLogSelectQueryBuilder();
        $rows = $queryBuilder
            ->orderBy('log.tstamp', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map($this->mapRowToEntry(...), $rows);
    }

    private function createLogSelectQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_zt_audit_log');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('log.uid', 'log.user_id', 'log.tstamp', 'log.action_type', 'log.risk_level', 'log.rule_id', 'log.rule_description', 'log.page_context', 'be_users.username')
            ->from('tx_zt_audit_log', 'log')
            ->leftJoin(
                'log',
                'be_users',
                'be_users',
                $queryBuilder->expr()->eq('be_users.uid', $queryBuilder->quoteIdentifier('log.user_id'))
            );
    }

    private function mapRowToEntry(array $row): AuditLogEntry
    {
        $username = (string)($row['username'] ?? '');

        return new AuditLogEntry(
            uid: (int)$row['uid'],
            userUid: (int)$row['user_id'],
            username: $username !== '' ? $username : 'gelöschter Benutzer',
            timestamp: (int)$row['tstamp'],
            actionType: (string)$row['action_type'],
            riskLevel: (string)$row['risk_level'],
            ruleId: (string)($row['rule_id'] ?? ''),
            ruleDescription: (string)($row['rule_description'] ?? ''),
            pageContext: (string)($row['page_context'] ?? ''),
        );
    }
}
