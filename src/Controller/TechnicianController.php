<?php

namespace App\Controller;

use App\Entity\Technician;
use App\Repository\TechnicianRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TechnicianController extends AbstractController
{
    #[Route('/technicians', name: 'technician_index')]
    public function index(TechnicianRepository $technicianRepository, TicketRepository $ticketRepository)
    {
        $technicians = $technicianRepository->findBy([], ['name' => 'ASC']);

        $counts = [];
        foreach ($technicians as $technician) {
            $counts[$technician->getId()] = $ticketRepository->countOpenForTechnician($technician);
        }

        return $this->render('technician/index.html.twig', [
            'technicians' => $technicians,
            'counts' => $counts,
        ]);
    }

    public function switcher(Request $request, TechnicianRepository $technicianRepository)
    {
        return $this->render('technician/_switcher.html.twig', [
            'technicians' => $technicianRepository->findBy(['active' => true]),
            'current' => $request->getSession()->get('technician_id'),
        ]);
    }

    // choix du technicien courant, en attendant une vraie authentification
    #[Route('/technicians/switch', name: 'technician_switch', methods: ['POST'])]
    public function switch(Request $request, EntityManagerInterface $em)
    {
        $id = $request->request->get('technician_id');
        if ($id) {
            $technician = $em->getRepository(Technician::class)->find($id);
            $request->getSession()->set('technician_id', $technician->getId());
            $request->getSession()->set('technician_name', $technician->getName());
        } else {
            $request->getSession()->remove('technician_id');
            $request->getSession()->remove('technician_name');
        }

        $referer = $request->headers->get('referer');
        if ($referer == NULL)
            $referer = $this->generateUrl('dashboard');

        return $this->redirect($referer);
    }

    #[Route('/technicians/{id}/toggle', name: 'technician_toggle', methods: ['POST'])]
    public function toggle($id, EntityManagerInterface $em)
    {
        $technician = $em->getRepository(Technician::class)->find($id);
        if (!$technician) {
            return new Response('Technicien introuvable', 404);
        }

        $technician->setActive(!$technician->isActive());
        $em->flush();

        return $this->redirectToRoute('technician_index');
    }
}
