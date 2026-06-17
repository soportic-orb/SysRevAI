<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;
use SysRevAI\Models\Article;
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
        try {
            // Article::forUser returns owner + collaborator rows
            // ordered by updated_at DESC. We surface the 6 most recent
            // on the dashboard so the section never grows past the
            // fold; the "View all" link goes to /tools/articles.
            $articles = array_slice(Article::forUser($uid), 0, 6);
        } catch (\Throwable) {
            $articles = [];
        }

        echo View::render('dashboard/index', [
            'user'     => Auth::user(),
            'reviews'  => $reviews,
            'articles' => $articles,
        ]);
    }
}
