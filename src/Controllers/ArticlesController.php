<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Article;
use SysRevAI\Models\ArticleCriticalReport;
use SysRevAI\Models\ArticleUser;
use SysRevAI\Models\CopilotMessage;
use SysRevAI\Services\DocumentTextExtractor;
use SysRevAI\Services\FileStorage;

/**
 * Article analysis tool — list, create, show (workspace) and delete.
 * Chat lives in ArticleChatController, team in ArticleMembersController.
 */
final class ArticlesController
{
    private const MAX_BYTES = 50 * 1024 * 1024; // 50 MB
    private const ALLOWED_MIMES = [
        'application/pdf'                                                            => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'    => 'docx',
        'application/msword'                                                         => 'doc',
    ];

    public function index(): void
    {
        $uid = (int) Auth::id();
        echo View::render('articles/index', [
            'articles' => Article::forUser($uid),
        ]);
    }

    public function newForm(): void
    {
        echo View::render('articles/new');
    }

    public function store(): void
    {
        $uid = (int) Auth::id();
        $title = trim((string) ($_POST['title'] ?? ''));
        $file  = $_FILES['document'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            Session::flash('error', __('articles.no_file'));
            redirect('/tools/articles/new');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            Session::flash('error', __('articles.invalid_upload'));
            redirect('/tools/articles/new');
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            Session::flash('error', __('articles.too_large'));
            redirect('/tools/articles/new');
        }

        // Detect real MIME via finfo — don't trust the browser.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file((string) $file['tmp_name']);
        if (!isset(self::ALLOWED_MIMES[$mime])) {
            Session::flash('error', __('articles.bad_format'));
            redirect('/tools/articles/new');
        }
        $ext = self::ALLOWED_MIMES[$mime];

        // Persist bytes outside the document root.
        $bytes = (string) file_get_contents((string) $file['tmp_name']);
        $path  = FileStorage::storeBytes($bytes, $ext, 'articles');
        if ($path === null) {
            Session::flash('error', __('articles.store_failed'));
            redirect('/tools/articles/new');
        }

        // Extract text — uses pdftotext / docx2txt under the hood.
        @set_time_limit(180);
        $text = DocumentTextExtractor::extract((string) $path, $mime);
        if (trim($text) === '') {
            // Keep the upload anyway — the user can still chat, just
            // without grounding; surface a warning.
            Session::flash('warning', __('articles.no_text_extracted'));
        }

        if ($title === '') {
            $title = self::deriveTitle((string) ($file['name'] ?? 'Article'), $text);
        }
        $title = mb_substr($title, 0, 500);

        $id = Article::create($uid, [
            'title'           => $title,
            'source_filename' => mb_substr((string) ($file['name'] ?? ''), 0, 500),
            'mime'            => $mime,
            'file_path'       => $path,
            'extracted_text'  => $text,
            'char_count'      => mb_strlen($text),
        ]);

        ActivityLog::record('articles.created', ['article_id' => $id, 'char_count' => mb_strlen($text)]);
        Session::flash('success', __('articles.created'));
        redirect('/tools/articles/' . $id);
    }

    public function show(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        $uid = (int) Auth::id();
        $aid = (int) $article['id'];

        // Bootstrap hook: when the user clicks "Trabajar el análisis con
        // Copilot" on the critical-report page, we land here with a
        // ?from_report=1 query. If a report exists, seed a one-off
        // assistant message inviting the user to walk through it. The
        // follow-up turns will then carry the report inside Claude's
        // system prompt (see ClaudeService::articleChat), so any "sí"
        // from the user kicks off a phased plan grounded in the report.
        if (!empty($_GET['from_report'])) {
            $report = ArticleCriticalReport::find($aid);
            if ($report !== null) {
                CopilotMessage::add(
                    null,
                    $uid,
                    'assistant',
                    (string) __('articles.chat.bootstrap_question'),
                    $aid
                );
                ActivityLog::record('articles.chat_bootstrapped', ['article_id' => $aid]);
            }
            redirect('/tools/articles/' . $aid);
        }

        echo View::render('articles/show', [
            'article'   => $article,
            'isOwner'   => Article::isOwner($article, $uid),
            'members'   => ArticleUser::forArticle($aid),
            'history'   => CopilotMessage::history(null, $uid, 200, $aid),
            'documents' => \SysRevAI\Models\ArticleDocument::forArticle($aid),
        ]);
    }

    public function destroy(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        if (!Article::isOwner($article, (int) Auth::id())) {
            Session::flash('error', __('articles.delete_forbidden'));
            redirect('/tools/articles/' . (int) $id);
        }
        Article::delete((int) $id);
        ActivityLog::record('articles.deleted', ['article_id' => (int) $id]);
        Session::flash('success', __('articles.deleted'));
        redirect('/tools/articles');
    }

    public function downloadOriginal(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        $path = (string) ($article['file_path'] ?? '');
        if ($path === '' || !FileStorage::isStoredIn($path, 'articles') || !is_file($path)) {
            http_response_code(404);
            echo View::render('errors/404', [], 'layouts/auth');
            return;
        }
        $filename = (string) ($article['source_filename'] ?? 'article');
        header('Content-Type: ' . (string) ($article['mime'] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
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

    private static function deriveTitle(string $filename, string $text): string
    {
        // First non-empty line of the extracted text, capped — common
        // case where the title is the first heading.
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if (mb_strlen($line) >= 6 && mb_strlen($line) <= 200) {
                return $line;
            }
        }
        // Fall back to the filename without extension.
        return pathinfo($filename, PATHINFO_FILENAME) ?: 'Article';
    }
}
