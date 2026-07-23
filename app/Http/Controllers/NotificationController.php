<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        return view('notifications.index', [
            'notifications' => auth()->user()->notifications()->paginate(20),
        ]);
    }

    public function markAsRead(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === auth()->id() && $notification->notifiable_type === auth()->user()::class, 403);

        $notification->markAsRead();

        return back();
    }

    public function markAllAsRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back();
    }
}
