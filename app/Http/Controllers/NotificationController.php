<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * Notifications de l'utilisateur connecté (la « cloche » en haut de l'écran).
 *
 * Les notifications sont enregistrées dans la table `notifications` par les
 * classes de app/Notifications (ex. « Import terminé »).
 */
class NotificationController extends Controller
{
    /** Liste des notifications de l'utilisateur, 20 par page. */
    public function index(): View
    {
        return view('notifications.index', [
            'notifications' => auth()->user()->notifications()->paginate(20),
        ]);
    }

    /** Marque une notification comme lue. */
    public function markAsRead(DatabaseNotification $notification): RedirectResponse
    {
        // Sécurité : on ne peut marquer que SES propres notifications (sinon erreur 403 « interdit »).
        abort_unless($notification->notifiable_id === auth()->id() && $notification->notifiable_type === auth()->user()::class, 403);

        $notification->markAsRead();

        // back() = retour à la page précédente.
        return back();
    }

    /** Marque toutes les notifications non lues comme lues. */
    public function markAllAsRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back();
    }
}
