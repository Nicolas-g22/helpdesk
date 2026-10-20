<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Customer;
use App\Entity\Technician;
use App\Entity\Ticket;
use App\Repository\TicketRepository;
use App\Service\NotificationService;
use App\Util\TicketHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TicketController extends AbstractController
{
    const STATUS_NEW = 'new';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    const PER_PAGE = 10;

    private $em;
    private $notifier;
    private $maxOpenTickets;

    public function __construct(EntityManagerInterface $em, NotificationService $notifier, int $maxOpenTickets)
    {
        $this->em = $em;
        $this->notifier = $notifier;
        $this->maxOpenTickets = $maxOpenTickets;
    }

    #[Route('/tickets', name: 'ticket_index', methods: ['GET'])]
    public function index(Request $request, TicketRepository $ticketRepository): Response
    {
        $q = $request->query->get('q');

        // "#12" ouvre directement le ticket 12
        if (substr($q, 0, 1) == '#' && is_numeric(substr($q, 1))) {
            return $this->redirectToRoute('ticket_show', array('id' => substr($q, 1)));
        }

        if ($q != '') {
            $results = $ticketRepository->search($q);

            return $this->render('ticket/search.html.twig', ['results' => $results, 'q' => $q]);
        }

        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $page = $request->query->get('page', 1);
        if ($page < 1) $page = 1;

        $tickets = $ticketRepository->findFiltered($status, $priority, $page);
        $total = $ticketRepository->countFiltered($status, $priority);
        $pages = ceil($total / self::PER_PAGE);

        return $this->render('ticket/index.html.twig', [
            'tickets' => $tickets,
            'status' => $status,
            'priority' => $priority,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    #[Route('/tickets/new', name: 'ticket_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $customers = $this->em->getRepository(Customer::class)->findBy([], ['name' => 'ASC']);
        $errors = array();
        $data = array('title' => '', 'description' => '', 'customer_id' => '', 'priority' => 2);

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            if (trim($data['title']) == '') {
                $errors['title'] = 'Le titre est obligatoire';
            } elseif (strlen($data['title']) > 255) {
                $errors['title'] = 'Le titre ne doit pas dépasser 255 caractères';
            }
            if (trim($data['description']) == '') {
                $errors['description'] = 'La description est obligatoire';
            }
            $customer = $this->em->getRepository(Customer::class)->find($data['customer_id']);
            if (!$customer) {
                $errors['customer_id'] = 'Client inconnu';
            }
            if (!in_array($data['priority'], array('1', '2', '3', '4'))) {
                $errors['priority'] = 'Priorité invalide';
            }

            if (count($errors) == 0) {
                // la priorité dépend du client
                $priority = (int) $data['priority'];
                if ($customer->getPlan() == 'premium') {
                    $priority = $priority + 1;
                    if ($priority > 4) {
                        $priority = 4;
                    }
                } else {
                    if ($priority > 3) {
                        $priority = 3;
                    }
                }

                $ticket = new Ticket();
                $ticket->setTitle(trim($data['title']));
                $ticket->setDescription($data['description']);
                $ticket->setCustomer($customer);
                $ticket->setPriority($priority);
                $ticket->setStatus(self::STATUS_NEW);

                $this->em->persist($ticket);
                $this->em->flush();

                $this->notifier->notify($ticket, 'created');

                $this->addFlash('success', 'Ticket #' . $ticket->getId() . ' créé');

                return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
            }
        }

        return $this->render('ticket/new.html.twig', [
            'customers' => $customers,
            'errors' => $errors,
            'data' => $data,
        ]);
    }

    #[Route('/tickets/{id}', name: 'ticket_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show($id): Response
    {
        $ticket = $this->em->getRepository(Ticket::class)->find($id);
        if (!$ticket) {
            return new Response('Ticket introuvable', 404);
        }

        $technicians = $this->em->getRepository(Technician::class)->findBy(['active' => true], ['name' => 'ASC']);

        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
            'technicians' => $technicians,
            'due_date' => TicketHelper::dueDate($ticket),
        ]);
    }

    #[Route('/tickets/{id}/assign', name: 'ticket_assign', methods: ['POST'])]
    public function assign($id, Request $request, TicketRepository $ticketRepository): Response
    {
        $ticket = $ticketRepository->find($id);
        if (!$ticket) {
            return new Response('Ticket introuvable', 404);
        }

        if ($ticket->getStatus() == self::STATUS_CLOSED) {
            $this->addFlash('error', 'Un ticket fermé ne peut pas être réassigné');

            return $this->redirectToRoute('ticket_show', ['id' => $id]);
        }

        $technicianId = $request->request->get('technician_id');
        if ($technicianId == '') {
            $ticket->setAssignee(null);
            $this->em->flush();
            $this->addFlash('success', 'Le ticket n\'est plus assigné');

            return $this->redirectToRoute('ticket_show', ['id' => $id]);
        }

        $technician = $this->em->getRepository(Technician::class)->find($technicianId);
        if (!$technician || !$technician->isActive()) {
            $this->addFlash('error', 'Technicien inconnu ou inactif');

            return $this->redirectToRoute('ticket_show', ['id' => $id]);
        }

        if ($ticketRepository->countOpenForTechnician($technician) >= $this->maxOpenTickets) {
            $this->addFlash('error', $technician->getName() . ' a déjà ' . $this->maxOpenTickets . ' tickets ouverts');

            return $this->redirectToRoute('ticket_show', ['id' => $id]);
        }

        $ticket->setAssignee($technician);
        if ($ticket->getStatus() == self::STATUS_NEW) {
            $ticket->setStatus(self::STATUS_IN_PROGRESS);
        }
        $this->em->flush();

        $this->notifier->notify($ticket, 'assigned');
        $this->addFlash('success', 'Ticket assigné à ' . $technician->getName());

        return $this->redirectToRoute('ticket_show', ['id' => $id]);
    }

    #[Route('/tickets/{id}/status', name: 'ticket_status', methods: ['POST'])]
    public function changeStatus($id, Request $request): Response
    {
        $ticket = $this->em->getRepository(Ticket::class)->find($id);
        if (!$ticket) {
            return new Response('Ticket introuvable', 404);
        }

        $old = $ticket->getStatus();
        $new = $request->request->get('status');

        $allowed = false;
        if ($old == self::STATUS_NEW && $new == self::STATUS_IN_PROGRESS) {
            // un ticket ne passe en cours que s'il est assigné
            if ($ticket->getAssignee() != null) {
                $allowed = true;
            }
        } else if ($old == self::STATUS_IN_PROGRESS && $new == self::STATUS_RESOLVED) {
            $allowed = true;
        } else if ($old == self::STATUS_RESOLVED && $new == self::STATUS_IN_PROGRESS) {
            $allowed = true;
        } else if ($old == self::STATUS_RESOLVED && $new == self::STATUS_CLOSED) {
            $allowed = true;
        }

        if (!$allowed) {
            $this->addFlash('error', 'Changement de statut impossible');

            return $this->redirectToRoute('ticket_show', ['id' => $id]);
        }

        $ticket->setStatus($new);
        if ($new == self::STATUS_RESOLVED) {
            $ticket->setResolvedAt(new \DateTime());
        }
        if ($new == self::STATUS_IN_PROGRESS) {
            $ticket->setResolvedAt(null);
        }
        if ($new == self::STATUS_CLOSED) {
            $ticket->setClosedAt(new \DateTime());
        }
        $this->em->flush();

        if ($new == self::STATUS_RESOLVED) {
            $this->notifier->notify($ticket, 'resolved');
        }

        $this->addFlash('success', 'Statut mis à jour : ' . $ticket->getStatusLabel());

        return $this->redirectToRoute('ticket_show', ['id' => $id]);
    }

    #[Route('/tickets/{id}/comment', name: 'ticket_comment', methods: ['POST'])]
    public function comment($id, Request $request): Response
    {
        $ticket = $this->em->getRepository(Ticket::class)->find($id);
        if (!$ticket) {
            return new Response('Ticket introuvable', 404);
        }

        if ($ticket->isClosed()) {
            $this->addFlash('error', 'Impossible de commenter un ticket fermé');

            return $this->redirectToRoute('ticket_show', ['id' => $id]);
        }

        $content = trim($request->request->get('content'));
        if ($content == '') {
            $this->addFlash('error', 'Le commentaire est vide');

            return $this->redirectToRoute('ticket_show', ['id' => $id]);
        }

        $fromCustomer = $request->request->get('from_customer') == '1';

        $comment = new Comment();
        $comment->setContent($content);
        $comment->setFromCustomer($fromCustomer);
        if ($fromCustomer) {
            $comment->setAuthorName($ticket->getCustomer()->getName());
        } else {
            $technicianId = $request->getSession()->get('technician_id');
            $technician = $technicianId ? $this->em->getRepository(Technician::class)->find($technicianId) : null;
            $comment->setAuthorName($technician ? $technician->getName() : 'Support');
        }
        $ticket->addComment($comment);

        // une réponse du client sur un ticket résolu le rouvre
        if ($fromCustomer && $ticket->getStatus() == self::STATUS_RESOLVED) {
            $ticket->setStatus(self::STATUS_IN_PROGRESS);
            $ticket->setResolvedAt(null);
        }

        $this->em->persist($comment);
        $this->em->flush();

        return $this->redirectToRoute('ticket_show', ['id' => $id]);
    }

    #[Route('/tickets/{id}/delete', name: 'ticket_delete', methods: ['GET'])]
    public function delete($id): RedirectResponse
    {
        $ticket = $this->em->getRepository(Ticket::class)->find($id);
        if ($ticket) {
            $this->em->remove($ticket);
            $this->em->flush();
            $this->addFlash('success', 'Ticket supprimé');
        }

        return $this->redirectToRoute('ticket_index');
    }
}
