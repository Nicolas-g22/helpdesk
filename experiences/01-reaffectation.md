Quand un technicien quitte l'équipe ou part en congé, on le désactive depuis la page Techniciens, mais ses tickets restent à son nom et plus personne ne les traite.

Ajoute la réaffectation des tickets d'un technicien : sur la page Techniciens, chaque technicien a un bouton « Réaffecter ses tickets » et une liste des autres techniciens. La validation transfère ses tickets au technicien choisi, puis revient sur la page Techniciens avec un message qui indique combien de tickets ont été réaffectés.

Le formulaire envoie une requête `POST` vers `/technicians/{id}/transfer`, où `{id}` est le technicien dont on reprend les tickets, avec un champ `target_id` : l'identifiant du technicien qui les reprend.
