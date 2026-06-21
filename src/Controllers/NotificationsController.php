<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
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
            'unreadCount'   => Notification::unreadCount($uid),
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
        $uid = (int) Auth::id();
        // Capture the count BEFORE the update so we can tell the user
        // exactly how many notifications were just marked. Without this
        // the user just sees the same list back (now without the
        // unread tint) and assumes the action did nothing.
        $count = Notification::unreadCount($uid);
        Notification::markAllRead($uid);

        Session::flash(
            'success',
            $count > 0
                ? __('notifications.marked_all', $count)
                : __('notifications.nothing_to_mark')
        );

        // Preserve the active tab so the user lands on the same view
        // they pressed the button from — they expect continuity, not a
        // surprise tab-switch back to "all". The redirect is whitelisted
        // to the two known filter values to keep this open-redirect safe.
        $filter = (string) ($_POST['filter'] ?? '');
        $to = '/notifications';
        if ($filter === 'unread') {
            $to .= '?filter=unread';
        }
        redirect($to);
    }
}
