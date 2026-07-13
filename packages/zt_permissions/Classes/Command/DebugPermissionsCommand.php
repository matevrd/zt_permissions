<?php

namespace matevrd\ZtPermissions\Command;

use matevrd\ZtPermissions\Service\PermissionAnalyzer;
use matevrd\ZtPermissions\Service\RiskRuleEvaluator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DebugPermissionsCommand extends Command
{
    public function __construct(
        private readonly PermissionAnalyzer $permissionAnalyzer,
        private readonly RiskRuleEvaluator $riskRuleEvaluator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('userUid', InputArgument::REQUIRED, 'be_users uid');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $permissions = $this->permissionAnalyzer->analyseUser((int)$input->getArgument('userUid'));

        $output->writeln('isAdmin: ' . ($permissions->isAdmin ? 'yes' : 'no'));
        $output->writeln('isSystemMaintainer: ' . ($permissions->isSystemMaintainer ? 'yes' : 'no'));
        $output->writeln('readTables: ' . implode(', ', $permissions->readTables));
        $output->writeln('writeTables: ' . implode(', ', $permissions->writeTables));

        $matches = $this->riskRuleEvaluator->evaluate($permissions);
        $output->writeln('riskMatches: ' . count($matches));
        foreach ($matches as $match) {
            $output->writeln('  - ' . $match['id'] . ' (' . $match['riskLevel'] . '): ' . $match['description']);
        }

        return Command::SUCCESS;
    }
}
