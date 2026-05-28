<?php

declare(strict_types=1);

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;

/** @var array $review */
/** @var array $jobs */
/** @var array $summary */
/** @var string $pollUrl */
$id = (int) $review['id'];
$isOwner = (int) $review['owner_id'] === (int) Auth::id();
?>
<div class="page">
    <div class="page__head page__head--row">
        <div>
            <div class="breadcrumb"><a href="/reviews/<?= $id ?>/references"><?= e(__('references.title')) ?></a> /</div>
            <h1 class="page__title"><?= e(__('fulltext.queue_title')) ?></h1>
        </div>
        <?php if ($isOwner): ?>
            <form method="post" action="/reviews/<?= $id ?>/full-text-queue/cancel-all"
                  onsubmit="return confirm('<?= e(__('fulltext.confirm_cancel')) ?>')">
                <?= csrf_field() ?>
                <button class="btn btn--danger btn--sm"><?= e(__('fulltext.cancel_all')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <div class="metrics" data-queue-summary>
        <?php foreach (['pending','processing','completed','failed'] as $key): ?>
            <div class="metric">
                <span class="metric__value" data-count="<?= e($key) ?>"><?= (int) ($summary[$key] ?? 0) ?></span>
                <span class="metric__label"><?= e(__('fulltext.q_' . $key)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section-card" style="padding:0" data-queue-poll="<?= e($pollUrl) ?>">
        <?php if ($jobs === []): ?>
            <p class="section__intro" style="padding:18px"><?= e(__('fulltext.q_empty')) ?></p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th><?= e(__('references.col_study')) ?></th>
                    <th><?= e(__('fulltext.q_status')) ?></th>
                    <th><?= e(__('fulltext.q_priority')) ?></th>
                    <th><?= e(__('fulltext.q_by')) ?></th>
                    <th><?= e(__('admin.maintenance.when')) ?></th>
                </tr></thead>
                <tbody data-queue-rows>
                    <?php foreach ($jobs as $j): ?>
                        <tr>
                            <td>
                                <a href="/reviews/<?= $id ?>/references/<?= (int) $j['reference_id'] ?>/summary">
                                    <?= e(mb_strimwidth((string) $j['ref_title'], 0, 80, '…')) ?>
                                </a>
                                <?php if (!empty($j['error_message'])): ?>
                                    <br><span class="muted"><?= e((string) $j['error_message']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="tag tag--<?= e((string) $j['status']) ?>"><?= e(__('fulltext.q_' . $j['status'])) ?></span></td>
                            <td><?= (int) $j['priority'] ?></td>
                            <td class="muted"><?= e((string) ($j['requested_by_name'] ?? '—')) ?></td>
                            <td class="muted"><?= e((string) $j['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var root = document.querySelector('[data-queue-poll]');
    if (!root) return;
    var url = root.getAttribute('data-queue-poll');
    var summaryEl = document.querySelector('[data-queue-summary]');

    function statusTag(status) {
        return '<span class="tag tag--' + status + '">' + status + '</span>';
    }

    function refresh() {
        fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.ok) return;
                if (summaryEl) {
                    ['pending','processing','completed','failed'].forEach(function (k) {
                        var el = summaryEl.querySelector('[data-count="' + k + '"]');
                        if (el) el.textContent = (d.summary && d.summary[k]) || 0;
                    });
                }
            })
            .catch(function () { /* offline: ignore */ });
    }
    setInterval(refresh, 5000);
})();
</script>
