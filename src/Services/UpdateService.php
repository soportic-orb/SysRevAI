<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Database;
use SysRevAI\Models\SystemUpdate;

/**
 * Over-the-air updates from the canonical GitHub repository.
 *
 * `check()` queries the GitHub commits API for the tip of the default branch
 * (no auth — public repo). `apply()` runs `git fetch` + `git reset --hard
 * origin/<branch>` inside the project root, then replays every unseen
 * migration so the DB schema catches up with the new code.
 *
 * The installation must therefore be a git checkout. When it isn't (e.g. a
 * tarball deploy), `apply()` reports the situation cleanly instead of
 * mutating files.
 */
final class UpdateService
{
    public const REPO   = 'soportic-orb/sysrevai';
    public const BRANCH = 'main';
    private const TIMEOUT = 15;

    /**
     * Compare the local HEAD against the remote tip.
     *
     * @return array{
     *   ok:bool,
     *   error?:string,
     *   local?:?string,
     *   remote?:string,
     *   behind?:int,
     *   ahead?:int,
     *   up_to_date?:bool,
     *   short?:string,
     *   commit_url?:string,
     *   message?:string,
     *   author?:string,
     *   committed_at?:string,
     *   is_git?:bool,
     * }
     */
    public function check(): array
    {
        $remote = $this->fetchRemoteCommit();
        if (!$remote['ok']) {
            return $remote;
        }
        $local = $this->localCommit();
        $isGit = $local !== null;

        $behind = 0;
        if ($isGit && $local !== $remote['sha']) {
            $behind = $this->countCommitsBehind($remote['sha']);
        }

        return [
            'ok'           => true,
            'is_git'       => $isGit,
            'local'        => $local,
            'remote'       => $remote['sha'],
            'short'        => substr($remote['sha'], 0, 7),
            'behind'       => $behind,
            'up_to_date'   => $isGit && $local === $remote['sha'],
            'commit_url'   => 'https://github.com/' . self::REPO . '/commit/' . $remote['sha'],
            'message'      => $remote['message'],
            'author'       => $remote['author'],
            'committed_at' => $remote['date'],
        ];
    }

    /**
     * Pull the latest code on the configured branch and apply any new
     * migrations. The caller (controller) gates this behind admin auth.
     *
     * @return array{ok:bool,from?:?string,to?:?string,migrations_run?:int,error?:string,output?:string}
     */
    public function apply(): array
    {
        $base = (string) config('paths.base');
        if (!is_dir($base . '/.git')) {
            return ['ok' => false, 'error' => 'not_a_git_checkout'];
        }

        $remote = $this->fetchRemoteCommit();
        if (!$remote['ok']) {
            return ['ok' => false, 'error' => $remote['error'] ?? 'remote_lookup_failed'];
        }

        $from = $this->localCommit();
        $target = $remote['sha'];

        if ($from === $target) {
            return ['ok' => true, 'from' => $from, 'to' => $target, 'migrations_run' => 0];
        }

        $cmd = sprintf(
            'cd %s && git fetch --depth=1 origin %s 2>&1 && git reset --hard FETCH_HEAD 2>&1',
            escapeshellarg($base),
            escapeshellarg(self::BRANCH)
        );
        $output = [];
        $code = 0;
        @exec($cmd, $output, $code);
        if ($code !== 0) {
            $err = trim(implode("\n", $output));
            SystemUpdate::record($from, $target, Auth::id(), 'failed', 0, 'git: ' . $err);
            return ['ok' => false, 'error' => 'git_failed', 'output' => $err];
        }

        $migResult = $this->runPendingMigrations();
        if (!$migResult['ok']) {
            SystemUpdate::record($from, $target, Auth::id(), 'failed', $migResult['ran'], $migResult['error']);
            return [
                'ok'    => false,
                'error' => 'migration_failed',
                'from'  => $from,
                'to'    => $target,
                'output' => $migResult['error'],
                'migrations_run' => $migResult['ran'],
            ];
        }

        SystemUpdate::record($from, $target, Auth::id(), 'success', $migResult['ran'], null);
        return [
            'ok'             => true,
            'from'           => $from,
            'to'             => $target,
            'migrations_run' => $migResult['ran'],
        ];
    }

    /* ── Internals ─────────────────────────────────────────────────────── */

    /**
     * @return array{ok:bool,sha?:string,message?:string,author?:string,date?:string,error?:string}
     */
    private function fetchRemoteCommit(): array
    {
        $url = 'https://api.github.com/repos/' . self::REPO . '/commits/' . self::BRANCH;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: SysRevAI-Updater',
                'Accept: application/vnd.github+json',
            ],
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($err !== null) {
            return ['ok' => false, 'error' => 'network: ' . $err];
        }
        if ($status !== 200 || !is_string($raw)) {
            return ['ok' => false, 'error' => 'github_http_' . $status];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['sha'])) {
            return ['ok' => false, 'error' => 'unexpected_response'];
        }
        return [
            'ok'      => true,
            'sha'     => (string) $data['sha'],
            'message' => (string) ($data['commit']['message'] ?? ''),
            'author'  => (string) ($data['commit']['author']['name'] ?? ''),
            'date'    => (string) ($data['commit']['author']['date'] ?? ''),
        ];
    }

    private function localCommit(): ?string
    {
        $base = (string) config('paths.base');
        if (!is_dir($base . '/.git')) {
            return null;
        }
        $head = @file_get_contents($base . '/.git/HEAD');
        if (!is_string($head)) {
            return null;
        }
        $head = trim($head);
        if (str_starts_with($head, 'ref: ')) {
            $ref = substr($head, 5);
            $sha = @file_get_contents($base . '/.git/' . $ref);
            if (is_string($sha)) {
                return trim($sha) ?: null;
            }
            // Packed refs fallback.
            $packed = @file_get_contents($base . '/.git/packed-refs');
            if (is_string($packed) && preg_match('/^([0-9a-f]{40}) ' . preg_quote($ref, '/') . '$/m', $packed, $m)) {
                return $m[1];
            }
            return null;
        }
        return ctype_xdigit($head) ? $head : null;
    }

    /** Best-effort commit count between local HEAD and the remote tip. */
    private function countCommitsBehind(string $remote): int
    {
        $base = (string) config('paths.base');
        $cmd  = sprintf(
            'cd %s && git rev-list --count HEAD..%s 2>/dev/null',
            escapeshellarg($base),
            escapeshellarg($remote)
        );
        $out = (string) @shell_exec($cmd);
        return (int) trim($out);
    }

    /**
     * Apply migrations whose filename hasn't been recorded yet. Uses the same
     * tracking table the installer writes to (`schema_migrations`), creating
     * it if missing so an upgrade from an older install still works.
     *
     * @return array{ok:bool,ran:int,error?:string}
     */
    private function runPendingMigrations(): array
    {
        $dir = (string) config('paths.migrations');
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        if ($files === []) {
            return ['ok' => true, 'ran' => 0];
        }

        try {
            $pdo = Database::pdo();
            $prefix = Database::prefix();
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `{$prefix}schema_migrations` (
                    `file`       VARCHAR(190) NOT NULL PRIMARY KEY,
                    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $applied = [];
            foreach ($pdo->query("SELECT file FROM `{$prefix}schema_migrations`")->fetchAll(\PDO::FETCH_COLUMN) as $f) {
                $applied[(string) $f] = true;
            }

            $ran = 0;
            $insert = $pdo->prepare("INSERT IGNORE INTO `{$prefix}schema_migrations` (`file`) VALUES (?)");
            foreach ($files as $file) {
                $name = basename($file);
                if (isset($applied[$name])) {
                    continue;
                }
                $sql = (string) file_get_contents($file);
                $sql = str_replace('{prefix}', $prefix, $sql);
                $pdo->exec($sql);
                $insert->execute([$name]);
                $ran++;
            }
            return ['ok' => true, 'ran' => $ran];
        } catch (\Throwable $e) {
            return ['ok' => false, 'ran' => 0, 'error' => $e->getMessage()];
        }
    }
}
