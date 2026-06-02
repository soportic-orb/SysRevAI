<?php

declare(strict_types=1);

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;

/** @var array $review */
/** @var array $template */
/** @var array $rows */
/** @var array $statusMap */
$id = (int) $review['id'];
$uid = (int) Auth::id();
$isOwner = (int) $review['owner_id'] === $uid;
?>
<div class="page">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title">
                <?= e(__('extraction.title')) ?>
                <?php $phaseKey = 'extraction'; require config('paths.base') . '/views/partials/phase_info.php'; ?>
            </h1>
        </div>
        <?php if ($isOwner): ?>
            <a class="btn btn--ghost" href="/reviews/<?= $id ?>/extraction/template"><?= e(__('extraction.edit_template')) ?></a>
        <?php endif; ?>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <div class="empty-state"><p><?= e(__('extraction.none')) ?></p></div>
    <?php else: ?>
        <div class="section-card" style="padding:0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th><?= e(__('references.col_study')) ?></th>
                        <th><?= e(__('extraction.your_status')) ?></th>
                        <th><?= e(__('extraction.team_status')) ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): $rid = (int) $r['id']; $authors = json_decode((string) $r['authors_json'], true) ?: []; ?>
                        <?php $mine = $statusMap[$rid][$uid] ?? null; ?>
                        <tr>
                            <td>
                                <strong><?= e((string) ($r['title'] ?: '—')) ?></strong><br>
                                <span class="muted">
                                    <?= e(implode('; ', array_slice($authors, 0, 3))) ?><?= count($authors) > 3 ? ' et al.' : '' ?>
                                    <?php if (!empty($r['year'])): ?> · <?= (int) $r['year'] ?><?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php $s = $mine['status'] ?? null; ?>
                                <?php if ($s === null): ?>
                                    <span class="muted"><?= e(__('extraction.st_none')) ?></span>
                                <?php else: ?>
                                    <span class="tag tag--<?= e($s) ?>"><?= e(__('extraction.st_' . $s)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $teamCount = count($statusMap[$rid] ?? []); ?>
                                <span class="muted"><?= e(__('extraction.team_count', $teamCount)) ?></span>
                            </td>
                            <td><a class="btn btn--ghost btn--sm" href="/reviews/<?= $id ?>/extraction/<?= $rid ?>"><?= e(__('extraction.open')) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
