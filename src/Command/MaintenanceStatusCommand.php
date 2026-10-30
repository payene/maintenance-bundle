<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Command;

use Payene\MaintenanceBundle\Service\MaintenanceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'maintenance:status',
    description: 'Affiche l\'état du mode maintenance.',
)]
final class MaintenanceStatusCommand extends Command
{
    public function __construct(
        private readonly MaintenanceManager $manager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $state = $this->manager->getState();

        if (!$state->enabled) {
            $io->success('Le mode maintenance est désactivé.');
            $io->writeln(sprintf('  <comment>Whitelist IP :</comment> %s', implode(', ', $state->whitelistIps) ?: '(vide)'));
            $io->writeln(sprintf('  <comment>Whitelist chemins :</comment> %s', implode(', ', $state->whitelistPaths) ?: '(vide)'));

            return Command::SUCCESS;
        }

        $io->warning('Le mode maintenance est ACTIVÉ.');

        $rows = [
            ['Depuis',          $state->since?->format('d/m/Y H:i:s') ?? 'n/a'],
            ['Jusqu\'à',        $this->formatUntil($state)],
            ['Message',         $state->message         ?? '(par défaut)'],
            ['Overdue message', $state->overdueMessage  ?? '(par défaut)'],
            ['Retry-After',     $state->retryAfter . ' s'],
        ];

        $io->table(['Champ', 'Valeur'], $rows);

        $io->section('Whitelist');
        $io->writeln(sprintf('  <comment>IP :</comment> %s', implode(', ', $state->whitelistIps) ?: '(vide)'));
        $io->writeln(sprintf('  <comment>Chemins :</comment> %s', implode(', ', $state->whitelistPaths) ?: '(vide)'));

        return Command::SUCCESS;
    }

    private function formatUntil(\Payene\MaintenanceBundle\Model\MaintenanceState $state): string
    {
        if ($state->until === null) {
            return 'indéfini';
        }

        $formatted = $state->until->format('d/m/Y H:i:s');

        if ($state->isOverdue()) {
            return sprintf('%s <fg=red;options=bold>(dépassée)</>', $formatted);
        }

        $now = new \DateTimeImmutable();
        $diff = $now->diff($state->until);
        $remaining = $diff->format('%hh %imin');

        return sprintf('%s <fg=yellow>(dans %s)</>', $formatted, $remaining);
    }
}