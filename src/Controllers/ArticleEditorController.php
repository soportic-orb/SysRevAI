<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Article;
use SysRevAI\Models\ArticleCriticalReport;
use SysRevAI\Models\ArticleEditorVersion;
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

    /**
     * POST /tools/articles/{id}/edit/versions — append a named snapshot.
     * Body: { html, label? }. Also updates editor_html so the autosave
     * baseline reflects the snapshot the user just took.
     */
    public function createVersion(string $id): void
    {
        header('Content-Type: application/json');
        $article = $this->loadOrDeny((int) $id);
        $payload = $this->jsonBody();
        $html  = (string) ($payload['html']  ?? '');
        $label = trim((string) ($payload['label'] ?? ''));
        if (strlen($html) > 4 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'too_large']);
            return;
        }
        $aid = (int) $article['id'];
        Article::saveEditorHtml($aid, $html);
        $vid = ArticleEditorVersion::create(
            $aid,
            $html,
            $label !== '' ? $label : null,
            (int) Auth::id() ?: null,
        );
        ActivityLog::record('articles.editor_version_saved', ['article_id' => $aid, 'version_id' => $vid]);
        echo json_encode([
            'ok'       => true,
            'version'  => [
                'id'            => $vid,
                'label'         => $label !== '' ? $label : null,
                'created_at'    => gmdate('c'),
                'saved_by_name' => (string) (Auth::user()['name'] ?? ''),
            ],
        ]);
    }

    /** GET /tools/articles/{id}/edit/versions — list snapshots (no html). */
    public function listVersions(string $id): void
    {
        header('Content-Type: application/json');
        $article = $this->loadOrDeny((int) $id);
        $rows = ArticleEditorVersion::listForArticle((int) $article['id'], 50);
        echo json_encode(['ok' => true, 'versions' => $rows]);
    }

    /** GET /tools/articles/{id}/edit/versions/{vid} — fetch the html. */
    public function showVersion(string $id, string $vid): void
    {
        header('Content-Type: application/json');
        $article = $this->loadOrDeny((int) $id);
        $row = ArticleEditorVersion::find((int) $vid);
        if ($row === null || (int) $row['article_id'] !== (int) $article['id']) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            return;
        }
        echo json_encode([
            'ok'   => true,
            'html' => (string) $row['html'],
            'id'   => (int) $row['id'],
        ]);
    }

    /**
     * GET /tools/articles/{id}/edit/word — stream the editor HTML as a
     * Word 2007 (.docx) download. Uses PhpWord's Html::addHtml helper
     * so headings, lists, tables and inline formatting carry over.
     */
    public function exportWord(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        $aid = (int) $article['id'];
        $html = (string) ($article['editor_html'] ?? '');
        if ($html === '') {
            $html = ArticleHtmlImporter::fromArticle($article);
        }

        @set_time_limit(120);
        if (!class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            http_response_code(500);
            echo 'PhpWord is not installed.';
            return;
        }

        // Resolve image URLs that point at our protected edit/image route
        // into the actual paths on disk so PhpWord can embed them — it
        // can't follow an authenticated URL of its own accord.
        $html = $this->resolveEditorImageUrls($html, $aid);

        // TinyMCE outputs HTML5 (void elements without self-closing,
        // bare ampersands, etc.); PhpWord's Shared\Html::addHtml does
        // a STRICT $dom->loadXML on its input and fails silently when
        // it isn't well-formed XML — that's why early callers got an
        // empty .docx. Reformat the fragment to clean XHTML using
        // DOMDocument::loadHTML (forgiving) + saveXML.
        $xhtml = $this->htmlFragmentToXhtml($html);

        $doc = new \PhpOffice\PhpWord\PhpWord();
        $section = $doc->addSection();
        $rendered = false;
        try {
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $xhtml, false, false);
            $rendered = true;
        } catch (\Throwable) {
            // Fall through to the plain-text fallback below.
        }
        if (!$rendered || $this->sectionIsEmpty($section)) {
            // Safety net so the user always gets *something* — strip
            // tags, collapse whitespace, paragraphise on blank lines.
            $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($plain === '') {
                $plain = (string) ($article['title'] ?? '');
            }
            // Rebuild the section so the (possibly half-applied) HTML
            // attempt doesn't show through.
            $doc = new \PhpOffice\PhpWord\PhpWord();
            $section = $doc->addSection();
            foreach (preg_split('/\n{2,}/', $plain) ?: [$plain] as $block) {
                $block = trim((string) $block);
                if ($block !== '') {
                    $section->addText($block);
                }
            }
        }

        $filename = $this->wordFilename($article);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'Word2007');
        $writer->save('php://output');

        ActivityLog::record('articles.editor_exported_word', ['article_id' => $aid]);
    }

    private function wordFilename(array $article): string
    {
        $base = (string) ($article['title'] ?? 'article');
        $base = preg_replace('/[^\p{L}\p{N}\-_ ]+/u', '', $base) ?? 'article';
        $base = trim($base);
        if ($base === '') {
            $base = 'article-' . (int) $article['id'];
        }
        return mb_substr($base, 0, 80) . '.docx';
    }

    /**
     * Reformat a TinyMCE HTML fragment to clean XHTML so PhpWord's
     * Shared\Html::addHtml can parse it. DOMDocument::loadHTML is
     * forgiving (closes voids, balances tags, decodes entities) and
     * saveXML produces XML that loadXML on the other side accepts.
     */
    private function htmlFragmentToXhtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        // The XML encoding declaration prefixed below is the documented
        // way to stop loadHTML from treating the bytes as Windows-1252.
        // Without it, Catalan accents end up double-encoded in the .docx.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $doc->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return '';
        }
        $out = '';
        foreach ($body->childNodes as $child) {
            $serialised = $doc->saveXML($child);
            if (is_string($serialised)) {
                $out .= $serialised;
            }
        }
        return $out;
    }

    /**
     * Rewrite <img src="/tools/articles/{id}/edit/image/{name}"> URLs to
     * absolute file-system paths so PhpWord can read the bytes off disk
     * instead of trying to fetch them through HTTP.
     */
    private function resolveEditorImageUrls(string $html, int $articleId): string
    {
        $storage = (string) config('paths.storage') . '/article_images/';
        $prefix  = '/tools/articles/' . $articleId . '/edit/image/';
        return (string) preg_replace_callback(
            '#src=(["\'])' . preg_quote($prefix, '#') . '([^"\'<>]+)\1#i',
            static function (array $m) use ($storage): string {
                $name = (string) $m[2];
                // Only resolve names that match the article-id prefix
                // contract enforced by serveImage(); otherwise leave the
                // attribute alone so PhpWord just drops the image.
                if (preg_match('/^\d+_[a-zA-Z0-9_\-]+\.[a-zA-Z0-9]{1,5}$/', $name) !== 1) {
                    return $m[0];
                }
                $abs = $storage . $name;
                if (!is_file($abs)) {
                    return $m[0];
                }
                return 'src=' . $m[1] . htmlspecialchars($abs, ENT_QUOTES) . $m[1];
            },
            $html
        );
    }

    private function sectionIsEmpty(\PhpOffice\PhpWord\Element\Section $section): bool
    {
        foreach ($section->getElements() as $el) {
            // A non-empty Text / TextRun / Image / Table / etc. counts.
            return false;
        }
        return true;
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
