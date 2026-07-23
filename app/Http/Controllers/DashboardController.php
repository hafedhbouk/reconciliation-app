<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardMetricsService $metrics)
    {
    }

    public function index(): View
    {
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
