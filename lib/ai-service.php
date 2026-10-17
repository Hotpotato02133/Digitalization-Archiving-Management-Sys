<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function dats_gemini_api_key(): string
{
    return dats_env('DATS_GEMINI_API_KEY', 'AQ.Ab8RN6LB20zuDJxZBjwJzqeST2s6iEmvFiRaN90FSAL6jHqu7w');
}

function dats_gemini_model(): string
{
    return dats_env('DATS_GEMINI_MODEL', 'gemini-flash-latest');
}

function dats_gemini_is_available(): bool
{
    return trim(dats_gemini_api_key()) !== '';
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
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
    $generateUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent?key=" . urlencode($key);

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

    $ch = curl_init($generateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

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
        return null;
    }

    $data = json_decode($res, true);
    $text = trim((string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));

    // Strip any markdown code fences if model wrapped the output
    $text = preg_replace('/^```(?:text)?\r?\n/i', '', $text);
    $text = preg_replace('/\r?\n```$/', '', $text);

    return $text !== '' ? $text : null;
}
