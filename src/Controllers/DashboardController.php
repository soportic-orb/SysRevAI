<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;
use SysRevAI\Models\Review;

final class DashboardController
{
    public function index(): void
    {
        $uid = (int) Auth::id();
        try {
            $reviews = Review::forUser($uid, 'active');
        } catch (\Throwable) {
            $reviews = [];
        }

        echo View::render('dashboard/index', [
            'user'    => Auth::user(),
            'reviews' => $reviews,
        ]);
    }
}
