<?php

namespace App\Service;

use App\Entity\Ticket;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Envoie les notifications aux clients et aux techniciens.
 * Pour l'instant les mails sont écrits dans un fichier, en attendant le branchement du serveur SMTP.
 */
class NotificationService
{
    private $logDir;
    private $requestStack;

    public function __construct($logDir, RequestStack $requestStack)
    {
        $this->logDir = $logDir;
        $this->requestStack = $requestStack;
    }

    public function notify(Ticket $ticket, $event)
    {
        $to = null;
        $subject = null;

        if ($event == 'created') {
            $to = $ticket->getCustomer()->getEmail();
            $subject = 'Votre demande #' . $ticket->getId() . ' a bien été enregistrée';
        } elseif ($event == 'assigned') {
            $to = $ticket->getAssignee()->getEmail();
            $subject = 'Le ticket #' . $ticket->getId() . ' vous a été assigné';
        } elseif ($event == 'resolved') {
            $to = $ticket->getCustomer()->getEmail();
            $subject = 'Votre demande #' . $ticket->getId() . ' est résolue';
        } elseif ($event == 'auto_closed') {
            // les clients premium sont prévenus sur l'adresse de leur contact dédié
            if ($ticket->getCustomer()->isPremium()) {
                $to = $ticket->getCustomer()->getContactEmail();
            } else {
                $to = $ticket->getCustomer()->getEmail();
            }
            $subject = 'Votre demande #' . $ticket->getId() . ' a été fermée';
        }

        if ($to == null) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        $link = $request ? $request->getSchemeAndHttpHost() . '/tickets/' . $ticket->getId() : '/tickets/' . $ticket->getId();

        $line = date('Y-m-d H:i:s') . ' | ' . $to . ' | ' . $subject . ' | ' . $link . "\n";
        file_put_contents($this->logDir . '/notifications.log', $line, FILE_APPEND);

        return true;
    }
}
