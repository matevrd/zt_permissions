<?php

namespace matevrd\ZtPermissions\TCA;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SiteMappingItemsProvider
{
    public function getSiteItems(array &$params): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        foreach ($siteFinder->getAllSites() as $site) {
            $params['items'][] = [
                'label' => $site->getIdentifier() . ' (' . (string)$site->getBase() . ')',
                'value' => $site->getIdentifier(),
            ];
        }
    }

    public function getUserItems(array &$params): void
    {
        $userType = $params['row']['user_type'] ?? 'be';
        if (is_array($userType)) {
            $userType = (string)($userType[0] ?? 'be');
        }

        $table = $userType === 'fe' ? 'fe_users' : 'be_users';

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $rows = $queryBuilder
            ->select('uid', 'username')
            ->from($table)
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $params['items'][] = [
                'label' => $row['username'] . ' [' . $row['uid'] . ']',
                'value' => (int)$row['uid'],
            ];
        }
    }
}
