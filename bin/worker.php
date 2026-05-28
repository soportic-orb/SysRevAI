#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Full-text retrieval worker
|------------------------------------------------------------------------------
| Drains the retrieval_queue table by running the FullTextRetrievalService for
| each pending job. Designed to be called from cron once a minute:
|
|   * * * * * php /path/to/sysrevai/bin/worker.php >> /path/to/storage/logs/worker.log 2>&1
|
| A flock-based lock prevents concurrent instances. The worker exits on its own
| once the queue is empty or after MAX_DURATION seconds (so a stuck job never
| blocks the next cron tick indefinitely).
*/

require __DIR__ . '/../src/bootstrap.php';

const MAX_DURATION = 50; // seconds — keep below the 60 s cron interval.

$lockPath = (string) config('paths.storage') . '/worker.lock';
$lockFp = fopen($lockPath, 'c');
if ($lockFp === false || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "[" . date('c') . "] worker: another instance is running, skipping.\n");
    exit(0);
}

if (!(bool) (setting('fulltext.enabled') ?? false)) {
    fwrite(STDOUT, "[" . date('c') . "] worker: fulltext module disabled, skipping.\n");
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);
}

$exhaustive = (bool) (setting('fulltext.exhaustive') ?? false);
$service    = new SysRevAI\Services\FullTextRetrieval\FullTextRetrievalService();
$started    = time();
$processed  = 0;

while ((time() - $started) < MAX_DURATION) {
    $jobs = SysRevAI\Models\RetrievalQueue::pending(1);
    if ($jobs === []) {
        break;
    }
    $job = $jobs[0];
    $jobId = (int) $job['id'];
    $referenceId = (int) $job['reference_id'];

    SysRevAI\Models\RetrievalQueue::markProcessing($jobId);
    fwrite(STDOUT, "[" . date('c') . "] worker: processing job #{$jobId} (ref #{$referenceId}).\n");

    try {
        $result = $service->retrieveFor($referenceId, $exhaustive);
        if ($result['success']) {
            SysRevAI\Models\RetrievalQueue::markCompleted($jobId);
        } else {
            SysRevAI\Models\RetrievalQueue::markFailed($jobId, 'no source returned content');
        }
        $processed++;
    } catch (Throwable $e) {
        SysRevAI\Models\RetrievalQueue::markFailed($jobId, $e->getMessage());
        fwrite(STDERR, "[" . date('c') . "] worker: job #{$jobId} failed — " . $e->getMessage() . "\n");
    }
}

fwrite(STDOUT, "[" . date('c') . "] worker: drained {$processed} job(s).\n");
flock($lockFp, LOCK_UN);
fclose($lockFp);
