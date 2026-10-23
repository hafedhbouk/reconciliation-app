<?php

namespace App\Http\Controllers\Admin;

/**
 * Écran « Mes opérations » et données de suivi en JSON.
 *
 * Le navigateur relit l'avancement toutes les 2 secondes (polling) tant
 * qu'une opération est en attente ou en cours : voir resources/js/operations.js.
 * Chacun ne voit que ses opérations ; les administrateurs peuvent tout voir
 * (OperationPolicy).
 * (Les notions de contrôleur sont expliquées dans ImportController.php.)
 */
use App\Http\Controllers\Controller;
use App\Models\Operation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OperationController extends Controller
{
    /** Nombre d'opérations récentes affichées. */
    private const LIMIT = 50;

    public function index(Request $request): View
    {
        $all = $this->showsAll($request);

        return view('admin.operations.index', [
            'all' => $all,
            'canViewAll' => Gate::allows('viewAll', Operation::class),
            'operations' => $this->visible($all)->latest('id')->limit(self::LIMIT)->get()
                ->map->toProgressArray()->values(),
        ]);
    }

    /** Liste en JSON (page « Mes opérations » et indicateur de la barre du haut). */
    public function data(Request $request): JsonResponse
    {
        $all = $this->showsAll($request);

        return response()->json([
            'operations' => $this->visible($all)->latest('id')->limit(self::LIMIT)->get()
                ->map->toProgressArray()->values(),
            'active_count' => $this->visible(false)->whereIn('status', ['pending', 'running'])->count(),
        ]);
    }

    /** Une opération en JSON (barre de progression intégrée à une page). */
    public function show(Operation $operation): JsonResponse
    {
        $this->authorize('view', $operation);

        return response()->json($operation->load('user')->toProgressArray());
    }

    /** ?all=1 : toutes les opérations, réservé aux administrateurs. */
    private function showsAll(Request $request): bool
    {
        return $request->boolean('all') && Gate::allows('viewAll', Operation::class);
    }

    private function visible(bool $all): Builder
    {
        return Operation::query()->with('user')
            ->when(! $all, fn (Builder $query) => $query->where('user_id', auth()->id()));
    }
}
