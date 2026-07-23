<?php

namespace matevrd\ZtPermissions\Hooks;

use matevrd\ZtPermissions\Service\AuditModule;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;


final class SecurityCriticalActionHook
{
    private const MONITORED_TABLES = ['be_users', 'be_groups'];
    private const PAGE_PERMISSION_FIELDS = ['perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody'];

    public function __construct(
        private readonly AuditModule $auditModule,
    ) {}

    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        int|string $id,
        array $fieldArray,
        DataHandler $dataHandler,
    ): void {
        $recordId = $this->resolveRecordId($id, $dataHandler);

        if (in_array($table, self::MONITORED_TABLES, true)) {
            $this->record($table, $recordId, $status === 'new' ? 'record_created' : 'record_updated', $dataHandler);
            return;
        }

        if ($table === 'pages' && array_intersect(array_keys($fieldArray), self::PAGE_PERMISSION_FIELDS) !== []) {
            $this->record($table, $recordId, 'page_permissions_changed', $dataHandler);
        }
    }

    public function processCmdmap_postProcess(
        string $command,
        string $table,
        int|string $id,
        mixed $value,
        DataHandler $dataHandler,
        mixed $pasteUpdate = null,
        mixed $pasteDatamap = null,
    ): void {
        if (!in_array($table, self::MONITORED_TABLES, true)) {
            return;
        }

        $actionType = match ($command) {
            'delete' => 'record_deleted',
            'undelete' => 'record_undeleted',
            default => null,
        };

        if ($actionType !== null) {
            $this->record($table, $id, $actionType, $dataHandler);
        }
    }

    private function resolveRecordId(int|string $id, DataHandler $dataHandler): int|string
    {
        if (is_string($id) && str_starts_with($id, 'NEW')) {
            return $dataHandler->substNEWwithIDs[$id] ?? $id;
        }

        return $id;
    }

    private function record(string $table, int|string $id, string $actionType, DataHandler $dataHandler): void
    {
        $backendUserUid = (int)($dataHandler->BE_USER->user['uid'] ?? 0);
        if ($backendUserUid === 0) {
            return;
        }

        $this->auditModule->recordSecurityCriticalAction(
            $backendUserUid,
            $this->getRemoteAddress(),
            $actionType,
            sprintf('%s#%s', $table, (string)$id),
        );
    }

    private function getRemoteAddress(): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof ServerRequestInterface) {
            $normalizedParams = $request->getAttribute('normalizedParams');
            if ($normalizedParams !== null) {
                return (string)$normalizedParams->getRemoteAddress();
            }
        }

        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }
}


// https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Events/Hooks/Index.html
// https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/DataHandler/Database/Index.html