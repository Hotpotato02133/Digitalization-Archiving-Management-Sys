<?php
declare(strict_types=1);

final class DatsDuplicateDocumentException extends RuntimeException
{
    public function __construct(public readonly array $document)
    {
        parent::__construct('This document already exists for this student.');
    }
}

function dats_normalize_document_content(?string $content): string
{
    $normalized = trim((string) $content);
    if ($normalized === '') return '';
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?: $normalized;
    return function_exists('mb_strtolower') ? mb_strtolower($normalized, 'UTF-8') : strtolower($normalized);
}

function dats_document_content_fingerprint(?string $ocrText, ?string $absoluteFilePath = null): array
{
    $normalizedText = dats_normalize_document_content($ocrText);
    if (strlen($normalizedText) >= 32) {
        return ['hash' => hash('sha256', $normalizedText), 'type' => 'ocr'];
    }
    if ($absoluteFilePath && is_file($absoluteFilePath)) {
        $fileHash = hash_file('sha256', $absoluteFilePath);
        if ($fileHash !== false) return ['hash' => $fileHash, 'type' => 'file'];
    }
    return ['hash' => null, 'type' => null];
}

function dats_find_duplicate_document(PDO $pdo, int $studentId, ?string $ocrText, ?string $contentHash, ?string $contentType = null): ?array
{
    $statement = $pdo->prepare("SELECT id, student_id, original_filename, category_id, file_path, ocr_text, content_hash FROM documents WHERE student_id = :student_id AND status = 'Archived' AND deleted_at IS NULL");
    $statement->execute([':student_id' => $studentId]);
    $normalizedText = dats_normalize_document_content($ocrText);
    while ($document = $statement->fetch(PDO::FETCH_ASSOC)) {
        if ($contentHash !== null && hash_equals((string) $contentHash, (string) ($document['content_hash'] ?? ''))) {
            return $document;
        }
        if ($contentType === 'file' && $contentHash !== null && !empty($document['file_path'])) {
            $existingPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $document['file_path']), DIRECTORY_SEPARATOR);
            if (is_file($existingPath) && hash_file('sha256', $existingPath) === $contentHash) return $document;
        }
        if ($normalizedText !== '' && strlen($normalizedText) >= 32 && $normalizedText === dats_normalize_document_content($document['ocr_text'] ?? null)) {
            return $document;
        }
    }
    return null;
}