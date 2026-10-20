<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Ticket;
use App\Util\TicketHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CustomerController extends AbstractController
{
    #[Route('/customers', name: 'customer_index')]
    public function index(ManagerRegistry $doctrine)
    {
        $customers = $doctrine->getRepository(Customer::class)->findBy(array(), array('name' => 'ASC'));

        $rows = array();
        foreach ($customers as $customer) {
            $open = 0;
            foreach ($customer->getTickets() as $ticket) {
                if ($ticket->getStatus() != 'closed' && $ticket->getStatus() != 'resolved') {
                    $open++;
                }
            }
            $rows[] = array(
                'customer' => $customer,
                'open' => $open,
                'total' => count($customer->getTickets()),
            );
        }

        return $this->render('customer/index.html.twig', ['rows' => $rows]);
    }

    #[Route('/customers/new', name: 'customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em)
    {
        $errors = [];
        $data = ['name' => '', 'email' => '', 'plan' => 'basic'];

        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();

            if (trim($data['name']) == '') {
                $errors['name'] = 'Le nom est obligatoire';
            }
            if (!TicketHelper::isValidEmail($data['email'])) {
                $errors['email'] = 'Adresse e-mail invalide';
            } else {
                $existing = $em->getRepository(Customer::class)->findOneBy(['email' => $data['email']]);
                if ($existing) {
                    $errors['email'] = 'Un client existe déjà avec cette adresse';
                }
            }
            if ($data['plan'] != 'basic' && $data['plan'] != 'premium') {
                $errors['plan'] = 'Offre invalide';
            }

            if (empty($errors)) {
                $customer = new Customer();
                $customer->setName(trim($data['name']));
                $customer->setEmail(strtolower($data['email']));
                $customer->setPlan($data['plan']);
                $em->persist($customer);
                $em->flush();

                $this->addFlash('success', 'Client ' . $customer->getName() . ' créé');

                return $this->redirectToRoute('customer_show', ['id' => $customer->getId()]);
            }
        }

        return $this->render('customer/new.html.twig', ['errors' => $errors, 'data' => $data]);
    }

    #[Route('/customers/{id}', name: 'customer_show', requirements: ['id' => '\d+'])]
    public function show($id, EntityManagerInterface $em)
    {
        $customer = $em->getRepository(Customer::class)->find($id);
        if ($customer == null) {
            return new Response('Client introuvable', 404);
        }

        $tickets = $em->getRepository(Ticket::class)->findBy(['customer' => $customer], ['createdAt' => 'DESC']);

        $late = 0;
        foreach ($tickets as $ticket) {
            if ($ticket->isLate()) {
                $late++;
            }
        }

        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
            'tickets' => $tickets,
            'late' => $late,
        ]);
    }
}
