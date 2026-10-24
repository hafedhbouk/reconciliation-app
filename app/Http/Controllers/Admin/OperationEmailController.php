<?php

namespace App\Http\Controllers\Admin;

/**
 * Envoi des résultats d'un rapprochement terminé par e-mail, depuis « Mes
 * opérations » : choix des destinataires, historique des envois, relance d'un
 * envoi en échec. Le travail est fait par App\Services\ResultEmails.
 *
 * Accès : le rapprochement doit être visible par l'utilisateur
 * (OperationPolicy) et l'utilisateur doit pouvoir consulter les résultats.
 * (Les notions de contrôleur sont expliquées dans ImportController.php.)
 */
use App\Http\Controllers\Controller;
use App\Models\Operation;
use App\Models\ResultEmail;
use App\Services\ResultEmails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OperationEmailController extends Controller
{
    public function __construct(private ResultEmails $emails) {}

    public function create(Request $request, Operation $operation): View
    {
        abort_unless($this->emails->canRequest($request->user(), $operation), 403);

        return view('admin.operations.email', [
            'operation' => $operation,
            'summary' => $this->emails->summary($operation),
            'recipients' => $this->emails->authorizedRecipients(),
            'canAttach' => $this->emails->canAttach($request->user()),
            'history' => $operation->resultEmails()->with(['recipient', 'requester'])->latest('id')->get(),
        ]);
    }

    public function store(Request $request, Operation $operation): RedirectResponse
    {
        abort_unless($this->emails->canRequest($request->user(), $operation), 403);
        $request->validate([
            'email_destinataires' => ['required', 'array'],
            'email_destinataires.*' => ['integer', Rule::in($this->emails->authorizedRecipients()->pluck('id')->all())],
            'email_piece_jointe' => ['nullable', 'boolean'],
        ], [
            'email_destinataires.required' => __('Choisissez au moins un destinataire.'),
            'email_destinataires.*.in' => __('Ce destinataire n’est pas autorisé à recevoir les résultats de rapprochement.'),
        ]);

        $result = $this->emails->request($operation, $request->user(), $request->input('email_destinataires'), $request->boolean('email_piece_jointe'));
        $messages = [];
        if ($result['added'] !== []) {
            $messages[] = __('Envoi lancé pour : :list.', ['list' => implode(', ', $result['added'])]);
        }
        if ($result['already'] !== []) {
            $messages[] = __('Déjà envoyé ou en cours d’envoi, non renvoyé : :list.', ['list' => implode(', ', $result['already'])]);
        }

        return redirect()->route('admin.operations.email', $operation)
            ->with($result['added'] !== [] ? 'status' : 'error', implode(' ', $messages));
    }

    /** Bouton « Renvoyer » d'un envoi en échec. */
    public function retry(Request $request, ResultEmail $resultEmail): RedirectResponse
    {
        $operation = $resultEmail->operation;
        abort_unless($operation !== null && $this->emails->canRequest($request->user(), $operation), 403);

        return redirect()->route('admin.operations.email', $operation)->with(...$this->emails->retry($resultEmail)
            ? ['status', __('Nouvel envoi lancé pour :email.', ['email' => $resultEmail->email])]
            : ['error', __('Cet envoi n’est pas en échec : il n’a pas été relancé.')]);
    }
}
