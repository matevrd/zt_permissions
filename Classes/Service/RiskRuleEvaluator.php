<?php

namespace matevrd\ZtPermissions\Service;

use matevrd\ZtPermissions\Domain\Model\EffectivePermissions;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;

final class RiskRuleEvaluator
{
    public function __construct(
        private readonly YamlFileLoader $yamlFileLoader,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}
    private ?array $criticalCombinations = null;

    public function evaluate(EffectivePermissions $permissions): array
    {
        if ($permissions->isAdmin) {
            return [];
        }

        return $this->evaluateTables($permissions->readTables, $permissions->writeTables);
    }

    public function evaluateTables(array $readTables, array $writeTables): array
    {
        $matches = [];
        foreach ($this->getCriticalCombinations() as $rule) {
            if ($this->ruleMatches($rule, $readTables, $writeTables)) {
                $matches[] = $rule;
            }
        }

        return $matches;
    }

    private function ruleMatches(array $rule, array $readTables, array $writeTables): bool
    {
        foreach ($rule['permissions'] as $table => $level) {
            $grantedTables = $level === 'write' ? $writeTables : $readTables;
            if (!in_array($table, $grantedTables, true)) {
                return false;
            }
        }

        return true;
    }

    private function getCriticalCombinations(): array
    {
        if ($this->criticalCombinations === null) {
            $rulesFile = $this->determineRulesFile();
            $config = $this->yamlFileLoader->load($rulesFile);
            $this->criticalCombinations = array_map(
                fn(array $rule): array => $this->validateRule($rule, $rulesFile),
                $config['criticalCombinations'] ?? []
            );
        }

        return $this->criticalCombinations;
    }

    private function determineRulesFile(): string
    {
        try {
            $configuredFile = trim((string)$this->extensionConfiguration->get('zt_permissions', 'riskRulesFile'));
        } catch (ExtensionConfigurationExtensionNotConfiguredException | ExtensionConfigurationPathDoesNotExistException) {
            return 'EXT:zt_permissions/Configuration/RiskRules.yaml';
        }

        return $configuredFile !== '' ? $configuredFile : 'EXT:zt_permissions/Configuration/RiskRules.yaml';
    }

    private function validateRule(array $rule, string $rulesFile): array
    {
        foreach (['id', 'riskLevel', 'permissions'] as $requiredKey) {
            if (!isset($rule[$requiredKey])) {
                throw new \RuntimeException(
                    sprintf('Risiko-Regel ohne Schlüssel "%s" in %s.', $requiredKey, $rulesFile),
                    1784246400
                );
            }
        }

        foreach ($rule['permissions'] as $table => $level) {
            if (!in_array($level, ['read', 'write'], true)) {
                throw new \RuntimeException(
                    sprintf(
                        'Risiko-Regel "%s" in %s: ungültige Berechtigungsstufe "%s" für Tabelle "%s". Erlaubt sind "read" und "write".',
                        $rule['id'],
                        $rulesFile,
                        (string)$level,
                        (string)$table
                    ),
                    1784246401
                );
            }
        }

        return $rule;
    }
}
