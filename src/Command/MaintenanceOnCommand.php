<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Command;

use Payene\MaintenanceBundle\Service\MaintenanceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'maintenance:on',
    description: 'Active le mode maintenance.',
)]
final class MaintenanceOnCommand extends Command
{
    public function __construct(
        private readonly MaintenanceManager $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'until',
                null,
                InputOption::VALUE_REQUIRED,
                'Date de fin de maintenance (ex: "+2 hours", "2026-10-30 18:00")',
            )
            ->addOption(
                'message',
                null,
                InputOption::VALUE_REQUIRED,
                'Message affiché sur la page de maintenance',
            )
            ->addOption(
                'overdue-message',
                null,
                InputOption::VALUE_REQUIRED,
                'Message affiché si la maintenance dépasse la date de fin',
            )
            ->addOption(
                'retry-after',
                null,
                InputOption::VALUE_REQUIRED,
                'Valeur du header Retry-After en secondes',
            )
            ->setHelp(<<<'HELP'
                Active le mode maintenance de l'application.

                  <info>php bin/console maintenance:on</info>
                  <info>php bin/console maintenance:on --until="+2 hours"</info>
                  <info>php bin/console maintenance:on --message="Mise à jour planifiée"</info>
                  <info>php bin/console maintenance:on --until="2026-10-30 18:00" --retry-after=3600</info>

                Les options <comment>--until</comment>, <comment>--message</comment>, <comment>--overdue-message</comment>
                et <comment>--retry-after</comment> mettent à jour les paramètres de la page avant
                l'activation. Les paramètres non fournis conservent leur valeur actuelle.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->manager->isEnabled()) {
            $io->warning('Le mode maintenance est déjà activé.');

            return $this->showCurrentState($io) ?? Command::SUCCESS;
        }

        $until = null;
        if ($raw = $input->getOption('until')) {
            try {
                $until = new \DateTimeImmutable($raw);
            } catch (\Exception $e) {
                $io->error(sprintf('Date invalide : "%s". %s', $raw, $e->getMessage()));

                return Command::FAILURE;
            }
        }

        $currentState = $this->manager->getState();
        $hasSettings = $until !== null
            || $input->getOption('message') !== null
            || $input->getOption('overdue-message') !== null
            || $input->getOption('retry-after') !== null;

        if ($hasSettings) {
            $retryAfter = $input->getOption('retry-after');
            $this->manager->updateSettings(
                $until ?? $currentState->until,
                $input->getOption('message') ?? $currentState->message,
                $retryAfter !== null ? (int) $retryAfter : $currentState->retryAfter,
                $input->getOption('overdue-message') ?? $currentState->overdueMessage,
            );
        }

        $this->manager->enable();

        $io->success('Mode maintenance ACTIVÉ.');

        return $this->showCurrentState($io) ?? Command::SUCCESS;
    }

    private function showCurrentState(SymfonyStyle $io): int
    {
        $state = $this->manager->getState();

        $io->table(['Champ', 'Valeur'], [
            ['Depuis',         $state->since?->format('d/m/Y H:i:s') ?? 'n/a'],
            ['Jusqu\'à',       $state->until?->format('d/m/Y H:i:s') ?? 'indéfini'],
            ['Message',        $state->message ?? '(par défaut)'],
            ['Overdue message', $state->overdueMessage ?? '(par défaut)'],
            ['Retry-After',    $state->retryAfter . ' s'],
        ]);

        $io->writeln(sprintf(
            '  <comment>Astuce :</comment> testez avec <info>curl -I %s</info> depuis une IP non whitelistée.',
            rtrim(getenv('DEFAULT_URI') ?: 'http://localhost:8000', '/') . '/',
        ));

        return Command::SUCCESS;
    }
}