<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function dats_gemini_api_key(): string
{
    return dats_env('DATS_GEMINI_API_KEY', '');
}

function dats_gemini_model(): string
{
    return dats_env('DATS_GEMINI_MODEL', 'gemini-flash-latest');
}

/** Tried once, after the primary model's own retries are exhausted — see dats_gemini_transcribe_gradesheet(). */
function dats_gemini_fallback_model(): string
{
    return dats_env('DATS_GEMINI_FALLBACK_MODEL', 'gemini-flash-lite-latest');
}

function dats_gemini_is_available(): bool
{
    return trim(dats_gemini_api_key()) !== '';
}

function dats_gemini_rate_limit_per_minute(): int
{
    return max(1, (int) dats_env('DATS_GEMINI_RPM_LIMIT', '8'));
}

function dats_gemini_rate_limit_per_day(): int
{
    return max(1, (int) dats_env('DATS_GEMINI_RPD_LIMIT', '150'));
}

/**
 * Conservative, configurable caps on Gemini calls so a burst of gradesheet uploads can't run
 * past the free-tier quota and start incurring charges. Defaults are deliberately well under
 * typical Gemini Flash free-tier limits — check your actual quota at
 * https://ai.google.dev/gemini-api/docs/rate-limits and adjust DATS_GEMINI_RPM_LIMIT /
 * DATS_GEMINI_RPD_LIMIT (see config/database.php's dats_env()) if it differs.
 * Callers should fall back to non-Gemini OCR on refusal rather than failing the upload outright.
 */
function dats_gemini_rate_limit_check(PDO $pdo): array
{
    $pdo->exec('DELETE FROM gemini_api_calls WHERE created_at < (NOW() - INTERVAL 1 DAY)');

    $minuteStatement = $pdo->query('SELECT COUNT(*) FROM gemini_api_calls WHERE created_at >= (NOW() - INTERVAL 1 MINUTE)');
    if ((int) $minuteStatement->fetchColumn() >= dats_gemini_rate_limit_per_minute()) {
        return ['allowed' => false, 'reason' => 'per-minute Gemini AI limit'];
    }

    $dayStatement = $pdo->query('SELECT COUNT(*) FROM gemini_api_calls WHERE created_at >= (NOW() - INTERVAL 1 DAY)');
    if ((int) $dayStatement->fetchColumn() >= dats_gemini_rate_limit_per_day()) {
        return ['allowed' => false, 'reason' => 'daily Gemini AI limit'];
    }

    return ['allowed' => true, 'reason' => null];
}

function dats_gemini_record_call(PDO $pdo): void
{
    $pdo->exec('INSERT INTO gemini_api_calls (created_at) VALUES (NOW())');
}

/**
 * Uploads a document to Google Gemini Files API and returns the file URI and resource name.
 * @return array{file_uri: string, name: string}|null
 */
function dats_gemini_upload_file(string $filePath, string $mimeType): ?array
{
    $key = dats_gemini_api_key();
    if ($key === '' || !is_file($filePath)) {
        return null;
    }

    $fileSize = filesize($filePath);
    if ($fileSize < 1) {
        return null;
    }

    $uploadUrl = "https://generativelanguage.googleapis.com/upload/v1beta/files?key=" . urlencode($key);
    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // A document upload (<=25MB) should never need anywhere near as long as the transcription
    // itself; keeping this short leaves more of the request's time budget for generateContent.
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Goog-Upload-Command: start, upload, finalize',
        'X-Goog-Upload-Header-Content-Length: ' . $fileSize,
        'X-Goog-Upload-Header-Content-Type: ' . $mimeType,
        'Content-Type: ' . $mimeType,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($filePath));

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($res)) {
        return null;
    }

    $data = json_decode($res, true);
    $fileUri = $data['file']['uri'] ?? null;
    $resourceName = $data['file']['name'] ?? null;

    if (!$fileUri || !$resourceName) {
        return null;
    }

    return ['file_uri' => $fileUri, 'name' => $resourceName];
}

/**
 * POSTs a generateContent payload to one model, retrying up to $maxAttempts times on a transient
 * failure (429/5xx, or httpCode 0 — curl gave up waiting; large documents have been observed to
 * just hang rather than error, and a fresh attempt after that routinely succeeds quickly). Each
 * attempt uses a shorter per-try timeout than one long wait, so a stuck attempt is abandoned and
 * retried instead of eating the whole budget; a real client error (4xx other than 429) stops at
 * once. Returns [body, httpCode] — httpCode 200 on success.
 */
function dats_gemini_call_generate(string $key, string $model, array $payload, int $timeoutPerAttempt, int $maxAttempts): array
{
    $transientStatuses = [0, 429, 500, 502, 503, 504];
    $generateUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent?key=" . urlencode($key);
    $res = null;
    $httpCode = 0;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init($generateUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutPerAttempt);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || !in_array($httpCode, $transientStatuses, true) || $attempt === $maxAttempts) {
            break;
        }
        usleep($attempt * 1500000); // 1.5s, then 3s
    }
    return [$res, $httpCode];
}

/**
 * Transcribes a gradesheet PDF or image using Google Gemini Flash.
 * Returns formatted text ready for `parse_report_of_rating_text` / `dats_record_gradesheet_batch`.
 */
function dats_gemini_transcribe_gradesheet(string $filePath, string $mimeType): ?string
{
    $key = dats_gemini_api_key();
    if ($key === '' || !is_file($filePath)) {
        return null;
    }

    // 1. Upload file to Gemini Files API
    $upload = dats_gemini_upload_file($filePath, $mimeType);
    if (!$upload) {
        return null;
    }

    $fileUri = $upload['file_uri'];
    $resourceName = $upload['name'];
    $model = dats_gemini_model();

    $prompt = <<<EOT
Transcribe all student grade rows from all pages of this university Report of Rating / Gradesheet document completely.
Separate each page with a form-feed character "\\f".
On each page, output the header:
Report of Rating
Course No.: <subject_code>
Description: <subject_name>
Semester: <semester>
School Year: <academic_year>
Units: <units>

Then for every student row listed on that page, output one line:
@ROW|<row_num>|<STUDENT_NAME>|<YEAR_COURSE>|<GRADE>|<REMARKS>

Rules:
- STUDENT_NAME must be uppercase (e.g. DELA CRUZ, JUAN A.)
- GRADE must be the grade (e.g. 1.0, 1.25, 1.5, 1.75, 2.0, 2.25, 2.5, 2.75, 3.0, 4.0, 5.0, INC, DRP)
- REMARKS should be PASSED, FAILED, INCOMPLETE, DROPPED
- Do not output markdown code blocks or explanations. Output only the plain transcribed text.
EOT;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    [
                        'fileData' => [
                            'mimeType' => $mimeType,
                            'fileUri' => $fileUri,
                        ],
                    ],
                    [
                        'text' => $prompt,
                    ],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.0,
            'thinkingConfig' => [
                'thinkingBudget' => 0,
            ],
        ],
    ];

    // Worst case ~286s (45s upload + 2x80s primary + 1.5s backoff + 1x80s fallback), kept under
    // both set_time_limit() in dats_resolve_gradesheet_ocr() and Apache's 300s Timeout directive.
    [$res, $httpCode] = dats_gemini_call_generate($key, $model, $payload, 80, 2);
    $fallbackModel = dats_gemini_fallback_model();
    if ($httpCode !== 200 && $fallbackModel !== '' && $fallbackModel !== $model) {
        error_log("Gemini model {$model} failed (HTTP {$httpCode}); trying fallback model {$fallbackModel}.");
        // "Lite" models 400 on a thinkingConfig they don't support; drop it for this call only.
        $fallbackPayload = $payload;
        unset($fallbackPayload['generationConfig']['thinkingConfig']);
        [$res, $httpCode] = dats_gemini_call_generate($key, $fallbackModel, $fallbackPayload, 80, 1);
    }

    // Clean up uploaded file from Gemini Files API (best practice)
    $deleteUrl = "https://generativelanguage.googleapis.com/v1beta/" . $resourceName . "?key=" . urlencode($key);
    $delCh = curl_init($deleteUrl);
    curl_setopt($delCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($delCh, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($delCh, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($delCh, CURLOPT_TIMEOUT, 10);
    @curl_exec($delCh);
    @curl_close($delCh);

    if ($httpCode !== 200 || !is_string($res)) {
        error_log(sprintf('Gemini generateContent failed after retries: HTTP %d, body: %s', $httpCode, substr((string) $res, 0, 500)));
        return null;
    }

    $data = json_decode($res, true);
    $text = trim((string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));

    // Strip any markdown code fences if model wrapped the output
    $text = preg_replace('/^```(?:text)?\r?\n/i', '', $text);
    $text = preg_replace('/\r?\n```$/', '', $text);

    return $text !== '' ? $text : null;
}
