<?php

/*
| Commandes en ligne de commande (« Artisan ») définies par l'application.
| On les lance avec `php artisan <nom>` ; `php artisan list` les affiche toutes.
| Seule la commande d'exemple de Laravel (« inspire ») est définie ici. Les
| commandes utiles au quotidien sont celles de Laravel lui-même, par exemple :
|   php artisan migrate       crée ou met à jour les tables (dossier database/migrations)
|   php artisan db:seed       remplit les données de base (dossier database/seeders)
|   php artisan queue:work    démarre le worker qui exécute les jobs (imports, rapprochements)
|   php artisan test          lance les tests automatiques
*/

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
