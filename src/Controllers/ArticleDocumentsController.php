<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Article;
use SysRevAI\Models\ArticleDocument;
use SysRevAI\Services\DocumentTextExtractor;
use SysRevAI\Services\FileStorage;

/**
 * Secondary documents attached to an article. Endpoints:
 *
 *   POST /tools/articles/{id}/documents                upload (multipart `file`)
 *   GET  /tools/articles/{id}/documents/{docId}/download
 *   POST /tools/articles/{id}/documents/{docId}/delete
 *
 * Members of the article can upload / download / delete. The Copilot
 * surfaces these documents to ClaudeService::articleChat as additional
 * grounded context.
 */
final class ArticleDocumentsController
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'docx'];
    private const MAX_BYTES = 30 * 1024 * 1024; // 30 MB

    public function upload(string $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $article = $this->loadOrDeny((int) $id);
        $aid = (int) $article['id'];

        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            echo json_encode(['ok' => false, 'error' => 'no_file']);
            return;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            echo json_encode(['ok' => false, 'error' => 'invalid_upload']);
            return;
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            echo json_encode(['ok' => false, 'error' => 'too_large']);
            return;
        }
        $name = (string) ($file['name'] ?? '');
        $ext  = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            echo json_encode(['ok' => false, 'error' => 'unsupported_format']);
            return;
        }

        $mime = '';
        if (class_exists('finfo')) {
            try {
                $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
            } catch (\Throwable) {
                $mime = '';
            }
        }

        // Extract before storing — if the file is unreadable we don't
        // want to leave an orphan on disk. Empty text is allowed (some
        // PDFs are pure scans) so the user can still attach the file
        // for reference, but the Copilot won't see anything from it.
        $text = DocumentTextExtractor::extract((string) $file['tmp_name'], $mime);

        $bytes = (string) file_get_contents((string) $file['tmp_name']);
        $path  = FileStorage::storeBytes($bytes, $ext, 'article_documents');
        if ($path === null) {
            echo json_encode(['ok' => false, 'error' => 'store_failed']);
            return;
        }
        // Article-id prefix on the basename, same defence-in-depth
        // pattern as the editor image uploads: the download endpoint
        // refuses cross-article access via this prefix check.
        $prefixed = $aid . '_' . basename($path);
        $prefixedPath = dirname($path) . '/' . $prefixed;
        if (!@rename($path, $prefixedPath)) {
            @unlink($path);
            echo json_encode(['ok' => false, 'error' => 'store_failed']);
            return;
        }

        $docId = ArticleDocument::create($aid, $name, $mime, $prefixedPath, $text, (int) Auth::id() ?: null);
        ActivityLog::record('articles.document_uploaded', [
            'article_id'  => $aid,
            'document_id' => $docId,
            'chars'       => mb_strlen($text),
            'name'        => $name,
        ]);
        echo json_encode([
            'ok'      => true,
            'id'      => $docId,
            'name'    => $name,
            'chars'   => mb_strlen($text),
            'has_text'=> $text !== '',
        ], JSON_UNESCAPED_UNICODE);
    }

    public function download(string $id, string $docId): void
    {
        $article = $this->loadOrDeny((int) $id);
        $doc = ArticleDocument::find((int) $docId);
        if ($doc === null || (int) $doc['article_id'] !== (int) $article['id']) {
            http_response_code(404);
            return;
        }
        $path = (string) $doc['file_path'];
        if (!FileStorage::isStoredIn($path, 'article_documents') || !is_file($path)) {
            http_response_code(404);
            return;
        }
        $filename = (string) ($doc['filename'] ?? 'document');
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = str_replace(['"', "\r", "\n"], ['', '', ''], $filename);

        $mime = (string) ($doc['mime'] ?? '');
        if ($mime === '') $mime = 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0');
        readfile($path);
        ActivityLog::record('articles.document_downloaded', [
            'article_id'  => (int) $article['id'],
            'document_id' => (int) $doc['id'],
        ]);
    }

    public function delete(string $id, string $docId): void
    {
        $article = $this->loadOrDeny((int) $id);
        $doc = ArticleDocument::find((int) $docId);
        if ($doc === null || (int) $doc['article_id'] !== (int) $article['id']) {
            Session::flash('error', __('articles.document_not_found'));
            redirect('/tools/articles/' . (int) $article['id']);
        }
        $path = (string) ($doc['file_path'] ?? '');
        if ($path !== '' && FileStorage::isStoredIn($path, 'article_documents')) {
            FileStorage::delete($path);
        }
        ArticleDocument::delete((int) $doc['id']);
        ActivityLog::record('articles.document_deleted', [
            'article_id'  => (int) $article['id'],
            'document_id' => (int) $doc['id'],
        ]);
        Session::flash('success', __('articles.document_deleted'));
        redirect('/tools/articles/' . (int) $article['id']);
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
