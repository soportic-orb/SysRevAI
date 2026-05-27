<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;

final class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();

        // Review metrics arrive with the reviews feature (Phase 3). For now the
        // dashboard greets the user and shows the empty-state.
        echo View::render('dashboard/index', [
            'user'    => $user,
            'metrics' => [
                'imported'     => 0,
                'duplicates'   => 0,
                'ta_screening' => 0,
                'ft_screening' => 0,
                'included'     => 0,
                'excluded'     => 0,
            ],
        ]);
    }
}
