<?php

declare(strict_types=1);

namespace SysRevAI\Core;

/**
 * Catalogue of platform tools.
 *
 * SysRevAI is shifting from a single-purpose systematic-review app into a
 * hub of research tools — Reviews becomes one entry alongside Search,
 * Citations, the future Citation-verification module, scoping reviews,
 * etc. Each tool is described as a small associative array so adding a
 * new module is just registering it here; the /tools hub view iterates
 * the list to render its tiles.
 *
 * Keys per entry:
 *   id          stable identifier (route slug)
 *   icon        glyph name in views/partials/icon.php
 *   labelKey    i18n key for the tile title
 *   blurbKey    i18n key for the tile description
 *   route       in-app URL to land the user on
 *   status      'available' | 'coming_soon'  (coming-soon tiles render
 *               muted and aren't clickable)
 *   admin       true if the tile is admin-only
 */
final class ToolRegistry
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return [
            [
                'id'       => 'reviews',
                'icon'     => 'reviews',
                'labelKey' => 'tools.reviews_title',
                'blurbKey' => 'tools.reviews_blurb',
                'route'    => '/reviews',
                'status'   => 'available',
                'admin'    => false,
            ],
            [
                'id'       => 'search',
                'icon'     => 'search',
                'labelKey' => 'tools.search_title',
                'blurbKey' => 'tools.search_blurb',
                'route'    => '/search',
                'status'   => 'available',
                'admin'    => false,
            ],
            [
                'id'       => 'citations',
                'icon'     => 'references',
                'labelKey' => 'tools.citations_title',
                'blurbKey' => 'tools.citations_blurb',
                'route'    => '/citations',
                'status'   => 'available',
                'admin'    => false,
            ],
            // Placeholder tiles for the modules in the roadmap. They render
            // muted with a "Coming soon" tag so researchers see where the
            // platform is heading without being able to click through to a
            // 404. Each one becomes a real entry as the PR landing it ships.
            [
                'id'       => 'scoping_reviews',
                'icon'     => 'protocol',
                'labelKey' => 'tools.scoping_title',
                'blurbKey' => 'tools.scoping_blurb',
                'route'    => '/reviews/new?kind=scoping',
                'status'   => 'available',
                'admin'    => false,
            ],
            [
                'id'       => 'citation_verification',
                'icon'     => 'check',
                'labelKey' => 'tools.cite_verify_title',
                'blurbKey' => 'tools.cite_verify_blurb',
                'route'    => '/tools/verify-citations',
                'status'   => 'available',
                'admin'    => false,
            ],
            [
                'id'       => 'articles',
                'icon'     => 'abstract',
                'labelKey' => 'tools.articles_title',
                'blurbKey' => 'tools.articles_blurb',
                'route'    => '/tools/articles',
                'status'   => 'available',
                'admin'    => false,
            ],
        ];
    }

    /** Tools the given user can see (handles admin gating). */
    public static function forUser(?array $user): array
    {
        $isAdmin = $user !== null && in_array(($user['role'] ?? ''), ['owner', 'admin'], true);
        return array_values(array_filter(
            self::all(),
            static fn (array $t): bool => !($t['admin'] ?? false) || $isAdmin
        ));
    }
}
