<?php

namespace matevrd\ZtPermissions\Service;

use matevrd\ZtPermissions\Domain\Model\EffectivePermissions;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class PermissionAnalyzer
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function analyseUser(int $userUid): EffectivePermissions
    {
        $userQueryBuilder = $this->createRestrictedQueryBuilder('be_users');
        $user = $userQueryBuilder
            ->select('uid', 'admin', 'usergroup', 'db_mountpoints')
            ->from('be_users')
            ->where(
                $userQueryBuilder->expr()->eq('uid', $userQueryBuilder->createNamedParameter($userUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        if ($user === false) {
            throw new \RuntimeException(
                sprintf('be_user %d nicht gefunden.', $userUid)
            );
        }

        $isAdmin = (($user['admin'] ?? 0) & 1) === 1;

        $systemMaintainers = array_map(
            'intval',
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] ?? []
        );
        $isSystemMaintainer = in_array($userUid, $systemMaintainers, true);

        $dbMountPoints = GeneralUtility::intExplode(',', (string)($user['db_mountpoints'] ?? ''), true);

        $readTables = [];
        $writeTables = [];

        if (trim((string)$user['usergroup']) !== '') {
            $directGroupUids = GeneralUtility::intExplode(',', $user['usergroup'], true);
            $allGroupUids = $this->resolveGroupUidsRecursively($directGroupUids);
            $groupData = $this->collectGroupData($allGroupUids);

            $dbMountPoints = array_merge($dbMountPoints, $groupData['mountPoints']);

            if (!$isAdmin) {
                $readTables = $groupData['readTables'];
                $writeTables = $groupData['writeTables'];
            }
        }

        return new EffectivePermissions(
            userUid: $userUid,
            isAdmin: $isAdmin,
            isSystemMaintainer: $isSystemMaintainer,
            dbMountPoints: array_values(array_unique($dbMountPoints)),
            readTables: $readTables,
            writeTables: $writeTables,
        );
    }

    public function analyseGroup(int $groupUid): array
    {
        $allGroupUids = $this->resolveGroupUidsRecursively([$groupUid]);
        $groupData = $this->collectGroupData($allGroupUids);

        return ['readTables' => $groupData['readTables'], 'writeTables' => $groupData['writeTables']];
    }

    private function collectGroupData(array $groupUids): array
    {
        $readTables = [];
        $writeTables = [];
        $mountPoints = [];

        foreach ($groupUids as $groupUid) {
            $groupQueryBuilder = $this->createRestrictedQueryBuilder('be_groups');
            $group = $groupQueryBuilder
                ->select('tables_select', 'tables_modify', 'db_mountpoints')
                ->from('be_groups')
                ->where(
                    $groupQueryBuilder->expr()->eq('uid', $groupQueryBuilder->createNamedParameter($groupUid, Connection::PARAM_INT))
                )
                ->executeQuery()
                ->fetchAssociative();

            if ($group === false) {
                continue;
            }

            $readTables = array_merge(
                $readTables,
                GeneralUtility::trimExplode(',', $group['tables_select'] ?? '', true)
            );
            $writeTables = array_merge(
                $writeTables,
                GeneralUtility::trimExplode(',', $group['tables_modify'] ?? '', true)
            );
            $mountPoints = array_merge(
                $mountPoints,
                GeneralUtility::intExplode(',', (string)($group['db_mountpoints'] ?? ''), true)
            );
        }

        return [
            'readTables' => array_values(array_unique(array_merge($readTables, $writeTables))),
            'writeTables' => array_values(array_unique($writeTables)),
            'mountPoints' => array_values(array_unique($mountPoints)),
        ];
    }

    private function resolveGroupUidsRecursively(array $groupUids, array $visited = []): array
    {
        $resolved = [];

        foreach ($groupUids as $groupUid) {
            if (isset($visited[$groupUid])) {
                continue;
            }
            $visited[$groupUid] = true;
            $resolved[] = $groupUid;

            $queryBuilder = $this->createRestrictedQueryBuilder('be_groups');
            $group = $queryBuilder
                ->select('subgroup')
                ->from('be_groups')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($groupUid, Connection::PARAM_INT))
                )
                ->executeQuery()
                ->fetchAssociative();

            if ($group === false) {
                continue;
            }

            $subgroupUids = GeneralUtility::intExplode(',', (string)($group['subgroup'] ?? ''), true);
            if ($subgroupUids !== []) {
                $resolved = array_merge(
                    $resolved,
                    $this->resolveGroupUidsRecursively($subgroupUids, $visited)
                );
            }
        }

        return array_values(array_unique($resolved));
    }

    private function createRestrictedQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        return $queryBuilder;
    }
    // https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Database/DoctrineDbal/RestrictionBuilder/Index.html
}
