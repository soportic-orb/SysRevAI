<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\Notification;
use SysRevAI\Models\User;

final class ProfileController
{
    public function notifications(): void
    {
        $user = Auth::user();
        $prefs = json_decode((string) ($user['notification_preferences'] ?? ''), true);
        echo View::render('profile/notifications', [
            'types' => Notification::TYPES,
            'prefs' => is_array($prefs) ? $prefs : [],
        ]);
    }

    public function saveNotifications(): void
    {
        $prefs = [];
        $posted = $_POST['pref'] ?? [];
        foreach (Notification::TYPES as $type) {
            $prefs[$type] = [
                'in_app' => !empty($posted[$type]['in_app']),
                'email'  => !empty($posted[$type]['email']),
            ];
        }
        User::updateNotificationPreferences((int) Auth::id(), $prefs);
        Session::flash('success', __('profile.saved'));
        redirect('/profile/notifications');
    }
}
