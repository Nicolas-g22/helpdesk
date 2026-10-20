<?php

namespace App\Controller\Api;

use App\Controller\TicketController;
use App\Entity\Customer;
use App\Entity\Ticket;
use App\Util\TicketHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API utilisée par le formulaire de contact du site public.
 */
class TicketApiController extends AbstractController
{
    #[Route('/api/tickets', name: 'api_ticket_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em)
    {
        $tickets = $em->getRepository(Ticket::class)->findBy([], ['createdAt' => 'DESC']);

        $data = [];
        foreach ($tickets as $ticket) {
            $data[] = $this->toArray($ticket);
        }

        return new Response(json_encode($data), 200, ['Content-Type' => 'application/json']);
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_show', methods: ['GET'])]
    public function show($id, EntityManagerInterface $em)
    {
        $ticket = $em->getRepository(Ticket::class)->find($id);
        if (!$ticket) {
            return new Response(json_encode(['error' => 'Ticket introuvable']), 404, ['Content-Type' => 'application/json']);
        }

        $data = $this->toArray($ticket);
        $data['comments'] = [];
        foreach ($ticket->getComments() as $comment) {
            $data['comments'][] = [
                'author' => $comment->getAuthorName(),
                'content' => $comment->getContent(),
                'created_at' => $comment->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }

        return new Response(json_encode($data), 200, ['Content-Type' => 'application/json']);
    }

    #[Route('/api/tickets', name: 'api_ticket_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em)
    {
        $payload = json_decode($request->getContent(), true);

        if (!isset($payload['title']) || !isset($payload['description']) || !isset($payload['customer_email'])) {
            return new Response(json_encode(['error' => 'Champs manquants']), 400, ['Content-Type' => 'application/json']);
        }

        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => $payload['customer_email']]);
        if (!$customer) {
            return new Response(json_encode(['error' => 'Client inconnu']), 400, ['Content-Type' => 'application/json']);
        }

        $priority = isset($payload['priority']) ? $payload['priority'] : 2;
        if ($customer->isPremium() && $priority < 4) {
            $priority++;
        }

        $ticket = new Ticket();
        $ticket->setTitle($payload['title']);
        $ticket->setDescription($payload['description']);
        $ticket->setCustomer($customer);
        $ticket->setPriority($priority);
        $ticket->setStatus(TicketController::STATUS_NEW);
        $em->persist($ticket);
        $em->flush();

        return new Response(json_encode($this->toArray($ticket)), 201, ['Content-Type' => 'application/json']);
    }

    private function toArray(Ticket $ticket)
    {
        return [
            'id' => $ticket->getId(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'status' => $ticket->getStatus(),
            'status_label' => $ticket->getStatusLabel(),
            'priority' => $ticket->getPriority(),
            'priority_label' => TicketHelper::priorityLabel($ticket->getPriority()),
            'customer' => [
                'id' => $ticket->getCustomer()->getId(),
                'name' => $ticket->getCustomer()->getName(),
                'email' => $ticket->getCustomer()->getEmail(),
                'plan' => $ticket->getCustomer()->getPlan(),
            ],
            'assignee' => $ticket->getAssignee() ? $ticket->getAssignee()->getName() : null,
            'created_at' => $ticket->getCreatedAt()->format('Y-m-d H:i'),
            'due_date' => TicketHelper::dueDate($ticket)->format('Y-m-d H:i'),
        ];
    }
}
