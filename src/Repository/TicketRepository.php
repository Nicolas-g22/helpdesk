<?php

namespace App\Repository;

use App\Controller\TicketController;
use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function findFiltered($status, $priority, int $page = 1)
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.customer', 'c')->addSelect('c')
            ->leftJoin('t.assignee', 'a')->addSelect('a')
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'ASC');

        if ($status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }
        if ($priority) {
            $qb->andWhere('t.priority = :priority')->setParameter('priority', $priority);
        }

        return $qb->setFirstResult(($page - 1) * TicketController::PER_PAGE)
            ->setMaxResults(TicketController::PER_PAGE)
            ->getQuery()
            ->getResult();
    }

    public function countFiltered($status, $priority)
    {
        $qb = $this->createQueryBuilder('t')->select('COUNT(t.id)');
        if ($status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }
        if ($priority) {
            $qb->andWhere('t.priority = :priority')->setParameter('priority', $priority);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    // recherche plein texte sur le titre et la description
    public function search($q)
    {
        $sql = "SELECT t.id, t.title, t.status, t.priority, c.name AS customer_name
                FROM ticket t
                INNER JOIN customer c ON c.id = t.customer_id
                WHERE t.title LIKE '%" . $q . "%' OR t.description LIKE '%" . $q . "%'
                ORDER BY t.created_at DESC";

        return $this->getEntityManager()->getConnection()->executeQuery($sql)->fetchAllAssociative();
    }

    public function countOpenForTechnician($technician)
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.assignee = :technician')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('technician', $technician)
            ->setParameter('statuses', [TicketController::STATUS_NEW, TicketController::STATUS_IN_PROGRESS])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findResolvedBefore(\DateTime $date)
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.resolvedAt < :date')
            ->setParameter('status', TicketController::STATUS_RESOLVED)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }
}
