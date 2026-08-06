<?php

namespace matevrd\ZtPermissions\Tests\Functional\Service;

use matevrd\ZtPermissions\Service\PermissionAnalyzer;
use matevrd\ZtPermissions\Service\RiskRuleEvaluator;
use matevrd\ZtPermissions\Tests\Functional\ZtPermissionsFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class RiskRuleEvaluatorTest extends ZtPermissionsFunctionalTestCase
{
    private RiskRuleEvaluator $subject;
    private PermissionAnalyzer $permissionAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EffectivePermissions.csv');
        $this->subject = $this->get(RiskRuleEvaluator::class);
        $this->permissionAnalyzer = $this->get(PermissionAnalyzer::class);
    }

    #[Test]
    public function combinationOfTwoGroupsTriggersHighRiskRule(): void
    {
        $matches = $this->subject->evaluate($this->permissionAnalyzer->analyseUser(914));

        self::assertContains('pages-write-beusers-access', $this->ruleIds($matches));
        self::assertSame('hoch', $this->highestRiskLevel($matches));
    }

    #[Test]
    public function singleGroupWithReadAccessTriggersNoRule(): void
    {
        $tables = $this->permissionAnalyzer->analyseGroup(902);

        self::assertSame([], $this->subject->evaluateTables($tables['readTables'], $tables['writeTables']));
    }

    #[Test]
    public function singleGroupWithPagesWriteReachesOnlyMediumRisk(): void
    {
        $tables = $this->permissionAnalyzer->analyseGroup(901);
        $matches = $this->subject->evaluateTables($tables['readTables'], $tables['writeTables']);

        self::assertSame(['pages-write-only'], $this->ruleIds($matches));
        self::assertSame('mittel', $this->highestRiskLevel($matches));
    }

    #[Test]
    public function selfEscalationCombinationIsDetected(): void
    {
        $matches = $this->subject->evaluate($this->permissionAnalyzer->analyseUser(915));

        self::assertSame(['beusers-write-begroups-write'], $this->ruleIds($matches));
        self::assertSame('hoch', $this->highestRiskLevel($matches));
    }

    #[Test]
    public function combinationAcrossNestedSubgroupsIsDetected(): void
    {
        $matches = $this->subject->evaluate($this->permissionAnalyzer->analyseUser(916));

        self::assertContains('pages-write-beusers-access', $this->ruleIds($matches));
        self::assertSame('hoch', $this->highestRiskLevel($matches));
    }

    #[Test]
    public function adminAccountsAreExcludedFromRuleEvaluation(): void
    {
        self::assertSame([], $this->subject->evaluate($this->permissionAnalyzer->analyseUser(910)));
    }

    #[Test]
    public function userWithoutCriticalPermissionsTriggersNoRule(): void
    {
        self::assertSame([], $this->subject->evaluate($this->permissionAnalyzer->analyseUser(912)));
    }

    private function ruleIds(array $matches): array
    {
        return array_column($matches, 'id');
    }

    private function highestRiskLevel(array $matches): string
    {
        $order = ['niedrig' => 0, 'mittel' => 1, 'hoch' => 2];
        $levels = array_column($matches, 'riskLevel');
        usort($levels, static fn(string $a, string $b): int => ($order[$b] ?? 0) <=> ($order[$a] ?? 0));

        return $levels[0] ?? 'niedrig';
    }
}
