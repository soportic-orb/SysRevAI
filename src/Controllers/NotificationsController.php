<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;
use SysRevAI\Models\Notification;

final class NotificationsController
{
    public function index(): void
    {
        $uid = (int) Auth::id();
        $onlyUnread = ($_GET['filter'] ?? '') === 'unread';
        echo View::render('notifications/index', [
            'notifications' => Notification::forUser($uid, $onlyUnread),
            'onlyUnread'    => $onlyUnread,
        ]);
    }

    /** Lightweight JSON endpoint polled by the bell (every ~45s). */
    public function poll(): void
    {
        $uid = (int) Auth::id();
        header('Content-Type: application/json; charset=utf-8');
        $items = array_map(static fn ($n) => [
            'id'         => (int) $n['id'],
            'title'      => $n['title'],
            'message'    => $n['message'],
            'action_url' => $n['action_url'],
            'is_read'    => (int) $n['is_read'],
            'created_at' => $n['created_at'],
        ], Notification::recent($uid, 10));

        echo json_encode([
            'count' => Notification::unreadCount($uid),
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function markRead(): void
    {
        $uid = (int) Auth::id();
        $id = (int) ($_POST['id'] ?? 0);
        Notification::markRead($id, $uid);
        $to = (string) ($_POST['redirect'] ?? '/notifications');
        // Only allow internal redirects.
        redirect(str_starts_with($to, '/') ? $to : '/notifications');
    }

    public function markAllRead(): void
    {
        Notification::markAllRead((int) Auth::id());
        redirect('/notifications');
    }
}
