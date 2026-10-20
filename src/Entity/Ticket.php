<?php

namespace App\Entity;

use App\Controller\TicketController;
use App\Repository\TicketRepository;
use App\Util\TicketHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(length: 255)]
    private $title;

    #[ORM\Column(type: 'text')]
    private $description;

    #[ORM\Column(length: 20)]
    private $status = 'new';

    #[ORM\Column(type: 'integer')]
    private $priority = 2;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private $customer;

    #[ORM\ManyToOne(targetEntity: Technician::class, inversedBy: 'tickets')]
    private $assignee;

    #[ORM\Column(type: 'datetime')]
    private $createdAt;

    #[ORM\Column(type: 'datetime')]
    private $updatedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $resolvedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $closedAt;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'ticket', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private $comments;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return string
     */
    public function getPriority()
    {
        return $this->priority;
    }

    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer)
    {
        $this->customer = $customer;

        return $this;
    }

    public function getAssignee()
    {
        return $this->assignee;
    }

    public function setAssignee($assignee)
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @return \DateTime
     */
    public function getResolvedAt()
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt($resolvedAt)
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getClosedAt()
    {
        return $this->closedAt;
    }

    public function setClosedAt($closedAt)
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment)
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setTicket($this);
        }

        return $this;
    }

    public function isClosed()
    {
        return $this->status == TicketController::STATUS_CLOSED;
    }

    public function isLate()
    {
        if ($this->status == TicketController::STATUS_RESOLVED || $this->status == TicketController::STATUS_CLOSED) {
            return false;
        }

        return TicketHelper::dueDate($this) < new \DateTime();
    }

    public function getStatusLabel(): string
    {
        switch ($this->status) {
            case 'new':
                return 'Nouveau';
            case 'in_progress':
                return 'En cours';
            case 'resolved':
                return 'Résolu';
            case 'closed':
                return 'Fermé';
        }
    }
}
