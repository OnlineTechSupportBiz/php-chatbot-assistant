<?php

/**
 * Copyright (c) 2026 Online Tech Support, LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace App\Controller;

use App\Auth\Auth;
use App\Auth\Session;
use App\Http\Request;
use App\Http\Response;
use App\Model\AuditLog;
use App\Model\Chatbot;
use App\Model\Document;
use App\Model\DocumentChunk;
use App\Model\Admin;
use App\Service\ChunkingService;
use App\Service\LlamaParseClient;
use App\Service\OpenAIClient;
use App\Service\PageIndexBuilder;

/**
 * DocumentController — manages uploaded documents and the training pipeline.
 *
 * All actions require a logged-in user scoped to an admin chatbot.
 *
 * Routes:
 *   GET    /chatbots/{id}/documents          → index()
 *   GET    /chatbots/{id}/documents   → upload()
 *   POST   /chatbots/{id}/documents/store    → store()
 *   GET    /chatbots/{id}/documents/{did}    → status()
 *   POST   /chatbots/{id}/documents/{did}/train  → train()
 *   POST   /chatbots/{id}/documents/{did}/delete → delete()
 */
class DocumentController
{
    /**
     * List documents for a chatbot.
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        $chatbotId = (int) ($params['id'] ?? 0);
        $adminId  = (int) $user['admin_id'];
        $chatbot   = Chatbot::find($chatbotId);

        if (!$chatbot || !\App\Controller\ChatbotController::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1><a href="/dashboard">Back to Dashboard</a>', 404)->send();
            return;
        }

        $documents    = Document::findByChatbot($adminId, $chatbotId);
        $indexedCount = Document::countIndexedByChatbot($adminId, $chatbotId);
        $docStrategies = Document::findStrategiesByChatbot($chatbotId);

        require __DIR__ . '/../Views/documents/index.php';
    }

    /**
     * Show the upload form.
     */
    public function upload(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_documents');
        $chatbotId = (int) ($params['id'] ?? 0);
        $adminId  = (int) $user['admin_id'];
        $chatbot   = Chatbot::find($chatbotId);

        if (!$chatbot || !\App\Controller\ChatbotController::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1><a href="/dashboard">Back to Dashboard</a>', 404)->send();
            return;
        }

        $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
    }

    /**
     * Handle file upload.
     * Returns JSON for AJAX requests (used by multi-file progress-bar upload),
     * redirects for normal browser form submissions.
     */
    public function store(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_documents');
        $chatbotId = (int) ($params['id'] ?? 0);
        $adminId  = (int) $user['admin_id'];
        $chatbot   = Chatbot::find($chatbotId);

        if (!$chatbot || !\App\Controller\ChatbotController::canAccessChatbot($chatbot, $user)) {
            if ($req->isAjax()) {
                $res->json(['ok' => false, 'error' => 'Chatbot not found.'])->send();
            } else {
                $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            }
            return;
        }

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            $error = 'Invalid form token. Please try again.';
            if ($req->isAjax()) {
                $res->json(['ok' => false, 'error' => $error])->send();
            } else {
                Session::flash('error', $error);
                $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
            }
            return;
        }

        $errors = [];

        // Validate file upload
        if (!$req->hasFile('document')) {
            $errors[] = 'Please select a file to upload.';
        } else {
            $file        = $req->files['document'];
            $allowedMimes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                'text/markdown',
            ];

            $mime = $file['type'] ?? '';
            $mime = strtolower($mime);

            // Normalise common variations
            $mimeMap = [
                'application/x-pdf'                   => 'application/pdf',
                'application/pdf'                     => 'application/pdf',
                'text/plain'                          => 'text/plain',
                'text/markdown'                       => 'text/markdown',
                'application/msword'                  => 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            $normalizedMime = $mimeMap[$mime] ?? null;

            if ($normalizedMime === null) {
                $errors[] = 'Unsupported file type: ' . htmlspecialchars($mime)
                    . '. Allowed: PDF, DOC, DOCX, TXT, MD.';
            }

            // 20 MB max
            if ($file['size'] > 20 * 1024 * 1024) {
                $errors[] = 'File exceeds 20 MB maximum size.';
            }

            // Check upload error
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload failed with error code ' . $file['error'] . '.';
            }
        }

        if (!empty($errors)) {
            if ($req->isAjax()) {
                $res->json(['ok' => false, 'error' => implode(' ', $errors)])->send();
            } else {
                Session::flash('errors', $errors);
                $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
            }
            return;
        }

        $originalName = basename($file['name']);

                // ── Duplicate check: same filename already uploaded to this chatbot ──
        // A document with the same name can coexist if it was trained under a
        // different retrieval strategy (e.g. one PageIndex, one Traditional RAG).
        // Only block if the strategy matches, or if the existing doc is untrained.
        $currentStrategy = $chatbot['retrieval_strategy'] ?? 'traditional_rag';
        $existingStrategy = Document::findStrategyByOriginalName($chatbotId, $originalName);

        if ($existingStrategy !== null && $existingStrategy['strategy'] === $currentStrategy) {
            $error = 'A file named "' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8')
                   . '" has already been uploaded for ' . $currentStrategy . '.';
            if ($req->isAjax()) {
                $res->json(['ok' => false, 'error' => $error])->send();
            } else {
                Session::flash('error', $error);
                $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
            }
            return;
        }

        // Also block if an untrained document with the same name already exists
        // (no point having two untrained copies of the same file).
        $existingDoc = Document::findByOriginalName($adminId, $chatbotId, $originalName);
        if ($existingDoc !== null && $existingStrategy === null) {
            $error = 'A file named "' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8')
                   . '" has already been uploaded (not yet trained).';
            if ($req->isAjax()) {
                $res->json(['ok' => false, 'error' => $error])->send();
            } else {
                Session::flash('error', $error);
                $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
            }
            return;
        }

        // Move uploaded file to storage
        $uploadDir    = __DIR__ . '/../../../storage/uploads';
        $originalName = basename($file['name']);

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $storedName = $adminId . '_' . $chatbotId . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $destPath   = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $error = 'Failed to save uploaded file.';
            if ($req->isAjax()) {
                $res->json(['ok' => false, 'error' => $error])->send();
            } else {
                Session::flash('error', $error);
                $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
            }
            return;
        }

        // Create document record with correct schema columns
        $documentId = Document::create([
            'admin_id'     => $adminId,
            'chatbot_id'    => $chatbotId,
            'filename'      => $storedName,
            'original_name' => $originalName,
            'mime_type'     => $normalizedMime,
            'file_path'     => $destPath,
            'file_size'     => $file['size'],
            'status'        => 'uploaded',
            'uploaded_by'   => (int) $user['id'],
        ]);

        if ($documentId === null || $documentId === false) {
            @unlink($destPath);
            $error = 'Failed to create document record.';
            if ($req->isAjax()) {
                $res->json(['ok' => false, 'error' => $error])->send();
            } else {
                Session::flash('error', $error);
                $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
            }
            return;
        }

        AuditLog::log(
            $adminId,
            (int) $user['id'],
            'create_document',
            'document',
            $documentId,
            null,
            ['name' => $originalName, 'chatbot_id' => $chatbotId, 'size' => $file['size']]
        );

        if ($req->isAjax()) {
            $res->json([
                'ok'      => true,
                'id'      => $documentId,
                'name'    => $originalName,
                'size'    => $file['size'],
                'mime'    => $normalizedMime,
                'message' => 'File uploaded successfully.',
            ])->send();
        } else {
            Session::flash('success', 'File uploaded successfully. You can now train it.');
            $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
        }
    }

    /**
     * Show document training status.
     */
    public function status(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        $chatbotId  = (int) ($params['id'] ?? 0);
        $documentId = (int) ($params['did'] ?? 0);
        $adminId   = (int) $user['admin_id'];

        $chatbot = Chatbot::find($chatbotId);
        if (!$chatbot || !\App\Controller\ChatbotController::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $document = Document::find($documentId);
        if (!$document || (int) $document['admin_id'] !== $adminId || (int) $document['chatbot_id'] !== $chatbotId) {
            $res->setStatus(404)->html('<h1>Document not found.</h1>', 404)->send();
            return;
        }

        $chunkCount = DocumentChunk::countByDocument($documentId);

        require __DIR__ . '/../Views/documents/status.php';
    }

    /**
     * Train a document: parse → chunk → embed → index.
     */
    /**
     * Trigger parsing, chunking, and embedding for a document.
     * Returns JSON for AJAX requests (used by auto-train after upload),
     * redirects with flash for normal form submissions.
     */
    public function train(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requirePermission($user, 'manage_documents');
        $chatbotId  = (int) ($params['id'] ?? 0);
        $documentId = (int) ($params['did'] ?? 0);
        $adminId   = (int) $user['admin_id'];
        $isAjax     = $req->isAjax();

        // Helper: send JSON error or redirect
        $errorReturn = function (string $msg) use ($res, $chatbotId, $isAjax): never {
            if ($isAjax) {
                $res->json(['ok' => false, 'error' => $msg])->send();
            } else {
                Session::flash('error', $msg);
                $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
            }
            exit;
        };

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            $errorReturn('Invalid form token. Please try again.');
        }

        $chatbot = Chatbot::find($chatbotId);
        if (!$chatbot || !\App\Controller\ChatbotController::canAccessChatbot($chatbot, $user)) {
            if ($isAjax) {
                $res->json(['ok' => false, 'error' => 'Chatbot not found.'])->send();
            } else {
                $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            }
            return;
        }

        $document = Document::find($documentId);
        if (!$document || (int) $document['admin_id'] !== $adminId || (int) $document['chatbot_id'] !== $chatbotId) {
            if ($isAjax) {
                $res->json(['ok' => false, 'error' => 'Document not found.'])->send();
            } else {
                $res->setStatus(404)->html('<h1>Document not found.</h1>', 404)->send();
            }
            return;
        }

        // Check the user's API keys
        $apiKeys = Admin::getApiKeys((int) $user['id']);
        $llamaKey   = $apiKeys['llamacloud_api_key'] ?? '';
        $openAiKey  = $apiKeys['openai_api_key'] ?? '';

        if (empty($llamaKey)) {
            $errorReturn('LlamaCloud API key not configured for this admin. Please set it in Admin Settings.');
        }

        if (empty($openAiKey)) {
            $errorReturn('OpenAI API key not configured for this admin. Please set it in Admin Settings.');
        }
        try {
            // ── Step 1: Parse via LlamaCloud ──
            Document::patchStatus($documentId, 'parsing');

            $llamaClient = new LlamaParseClient($llamaKey);
            $parseResult = $llamaClient->parseFile(
                $document['file_path'],
                $document['mime_type'] ?: 'application/octet-stream',
                'cost_effective'
            );

            if ($parseResult['status'] !== 'COMPLETED' || $parseResult['markdown'] === null) {
                $errorMsg = $parseResult['error'] ?? 'Unknown parsing error';
                Document::patchStatus($documentId, 'failed');
                $errorReturn('Document parsing failed: ' . $errorMsg);
            }

            $parsedText = $parseResult['markdown'];
            Document::saveParsedText($documentId, $parsedText);

            // ── Step 2: Branch on retrieval strategy ──
            $retrievalStrategy = $chatbot['retrieval_strategy'] ?? 'traditional_rag';

            if ($retrievalStrategy === 'page_index') {
                // ── PageIndex: build hierarchical tree from parsed text ──
                Document::patchStatus($documentId, 'chunking');
                $pageIndexBuilder = new PageIndexBuilder();
                $nodeCount = $pageIndexBuilder->buildAndStore($adminId, $chatbotId, $documentId, $parsedText);

                if ($nodeCount === 0) {
                    Document::patchStatus($documentId, 'failed');
                    $errorReturn('No sections could be extracted from the document.');
                }

                Document::markPageIndexed($documentId);
                Document::recordStrategy($adminId, $chatbotId, $documentId, $document['original_name'], 'page_index');

                AuditLog::log(
                    $adminId,
                    (int) $user['id'],
                    'train_document',
                    'document',
                    $documentId,
                    null,
                    ['name' => $document['original_name'], 'strategy' => 'page_index', 'nodes' => $nodeCount]
                );

                if ($isAjax) {
                    $res->json([
                        'ok'     => true,
                        'id'     => $documentId,
                        'name'   => $document['original_name'],
                        'chunks' => $nodeCount,
                        'message' => 'Document trained successfully! ' . $nodeCount . ' sections indexed via PageIndex.',
                    ])->send();
                } else {
                    Session::flash('success', 'Document trained successfully! ' . $nodeCount . ' sections indexed via PageIndex.');
                    $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
                }
                return;
            }

            // ── Traditional RAG: chunk → embed → insert vector chunks ──
            Document::patchStatus($documentId, 'chunking');
            $chunker = new ChunkingService();
            $chunks  = $chunker->chunk($parsedText, 500, 50);

            if (empty($chunks)) {
                Document::patchStatus($documentId, 'failed');
                $errorReturn('No text chunks could be extracted from the document.');
            }

            // ── Step 3: Embed each chunk ──
            Document::patchStatus($documentId, 'embedding');
            $openAi      = new OpenAIClient($openAiKey);
            $texts       = array_map(fn(array $c): string => $c['chunk_text'], $chunks);
            $jsonArrays  = $openAi->embedBatchAsJsonArray($texts);

            if (count($jsonArrays) !== count($chunks)) {
                Document::patchStatus($documentId, 'failed');
                $errorReturn('Embedding generation failed (count mismatch).');
            }

            // ── Step 4: Insert chunks with embeddings ──
            $docChunkData = [];
            foreach ($chunks as $i => $chunk) {
                $docChunkData[] = [
                    'chunk_index' => $chunk['chunk_index'],
                    'chunk_text'  => $chunk['chunk_text'],
                    'embedding'   => $jsonArrays[$i],
                ];
            }

            DocumentChunk::insertBatch($adminId, $chatbotId, $documentId, $docChunkData);

            // ── Step 5: Mark indexed ──
            Document::markIndexed($documentId);
            Document::recordStrategy($adminId, $chatbotId, $documentId, $document['original_name'], 'traditional_rag');

            AuditLog::log(
                $adminId,
                (int) $user['id'],
                'train_document',
                'document',
                $documentId,
                null,
                ['name' => $document['original_name'], 'chunks' => count($docChunkData)]
            );

            if ($isAjax) {
                $res->json([
                    'ok'     => true,
                    'id'     => $documentId,
                    'name'   => $document['original_name'],
                    'chunks' => count($docChunkData),
                    'message' => 'Document trained successfully! ' . count($docChunkData) . ' chunks indexed.',
                ])->send();
            } else {
                Session::flash('success', 'Document trained successfully! ' . count($docChunkData) . ' chunks indexed.');
                $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
            }
        } catch (\Throwable $e) {
            Document::patchStatus($documentId, 'failed');
            $errorMsg = $e->getMessage();
            AuditLog::log(
                $adminId,
                (int) $user['id'],
                'train_document_failed',
                'document',
                $documentId,
                null,
                ['name' => $document['original_name'] ?? '', 'error' => $errorMsg]
            );
            $errorReturn('Document training failed: ' . $errorMsg);
        }
    }

    /**
     * Delete a document and its chunks.
     */
    public function delete(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_documents');
        $chatbotId  = (int) ($params['id'] ?? 0);
        $documentId = (int) ($params['did'] ?? 0);
        $adminId   = (int) $user['admin_id'];

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
            return;
        }

        $chatbot = Chatbot::find($chatbotId);
        if (!$chatbot || !\App\Controller\ChatbotController::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $document = Document::find($documentId);
        if (!$document || (int) $document['admin_id'] !== $adminId || (int) $document['chatbot_id'] !== $chatbotId) {
            $res->setStatus(404)->html('<h1>Document not found.</h1>', 404)->send();
            return;
        }

        // Remove file from disk
        $filePath = $document['file_path'];
        if ($filePath && file_exists($filePath)) {
            @unlink($filePath);
        }

        // Delete document record (also deletes chunks via model)
        Document::delete($documentId);

        AuditLog::log(
            $adminId,
            (int) $user['id'],
            'delete_document',
            'document',
            $documentId,
            null,
            ['name' => $document['original_name']]
        );

        Session::flash('success', 'Document deleted successfully.');
        $res->redirect('/chatbots/' . $chatbotId . '/documents')->send();
    }
}
