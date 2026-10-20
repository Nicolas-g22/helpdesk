<?php

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\Customer;
use App\Entity\Technician;
use App\Entity\Ticket;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $customers = [];
        foreach ([
            ['martin', 'Boulangerie Martin', 'contact@boulangerie-martin.fr', 'basic'],
            ['lefevre', 'Cabinet Lefèvre Avocats', 'accueil@lefevre-avocats.fr', 'premium'],
            ['mairie', 'Mairie de Laval', 'informatique@laval.fr', 'premium'],
            ['dupont', 'Garage Dupont', 'garage.dupont@orange.fr', 'basic'],
            ['velo', 'Association Les Amis du Vélo', 'bureau@amisduvelo.org', 'basic'],
        ] as [$key, $name, $email, $plan]) {
            $customer = new Customer();
            $customer->setName($name);
            $customer->setEmail($email);
            $customer->setPlan($plan);
            $manager->persist($customer);
            $customers[$key] = $customer;
        }

        $technicians = [];
        foreach ([
            ['camille', 'Camille Roux', 'camille.roux@helpdesk.local', true],
            ['karim', 'Karim Benali', 'karim.benali@helpdesk.local', true],
            ['julie', 'Julie Morel', 'julie.morel@helpdesk.local', true],
            ['thomas', 'Thomas Petit', 'thomas.petit@helpdesk.local', false],
        ] as [$key, $name, $email, $active]) {
            $technician = new Technician();
            $technician->setName($name);
            $technician->setEmail($email);
            $technician->setActive($active);
            $manager->persist($technician);
            $technicians[$key] = $technician;
        }

        // [client, titre, description, statut, priorité, assigné, créé il y a, résolu il y a]
        $tickets = [
            ['martin', 'La caisse ne démarre plus', 'Depuis ce matin la caisse enregistreuse reste bloquée sur l\'écran de démarrage.', 'new', 3, null, '-2 hours', null],
            ['martin', 'Imprimante de tickets hors ligne', 'L\'imprimante thermique n\'apparaît plus dans la liste des périphériques.', 'in_progress', 2, 'karim', '-3 days', null],
            ['martin', 'Changer le mot de passe du Wi-Fi', 'Nous voulons changer le mot de passe du Wi-Fi client.', 'resolved', 1, 'camille', '-12 days', '-9 days'],
            ['lefevre', 'Messagerie inaccessible', 'Aucun collaborateur ne reçoit plus d\'e-mails depuis 8h.', 'in_progress', 4, 'camille', '-5 hours', null],
            ['lefevre', 'Nouveau poste pour une stagiaire', 'Préparer un poste pour l\'arrivée de notre stagiaire <strong>lundi prochain</strong>.', 'new', 2, null, '-1 day', null],
            ['lefevre', 'Sauvegarde du serveur en échec', 'Le rapport de sauvegarde indique une erreur depuis trois nuits.', 'resolved', 3, 'karim', '-14 days', '-10 days'],
            ['lefevre', 'Licence du logiciel de comptabilité', 'La licence expire à la fin du mois.', 'closed', 1, 'julie', '-40 days', '-35 days'],
            ['mairie', 'Site de la mairie très lent', 'Les pages du site mettent plus de 10 secondes à s\'afficher.', 'in_progress', 4, 'karim', '-1 day', null],
            ['mairie', 'Accès au logiciel d\'état civil', 'Deux agents n\'ont plus accès au logiciel d\'état civil.', 'in_progress', 3, 'karim', '-2 days', null],
            ['mairie', 'Écran cassé à l\'accueil', 'L\'écran du poste d\'accueil est fissuré.', 'new', 3, null, '-4 days', null],
            ['mairie', 'Mise à jour des postes de la médiathèque', 'Planifier la mise à jour des 12 postes publics.', 'in_progress', 2, 'julie', '-8 days', null],
            ['mairie', 'Badge d\'accès perdu', 'Un agent a perdu son badge, il faut le désactiver.', 'resolved', 3, 'camille', '-3 days', '-2 days'],
            ['dupont', 'Logiciel de devis qui plante', 'Le logiciel de devis se ferme à chaque export en PDF.', 'in_progress', 2, 'karim', '-6 days', null],
            ['dupont', 'Installer un antivirus', 'Nous n\'avons plus d\'antivirus depuis le changement d\'ordinateur.', 'new', 1, null, '-9 days', null],
            ['dupont', 'Problème de connexion internet', 'La connexion coupe plusieurs fois par jour.', 'closed', 3, 'julie', '-30 days', '-25 days'],
            ['velo', 'Créer des adresses e-mail', 'Créer trois adresses pour les nouveaux membres du bureau.', 'new', 1, null, '-6 hours', null],
            ['velo', 'Site web : formulaire d\'adhésion', 'Le formulaire d\'adhésion du site n\'envoie plus de confirmation.', 'in_progress', 3, 'julie', '-5 days', null],
            ['velo', 'Tableur des adhérents corrompu', 'Le fichier des adhérents ne s\'ouvre plus.', 'resolved', 2, 'camille', '-4 days', '-1 day'],
            ['martin', 'Terminal de paiement', 'Le terminal de paiement affiche une erreur de communication.', 'in_progress', 3, 'karim', '-1 day', null],
            ['mairie', 'Accès VPN pour le télétravail', 'Configurer le VPN pour trois agents en télétravail.', 'new', 2, 'thomas', '-15 days', null],
        ];

        foreach ($tickets as $i => [$customerKey, $title, $description, $status, $priority, $assigneeKey, $createdAgo, $resolvedAgo]) {
            $ticket = new Ticket();
            $ticket->setTitle($title);
            $ticket->setDescription($description);
            $ticket->setStatus($status);
            $ticket->setPriority($priority);
            $ticket->setCustomer($customers[$customerKey]);
            $ticket->setAssignee($assigneeKey ? $technicians[$assigneeKey] : null);
            $ticket->setCreatedAt(new \DateTime($createdAgo));
            if ($resolvedAgo) {
                $ticket->setResolvedAt(new \DateTime($resolvedAgo));
            }
            if ($status === 'closed') {
                $ticket->setClosedAt((new \DateTime($resolvedAgo))->modify('+7 days'));
            }
            $manager->persist($ticket);

            if ($i % 3 === 1) {
                $comment = new Comment();
                $comment->setContent('Pouvez-vous nous donner des nouvelles ?');
                $comment->setAuthorName($customers[$customerKey]->getName());
                $comment->setFromCustomer(true);
                $comment->setCreatedAt(new \DateTime($createdAgo.' +1 hour'));
                $ticket->addComment($comment);
                $manager->persist($comment);
            }
            if ($assigneeKey && 'new' !== $status) {
                $comment = new Comment();
                $comment->setContent('Je prends le ticket, je reviens vers vous rapidement.');
                $comment->setAuthorName($technicians[$assigneeKey]->getName());
                $comment->setCreatedAt(new \DateTime($createdAgo.' +2 hours'));
                $ticket->addComment($comment);
                $manager->persist($comment);
            }
        }

        $manager->flush();
    }
}
