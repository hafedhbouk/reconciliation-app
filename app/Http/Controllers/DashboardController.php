<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use Illuminate\View\View;

/**
 * Page d'accueil après connexion (/dashboard) : chiffres clés et graphiques.
 *
 * Le contrôleur reste très court : tous les calculs sont dans
 * DashboardMetricsService. C'est une bonne pratique : un contrôleur
 * « orchestre », un service « calcule ».
 */
class DashboardController extends Controller
{
    /** Laravel fournit automatiquement le service (injection de dépendances). */
    public function __construct(private DashboardMetricsService $metrics) {}

    public function index(): View
    {
        // Chaque clé du tableau devient une variable dans la vue resources/views/dashboard.blade.php.
        return view('dashboard', [
            'importStats' => $this->metrics->importStats(),
            'matchingStats' => $this->metrics->matchingStats(),
            'exceptionStats' => $this->metrics->exceptionStats(),
            'volumeBySource' => $this->metrics->transactionVolumeBySource(),
            'dailyTrend' => $this->metrics->dailyTransactionTrend(),
            'totalTransactions' => $this->metrics->totalTransactions(),
        ]);
    }
}
