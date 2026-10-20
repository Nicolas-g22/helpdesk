<?php

namespace App\Controller;

use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(EntityManagerInterface $em, Request $request)
    {
        $tickets = $em->getRepository(Ticket::class)->findAll();

        $stats = [
            'new' => 0,
            'in_progress' => 0,
            'resolved' => 0,
            'closed' => 0,
            'late' => 0,
            'unassigned' => 0,
        ];
        $late = [];
        $mine = [];

        foreach ($tickets as $ticket) {
            $stats[$ticket->getStatus()]++;

            if ($ticket->isLate()) {
                $stats['late']++;
                $late[] = $ticket;
            }

            if ($ticket->getAssignee() == NULL && $ticket->getStatus() != 'closed')
            {
                $stats['unassigned']++;
            }

            $technicianId = $request->getSession()->get('technician_id');
            if ($technicianId && $ticket->getAssignee() && $ticket->getAssignee()->getId() == $technicianId
                && ($ticket->getStatus() == 'new' || $ticket->getStatus() == 'in_progress')) {
                $mine[] = $ticket;
            }
        }

        usort($late, function ($a, $b) {
            return $b->getPriority() - $a->getPriority();
        });

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            'late' => $late,
            'mine' => $mine,
        ]);
    }
}
