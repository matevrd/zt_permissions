<?php

namespace matevrd\ZtPermissions\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SiteMappingRepository
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}
    private array $cache = [];

    public function findSiteIdentifiersForUser(int $userId, string $userType): array
    {
        $cacheKey = $userType . ':' . $userId;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_zt_site_mapping');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $rows = $queryBuilder
            ->select('site_identifier')
            ->from('tx_zt_site_mapping')
            ->where(
                $queryBuilder->expr()->eq(
                    'user_id',
                    $queryBuilder->createNamedParameter($userId, Connection::PARAM_INT)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'user_type',
                    $queryBuilder->createNamedParameter($userType, Connection::PARAM_STR)
                )
            )->executeQuery()
            ->fetchAllAssociative();

        $siteIdentifiers = array_values(array_unique(array_column($rows, 'site_identifier')));

        $this->cache[$cacheKey] = $siteIdentifiers;
        return $siteIdentifiers;
    }

    public function hasMappingForSite(int $userId, string $userType, string $siteIdentifier): bool
    {
        return in_array(
            $siteIdentifier,
            $this->findSiteIdentifiersForUser($userId, $userType),
            true
        );
    }
}
