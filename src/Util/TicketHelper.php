<?php

namespace App\Util;

use App\Entity\Customer;
use App\Entity\Ticket;

class TicketHelper
{
    /**
     * Calcule la priorité réelle d'un ticket à partir de la priorité demandée par le client.
     * Les clients premium passent au niveau supérieur, les clients basic ne peuvent pas ouvrir de ticket urgent.
     */
    public static function computePriority($requested, Customer $customer)
    {
        $priority = (int) $requested;
        if ($priority < 1) {
            $priority = 1;
        }

        if ($customer->isPremium()) {
            $priority = $priority + 1;
            if ($priority > 4) {
                $priority = 4;
            }
        } else {
            if ($priority > 3) {
                $priority = 3;
            }
        }

        return $priority;
    }

    /**
     * Date limite de prise en charge (SLA) : 4 heures pour un ticket urgent,
     * puis 1, 3 ou 5 jours ouvrés selon la priorité. Les week-ends ne comptent pas.
     */
    public static function dueDate(Ticket $ticket)
    {
        $date = clone $ticket->getCreatedAt();

        if ($ticket->getPriority() == 4) {
            return $date->modify('+4 hours');
        }

        switch ($ticket->getPriority()) {
            case 3:
                $days = 1;
                break;
            case 2:
                $days = 3;
                break;
            case 1:
                $days = 5;
                break;
        }

        while ($days > 0) {
            $date->modify('+1 day');
            if ($date->format('N') < 6) {
                $days--;
            }
        }

        return $date;
    }

    public static function priorityLabel($priority)
    {
        $labels = array(1 => 'Basse', 2 => 'Normale', 3 => 'Haute', 4 => 'Urgente');

        return isset($labels[$priority]) ? $labels[$priority] : 'Inconnue';
    }

    public static function isValidEmail($email)
    {
        return preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/', $email);
    }
}
