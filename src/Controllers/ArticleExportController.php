<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Article;
use SysRevAI\Services\ArticleExportService;

/**
 * Export the critical report (and optionally the Copilot chat) of an
 * Article project as DOCX or PDF. The selector view lives at
 * /tools/articles/{id}/export; the actual file is served from
 * /tools/articles/{id}/export/{format} with a `scope` query param.
 */
final class ArticleExportController
{
    private const ALLOWED_FORMATS = ['docx', 'pdf'];

    public function index(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        echo View::render('articles/export', [
            'article'  => $article,
            'isOwner'  => Article::isOwner($article, (int) Auth::id()),
            'scopes'   => ArticleExportService::scopes(),
        ]);
    }

    public function download(string $id, string $format): void
    {
        $article = $this->loadOrDeny((int) $id);
        $aid = (int) $article['id'];
        $back = '/tools/articles/' . $aid . '/export';

        if (!in_array($format, self::ALLOWED_FORMATS, true)) {
            Session::flash('error', __('articles.export.bad_format'));
            redirect($back);
        }
        $scope = (string) ($_GET['scope'] ?? ArticleExportService::SCOPE_REPORT);
        if (!in_array($scope, ArticleExportService::scopes(), true)) {
            $scope = ArticleExportService::SCOPE_REPORT;
        }

        @set_time_limit(120);
        $result = $format === 'docx'
            ? ArticleExportService::docx($article, (int) Auth::id(), $scope)
            : ArticleExportService::pdf($article, (int) Auth::id(), $scope);

        if ($result['bytes'] === null) {
            $err = (string) $result['error'];
            ActivityLog::record('articles.export_failed', [
                'article_id' => $aid, 'format' => $format, 'scope' => $scope, 'error' => $err,
            ]);
            Session::flash('error', __('articles.export.failed_' . $err) ?: __('articles.export.failed'));
            redirect($back);
        }

        ActivityLog::record('articles.exported', [
            'article_id' => $aid, 'format' => $format, 'scope' => $scope,
        ]);

        $base = self::slugify((string) ($article['title'] ?: 'article'));
        $suffix = $scope === ArticleExportService::SCOPE_REPORT_CHAT ? '-report-chat' : '-report';
        $filename = $base . $suffix . '.' . $format;

        $ctype = $format === 'docx'
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            : 'application/pdf';

        header('Content-Type: ' . $ctype);
        header('Content-Length: ' . strlen((string) $result['bytes']));
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        echo (string) $result['bytes'];
    }

    private function loadOrDeny(int $id): array
    {
        $article = Article::find($id);
        $uid = (int) Auth::id();
        if ($article === null || !Article::userCanAccess($id, $uid)) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $article;
    }

    /** ASCII-safe filename slug, capped — Content-Disposition stays sane. */
    private static function slugify(string $s): string
    {
        $s = trim($s);
        $tr = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($tr) && $tr !== '') {
            $s = $tr;
        }
        $s = strtolower($s);
        $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = trim($s, '-');
        if ($s === '') {
            $s = 'article';
        }
        return substr($s, 0, 80);
    }
}
