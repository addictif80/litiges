<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index(): void
    {
        $userId = (int) Auth::id();
        $model  = new Notification();

        $this->view('notifications/index', [
            'titre'         => 'Notifications',
            'notifications' => $model->forUser($userId, false, 100),
        ]);
    }

    public function markAll(): void
    {
        (new Notification())->marquerLues((int) Auth::id());
        flash('success', 'Toutes les notifications ont ete marquees comme lues.');
        $this->back('/notifications');
    }

    public function mark(string $id): void
    {
        (new Notification())->marquerLue((int) $id, (int) Auth::id());
        $this->back('/notifications');
    }
}
