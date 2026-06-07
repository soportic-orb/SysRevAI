<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\ToolRegistry;
use SysRevAI\Core\View;

/**
 * Tools hub. Single entry-point listing every research module the
 * platform offers — Reviews, Search, Citations and the modules on the
 * roadmap. Replaces the implicit "Dashboard is also where you launch
 * tools" model so adding a new tool is a registry edit, not a nav
 * surgery.
 */
final class ToolsController
{
    public function index(): void
    {
        $user = Auth::user();
        echo View::render('tools/index', [
            'tools' => ToolRegistry::forUser($user),
        ]);
    }
}
