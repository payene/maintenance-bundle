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
    name: 'maintenance:off',
    description: 'Désactive le mode maintenance.',
)]
final class MaintenanceOffCommand extends Command
{
    public function __construct(
        private readonly MaintenanceManager $manager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->manager->isEnabled()) {
            $io->note('Le mode maintenance est déjà désactivé.');

            return Command::SUCCESS;
        }

        $this->manager->disable();

        $io->success('Mode maintenance DÉSACTIVÉ. L\'application est de nouveau accessible.');
        $io->writeln('  <comment>Note :</comment> les paramètres de la page (message, date, whitelist) sont conservés.');

        return Command::SUCCESS;
    }
}