<?php

namespace App\Command;

use App\Controller\TicketController;
use App\Repository\TicketRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Ferme les tickets résolus depuis plus de 7 jours sans réponse du client.
 * Lancée chaque nuit par le cron du serveur.
 */
#[AsCommand(name: 'app:tickets:close-resolved', description: 'Ferme les tickets résolus depuis plus de 7 jours')]
class CloseResolvedTicketsCommand extends Command
{
    private $ticketRepository;
    private $em;
    private $notifier;

    public function __construct(TicketRepository $ticketRepository, EntityManagerInterface $em, NotificationService $notifier)
    {
        parent::__construct();
        $this->ticketRepository = $ticketRepository;
        $this->em = $em;
        $this->notifier = $notifier;
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les tickets sans les fermer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = new \DateTime('-7 days');
        $tickets = $this->ticketRepository->findResolvedBefore($limit);

        if (count($tickets) == 0) {
            $output->writeln('Aucun ticket à fermer');

            return Command::SUCCESS;
        }

        foreach ($tickets as $ticket) {
            $output->writeln(sprintf('#%d %s (résolu le %s)', $ticket->getId(), $ticket->getTitle(), $ticket->getResolvedAt()->format('d/m/Y')));

            if (!$input->getOption('dry-run')) {
                $ticket->setStatus(TicketController::STATUS_CLOSED);
                $ticket->setClosedAt(new \DateTime());
                $this->notifier->notify($ticket, 'auto_closed');
            }
        }

        if (!$input->getOption('dry-run')) {
            $this->em->flush();
        }

        $output->writeln(count($tickets) . ' ticket(s) traité(s)');

        return Command::SUCCESS;
    }
}
