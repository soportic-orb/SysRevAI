<?php

declare(strict_types=1);

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;

/** @var array $review */
/** @var array $pico */
/** @var array $metrics */
/** @var array $members */
/** @var array $reasons */
$id = (int) $review['id'];
$isOwner = (int) $review['owner_id'] === (int) Auth::id();
$metricKeys = ['imported', 'duplicate', 'duplicates_removed', 'ta_screening', 'ta_included', 'ft_screening', 'ft_included', 'ft_excluded', 'extracted'];

/** Tiny inline-SVG library used by the protocol cards. Each icon is 18×18 px
 *  and inherits the surrounding text colour via currentColor.                */
$icon = static function (string $name): string {
    $svgs = [
        'question'   => '<circle cx="12" cy="12" r="10"></circle><path d="M9.5 9a2.5 2.5 0 1 1 4.5 1.5c-1 .5-2 1-2 2.5"></path><circle cx="12" cy="17" r=".5" fill="currentColor"></circle>',
        'population' => '<circle cx="9" cy="8" r="3"></circle><circle cx="17" cy="9" r="2.5"></circle><path d="M3 19a6 6 0 0 1 12 0"></path><path d="M14 19a4 4 0 0 1 7 0"></path>',
        'intervention' => '<rect x="3" y="9" width="11" height="6" rx="3" transform="rotate(-30 8.5 12)"></rect><line x1="9" y1="9" x2="14" y2="14"></line>',
        'comparison' => '<line x1="12" y1="3" x2="12" y2="21"></line><polyline points="5 8 12 5 19 8"></polyline><path d="M3 14c0 1.6 1.6 3 3.5 3S10 15.6 10 14L6.5 8 3 14z"></path><path d="M14 14c0 1.6 1.6 3 3.5 3S21 15.6 21 14l-3.5-6L14 14z"></path>',
        'outcome'    => '<circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="5"></circle><circle cx="12" cy="12" r="1.5" fill="currentColor"></circle>',
        'design'     => '<path d="M7 21V9"></path><path d="M12 21V5"></path><path d="M17 21v-9"></path><line x1="3" y1="21" x2="21" y2="21"></line>',
        'check'      => '<polyline points="4 12 10 18 20 6"></polyline>',
        'x'          => '<line x1="6" y1="6" x2="18" y2="18"></line><line x1="6" y1="18" x2="18" y2="6"></line>',
        'team'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.9"></path><path d="M16 3.1A4 4 0 0 1 16 11"></path>',
        'ban'        => '<circle cx="12" cy="12" r="9"></circle><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"></line>',
        'chat'       => '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9 8.5 8.5 0 0 1 8.5 8.5z"></path>',
        'chevron'    => '<polyline points="6 9 12 15 18 9"></polyline>',
    ];
    $body = $svgs[$name] ?? '';
    return '<svg class="icon icon--' . $name . '" viewBox="0 0 24 24" width="18" height="18" fill="none"'
        . ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $body . '</svg>';
};

/**
 * Protocol fields in order — only render the ones that have content.
 * Picks the framework's field list based on the review's kind so a
 * scoping review renders the PCC (Population, Concept, Context)
 * triplet instead of PICO. The icon column intentionally has no
 * "context"-specific glyph — we fall back to the existing 'design'
 * icon, which reads as "broader frame" well enough.
 */
$picoFieldsByKind = [
    'systematic' => [
        'population'   => 'population',
        'intervention' => 'intervention',
        'comparison'   => 'comparison',
        'outcome'      => 'outcome',
        'study_design' => 'design',
    ],
    'scoping' => [
        'population' => 'population',
        'concept'    => 'intervention',
        'context'    => 'design',
    ],
];
$picoFields = $picoFieldsByKind[\SysRevAI\Models\Review::kind($review)] ?? $picoFieldsByKind['systematic'];
?>
<div class="page">
    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <div class="page__head">
        <span class="tag tag--soft"><?= e(__('reviews.mode_' . $review['screening_mode'])) ?></span>
    </div>

    <div class="metrics">
        <?php foreach ($metricKeys as $key): ?>
            <div class="metric">
                <span class="metric__value"><?= (int) ($metrics[$key] ?? 0) ?></span>
                <span class="metric__label"><?= e(__('reviews.metric_' . $key)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid-2">

        <!-- ── Protocol (collapsed by default) ─────────────────────────── -->
        <section class="section-card collapse-card"
                 data-collapsible data-collapsed-default>
            <button type="button" class="collapse-card__head"
                    data-collapsible-toggle aria-controls="protocolBody" aria-expanded="false">
                <span class="collapse-card__title"><?= e(__('reviews.protocol')) ?></span>
                <?= $icon('chevron') ?>
            </button>
            <div class="collapse-card__body protocol-body" id="protocolBody" data-collapsible-body hidden>

                <?php if (!empty($review['question'])): ?>
                    <div class="protocol-item protocol-item--wide">
                        <span class="protocol-item__icon"><?= $icon('question') ?></span>
                        <div>
                            <div class="protocol-item__label"><?= e(__('reviews.question')) ?></div>
                            <div class="protocol-item__value"><?= nl2br(e((string) $review['question'])) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="protocol-grid">
                    <?php foreach ($picoFields as $f => $iconName): ?>
                        <?php if (($pico[$f] ?? '') !== ''): ?>
                            <div class="protocol-item">
                                <span class="protocol-item__icon"><?= $icon($iconName) ?></span>
                                <div>
                                    <div class="protocol-item__label"><?= e(__('reviews.pico_' . $f)) ?></div>
                                    <div class="protocol-item__value"><?= e((string) $pico[$f]) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($review['inclusion_criteria'])): ?>
                    <div class="protocol-item protocol-item--wide protocol-item--include">
                        <span class="protocol-item__icon"><?= $icon('check') ?></span>
                        <div>
                            <div class="protocol-item__label"><?= e(__('reviews.inclusion')) ?></div>
                            <div class="protocol-item__value"><?= nl2br(e((string) $review['inclusion_criteria'])) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($review['exclusion_criteria'])): ?>
                    <div class="protocol-item protocol-item--wide protocol-item--exclude">
                        <span class="protocol-item__icon"><?= $icon('x') ?></span>
                        <div>
                            <div class="protocol-item__label"><?= e(__('reviews.exclusion')) ?></div>
                            <div class="protocol-item__value"><?= nl2br(e((string) $review['exclusion_criteria'])) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ── Team (collapsed by default) ─────────────────────────────── -->
        <section class="section-card collapse-card"
                 data-collapsible data-collapsed-default>
            <button type="button" class="collapse-card__head"
                    data-collapsible-toggle aria-controls="teamBody" aria-expanded="false">
                <span class="collapse-card__title"><?= e(__('reviews.team')) ?></span>
                <?= $icon('chevron') ?>
            </button>
            <div class="collapse-card__body" id="teamBody" data-collapsible-body hidden>
                <div class="team-block">
                    <div class="team-block__head">
                        <span class="protocol-item__icon"><?= $icon('team') ?></span>
                        <h3 class="team-block__title"><?= e(__('reviews.team')) ?></h3>
                    </div>
                    <ul class="member-list">
                        <?php foreach ($members as $m): ?>
                            <li>
                                <span><?= e((string) $m['name']) ?> <span class="muted"><?= e((string) $m['email']) ?></span></span>
                                <span class="tag tag--soft"><?= e((string) $m['role']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="field-help"><?= e(__('reviews.team_note')) ?></p>
                </div>

                <div class="team-block">
                    <div class="team-block__head">
                        <span class="protocol-item__icon"><?= $icon('ban') ?></span>
                        <h3 class="team-block__title"><?= e(__('reviews.exclusion_reasons')) ?></h3>
                    </div>
                    <?php if ($reasons === []): ?>
                        <p class="muted"><?= e(__('reviews.no_reasons')) ?></p>
                    <?php else: ?>
                        <ul class="reason-list">
                            <?php foreach ($reasons as $r): ?>
                                <li><?= e((string) $r['label']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    </div>

    <!-- ── Discussion / comments (always visible) ───────────────────────── -->
    <div class="section-card discussion-card" id="comments">
        <h2 class="section__subtitle discussion-card__title">
            <span class="protocol-item__icon"><?= $icon('chat') ?></span>
            <?= e(__('comments.title')) ?>
        </h2>

        <?php if (($comments ?? []) === []): ?>
            <p class="muted"><?= e(__('comments.none')) ?></p>
        <?php else: ?>
            <ul class="comment-list">
                <?php foreach ($comments as $c): ?>
                    <li class="comment">
                        <div class="comment__head">
                            <?php
                                $avatarUser = [
                                    'name'        => $c['author_name'] ?? '',
                                    'email'       => $c['author_email'] ?? '',
                                    'avatar_path' => $c['author_avatar'] ?? null,
                                ];
                                $avatarSize = 30;
                                require config('paths.base') . '/views/partials/avatar.php';
                            ?>
                            <strong class="comment__author"><?= e((string) $c['author_name']) ?></strong>
                            <span class="muted"><?= e((string) $c['created_at']) ?></span>
                            <?php if ((int) $c['user_id'] === (int) Auth::id() || $isOwner): ?>
                                <form method="post" action="/reviews/<?= $id ?>/comments/delete" class="comment__del">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="comment_id" value="<?= (int) $c['id'] ?>">
                                    <button class="link-del" title="<?= e(__('comments.delete')) ?>">×</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="comment__body"><?= nl2br(e((string) $c['content'])) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/reviews/<?= $id ?>/comments" class="comment-form">
            <?= csrf_field() ?>
            <textarea class="input" name="content" rows="3" placeholder="<?= e(__('comments.placeholder')) ?>" required></textarea>
            <div style="margin-top:8px"><button class="btn btn--primary"><?= e(__('comments.post')) ?></button></div>
        </form>
    </div>
</div>
