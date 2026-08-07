# Plan d'implémentation - Rôles et permissions

## Objectif
Implémenter les 3 nouveaux rôles : `directeur` (consultation seule), `chef-departement` (tous les droits sauf gestion des rôles), `agent-execution` (rapprochement uniquement).

## Étapes

- [x] 1. Mettre à jour `database/seeders/RolePermissionSeeder.php` - Ajouter les 3 nouveaux rôles avec leurs permissions
- [x] 2. Mettre à jour `GUIDE_UTILISATEUR.md` - Remplacer la section "Rôles et permissions"
- [x] 3. Mettre à jour `tests/Pest.php` - Ajouter des helpers de test pour les 3 nouveaux rôles
- [x] 4. Mettre à jour `tests/Feature/RolePermissionTest.php` - Ajouter des tests de vérification
- [x] 5. Exécuter `php artisan db:seed --class=RolePermissionSeeder` pour créer les nouveaux rôles (réussi sur MySQL)
- [x] 6. Exécuter `php artisan test` pour valider les tests (238 passed, 810 assertions)
