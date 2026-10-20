# Helpdesk

Application de gestion des demandes de support : les clients ouvrent des tickets, les techniciens les prennent en charge, les commentent et les résolvent.

## Prérequis

- PHP 8.4 ou plus, avec les extensions `pdo_sqlite`, `ctype` et `iconv`
- Composer
- [Symfony CLI](https://symfony.com/download) (facultatif, pour le serveur local)

## Installation

```bash
git clone https://github.com/iia-laval/helpdesk.git
cd helpdesk
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
symfony serve -d
```

L'application est servie sur https://127.0.0.1:8000 (sans Symfony CLI : `php -S 127.0.0.1:8000 -t public`).

La base de données est un fichier SQLite, `var/data_dev.db`. Pour repartir de zéro, supprimez-le puis relancez les deux commandes Doctrine.

## Fonctionnalités

- Tableau de bord : compteurs, tickets en retard, tickets du technicien courant
- Tickets : liste filtrable, recherche (`#12` ouvre directement le ticket 12), création, assignation, changement de statut, commentaires
- Clients et techniciens
- API JSON pour le formulaire de contact du site public : `GET /api/tickets`, `GET /api/tickets/{id}`, `POST /api/tickets`

Le technicien courant se choisit dans la barre du haut.

## Tâches planifiées

Fermeture automatique des tickets résolus depuis plus de 7 jours, à lancer chaque nuit :

```bash
php bin/console app:tickets:close-resolved
```

Les notifications sont écrites dans `var/notifications.log`.
