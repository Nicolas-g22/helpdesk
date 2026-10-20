<?php

namespace App\Twig;

use App\Controller\TicketController;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('priority_label', [$this, 'priorityLabel']),
            new TwigFilter('status_class', [$this, 'statusClass']),
            new TwigFilter('due_date', [$this, 'dueDate']),
        ];
    }

    public function priorityLabel($priority)
    {
        switch ($priority) {
            case 1: return 'Basse';
            case 2: return 'Normale';
            case 3: return 'Haute';
            case 4: return 'Urgente';
            default: return '';
        }
    }

    public function statusClass($status)
    {
        if ($status == TicketController::STATUS_NEW) {
            return 'badge-new';
        } elseif ($status == TicketController::STATUS_IN_PROGRESS) {
            return 'badge-progress';
        } elseif ($status == TicketController::STATUS_RESOLVED) {
            return 'badge-resolved';
        }

        return 'badge-closed';
    }

    // date limite de prise en charge, affichée dans les listes
    public function dueDate($ticket)
    {
        $hours = [1 => 120, 2 => 72, 3 => 24, 4 => 4];
        $date = clone $ticket->getCreatedAt();

        return $date->modify('+' . $hours[$ticket->getPriority()] . ' hours');
    }
}
