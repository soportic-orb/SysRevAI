<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Article;
use SysRevAI\Models\ArticleCriticalReport;
use SysRevAI\Services\ArticleHtmlImporter;
use SysRevAI\Services\ClaudeService;
use SysRevAI\Services\FileStorage;

/**
 * Collaborative editor — rich-text workspace for an article with
 * Copilot-driven, Accept / Reject edit suggestions.
 *
 * Endpoints (all require auth + article access):
 *   GET  /tools/articles/{id}/edit              show()
 *   POST /tools/articles/{id}/edit/save         save()    autosave HTML
 *   POST /tools/articles/{id}/edit/image        uploadImage()
 *   GET  /tools/articles/{id}/edit/image/{name} serveImage()
 *   POST /tools/articles/{id}/edit/copilot      copilot()  selection-aware edit
 */
final class ArticleEditorController
{
    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public function show(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        $uid = (int) Auth::id();
        $aid = (int) $article['id'];

        $initialHtml = (string) ($article['editor_html'] ?? '');
        if ($initialHtml === '') {
            $initialHtml = ArticleHtmlImporter::fromArticle($article);
        }

        echo View::render('articles/edit', [
            'article'     => $article,
            'isOwner'     => Article::isOwner($article, $uid),
            'initialHtml' => $initialHtml,
            'hasReport'   => ArticleCriticalReport::find($aid) !== null,
        ]);
    }

    public function save(string $id): void
    {
        header('Content-Type: application/json');
        $article = $this->loadOrDeny((int) $id);
        $payload = $this->jsonBody();
        $html = (string) ($payload['html'] ?? '');
        // Cap at 4 MB so a runaway autosave can't OOM the worker; the
        // editor itself rejects larger documents on the client too.
        if (strlen($html) > 4 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'too_large']);
            return;
        }
        Article::saveEditorHtml((int) $article['id'], $html);
        ActivityLog::record('articles.editor_saved', ['article_id' => (int) $article['id']]);
        echo json_encode(['ok' => true, 'saved_at' => gmdate('c')]);
    }

    public function uploadImage(string $id): void
    {
        header('Content-Type: application/json');
        $article = $this->loadOrDeny((int) $id);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            echo json_encode(['ok' => false, 'error' => 'no_file']);
            return;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            echo json_encode(['ok' => false, 'error' => 'invalid_upload']);
            return;
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
            echo json_encode(['ok' => false, 'error' => 'too_large']);
            return;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        if (!isset(self::ALLOWED_IMAGE_MIMES[$mime])) {
            echo json_encode(['ok' => false, 'error' => 'bad_format']);
            return;
        }
        $ext = self::ALLOWED_IMAGE_MIMES[$mime];
        $bytes = (string) file_get_contents((string) $file['tmp_name']);
        // FileStorage::storeBytes flattens its subdir argument (no '/'
        // allowed), so we put every editor image in one shared bucket
        // and prefix the article id into the filename. The serve / delete
        // path then enforces that the basename starts with "{article}_".
        $path = FileStorage::storeBytes($bytes, $ext, 'article_images');
        if ($path === null) {
            echo json_encode(['ok' => false, 'error' => 'store_failed']);
            return;
        }
        $aid = (int) $article['id'];
        $prefixedName = $aid . '_' . basename($path);
        $prefixedPath = dirname($path) . '/' . $prefixedName;
        if (!@rename($path, $prefixedPath)) {
            @unlink($path);
            echo json_encode(['ok' => false, 'error' => 'store_failed']);
            return;
        }
        ActivityLog::record('articles.editor_image_uploaded', ['article_id' => $aid, 'name' => $prefixedName]);
        echo json_encode([
            'ok'       => true,
            'location' => '/tools/articles/' . $aid . '/edit/image/' . $prefixedName,
        ]);
    }

    public function serveImage(string $id, string $name): void
    {
        $article = $this->loadOrDeny((int) $id);
        $aid = (int) $article['id'];
        // Defend against path traversal: only allow plain basenames and
        // require the article-id prefix so a user can't request another
        // article's image by guessing UUIDs.
        if (preg_match('/^' . $aid . '_[a-zA-Z0-9_\-]+\.[a-zA-Z0-9]{1,5}$/', $name) !== 1) {
            http_response_code(404);
            return;
        }
        $path = (string) config('paths.storage') . '/article_images/' . $name;
        if (!is_file($path) || !FileStorage::isStoredIn($path, 'article_images')) {
            http_response_code(404);
            return;
        }
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    public function copilot(string $id): void
    {
        header('Content-Type: application/json');
        $article = $this->loadOrDeny((int) $id);
        $payload  = $this->jsonBody();
        $prompt   = trim((string) ($payload['prompt'] ?? ''));
        $fullHtml = (string) ($payload['full_html']      ?? '');
        $selHtml  = (string) ($payload['selection_html'] ?? '');
        if ($prompt === '') {
            echo json_encode(['ok' => false, 'error' => 'empty_prompt']);
            return;
        }

        $reportRow = ArticleCriticalReport::find((int) $article['id']);
        $report = $reportRow !== null ? ArticleCriticalReport::decode($reportRow) : null;

        @set_time_limit(220);
        $result = ClaudeService::fromSettings()->articleEditorEdit($article, $fullHtml, $selHtml, $prompt, $report);
        if (!($result['ok'] ?? false) || !isset($result['data'])) {
            ActivityLog::record('articles.editor_copilot_failed', [
                'article_id' => (int) $article['id'],
                'error'      => (string) ($result['error'] ?? 'unknown'),
            ]);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'unknown']);
            return;
        }
        ActivityLog::record('articles.editor_copilot_proposed', [
            'article_id' => (int) $article['id'],
            'action'     => $result['data']['action'],
            'scope'      => $result['data']['scope'],
        ]);
        echo json_encode(['ok' => true, 'data' => $result['data']]);
    }

    /** @return array<string,mixed> */
    private function jsonBody(): array
    {
        $raw = (string) file_get_contents('php://input');
        $body = json_decode($raw, true);
        return is_array($body) ? $body : [];
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
}
