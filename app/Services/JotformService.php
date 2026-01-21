<?php

namespace App\Services;

use App\Models\SiHalal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JotFormService
{
    protected string $apiKey;
    protected string $formId;
    protected string $baseUrl;

    public function __construct()
    {
        $config = SiHalal::latest()->first();

        if (!$config) {
            throw new \Exception('SiHalal configuration not found. Please configure API Key and Form ID first.');
        }

        $this->apiKey = $config->api_key;
        $this->formId = $config->form_id;
        $this->baseUrl = 'https://api.jotform.com';
    }

    /**
     * Get all submissions from a JotForm
     * Only returns submissions that are NOT deleted
     */
    public function getSubmissions(array $filters = []): array
    {
        try {
            $url = "{$this->baseUrl}/form/{$this->formId}/submissions";

            // Add apiKey to query parameters
            $filters['apiKey'] = $this->apiKey;

            // Build filter to exclude deleted submissions
            $statusFilter = ['status:neq' => 'DELETED'];

            // Merge with existing filter if provided
            if (isset($filters['filter'])) {
                $existingFilter = json_decode($filters['filter'], true);
                if (is_array($existingFilter)) {
                    $statusFilter = array_merge($existingFilter, $statusFilter);
                }
            }

            $filters['filter'] = json_encode($statusFilter);

            $response = Http::get($url, $filters);

            if ($response->successful()) {
                $data = $response->json();
                $submissions = $data['content'] ?? [];

                // Additional filtering to ensure no deleted submissions are returned
                $filtered = array_filter($submissions, function($submission) {
                    return ($submission['status'] ?? 'ACTIVE') !== 'DELETED';
                });

                return $filtered;
            }

            Log::error('JotForm API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('JotForm Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Get submissions with pagination
     */
    public function getSubmissionsPaginated(int $limit = 100, int $offset = 0): array
    {
        return $this->getSubmissions([
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Get submissions filtered by date range
     */
    public function getSubmissionsByDateRange(
        string $startDate,
        string $endDate
    ): array {
        return $this->getSubmissions([
            'filter' => json_encode([
                'created_at:gt' => $startDate,
                'created_at:lt' => $endDate,
            ]),
        ]);
    }

    /**
     * Get form details
     */
    public function getFormDetails(): ?array
    {
        try {
            $response = Http::get("{$this->baseUrl}/form/{$this->formId}", [
                'apiKey' => $this->apiKey,
            ]);

            if ($response->successful()) {
                return $response->json()['content'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('JotForm getFormDetails Error', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Format submission data for storage
     */
    public function formatSubmissionData(array $submission): array
    {
        $answers = $submission['answers'] ?? [];

        // Extract sortable fields for database columns
        $namaLengkap = null;
        $email = null;
        $namaSppg = null;

        // Filter only fields that have "answer" key and process file uploads
        $formattedAnswers = [];

        foreach ($answers as $fieldId => $fieldData) {
            // Skip if no answer key
            if (!isset($fieldData['answer'])) {
                continue;
            }

            // Extract sortable fields
            $fieldName = $fieldData['name'] ?? null;
            if ($fieldName === 'nama') {
                $answerValue = $fieldData['answer'];
                if (is_array($answerValue)) {
                    $first = $answerValue['first'] ?? '';
                    $last = $answerValue['last'] ?? '';
                    $namaLengkap = trim($first . ' ' . $last);
                } else {
                    $namaLengkap = $answerValue;
                }
            } elseif ($fieldName === 'email') {
                $email = is_array($fieldData['answer']) ? implode(', ', $fieldData['answer']) : $fieldData['answer'];
            } elseif ($fieldName === 'namaSppg135') {
                $namaSppg = is_array($fieldData['answer']) ? implode(', ', $fieldData['answer']) : $fieldData['answer'];
            }

            // Build base field data
            $formattedField = [
                'name' => $fieldData['name'] ?? null,
                'order' => $fieldData['order'] ?? null,
                'text' => $fieldData['text'] ?? null,
                'type' => $fieldData['type'] ?? null,
            ];

            // Handle file upload type
            if (($fieldData['type'] ?? null) === 'control_fileupload' && !empty($fieldData['answer'])) {
                $formattedField['answer'] = $this->handleFileUpload(
                    $fieldData['answer'],
                    $submission['id'],
                    $fieldData['name']
                );
            } else {
                // For non-file fields, just store the answer
                $formattedField['answer'] = $fieldData['answer'];
            }

            $formattedAnswers[] = $formattedField;
        }

        // Build payload with submission metadata and formatted answers
        $payload = [
            'submission_id' => $submission['id'],
            'form_id' => $submission['form_id'],
            'created_at' => $submission['created_at'],
            'status' => $submission['status'] ?? 'ACTIVE',
            'answers' => $formattedAnswers,
            'submission_data' => [
                'ip' => $submission['ip'] ?? null,
                'created_at' => $submission['created_at'] ?? null,
                'updated_at' => $submission['updated_at'] ?? null,
            ],
        ];

        // Default JSON data structure
        $defaultJsonData = [
            'status' => 'new',
            'notes' => [],
        ];

        return [
            'form_id' => $submission['form_id'],
            'submission_id' => $submission['id'],
            'payload' => $payload,
            'status_submit' => $submission['status'] ?? 'ACTIVE',
            'nama_lengkap' => $namaLengkap,
            'email' => $email,
            'nama_sppg' => $namaSppg,
            'data_pengajuan' => $defaultJsonData,
            'komitmen_tanggung_jawab' => $defaultJsonData,
            'bahan' => $defaultJsonData,
            'proses' => $defaultJsonData,
            'produk' => $defaultJsonData,
            'pemantauan_evaluasi' => $defaultJsonData,
        ];
    }

    /**
     * Handle file upload from JotForm
     * Download file and store in Laravel storage
     */
    protected function handleFileUpload($fileUrl, $submissionId, $fieldName): string|array|null
    {
        try {
            // Handle multiple files (array)
            if (is_array($fileUrl)) {
                $urls = [];
                foreach ($fileUrl as $index => $url) {
                    $urls[] = $this->downloadAndStoreFile($url, $submissionId, $fieldName, $index);
                }
                return $urls;
            }

            // Handle single file
            return $this->downloadAndStoreFile($fileUrl, $submissionId, $fieldName);
        } catch (\Exception $e) {
            Log::error('Failed to handle file upload', [
                'file_url' => $fileUrl,
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
            ]);

            return $fileUrl; // Return original URL as fallback
        }
    }

    /**
     * Download file from URL and store in storage
     */
    protected function downloadAndStoreFile(string $fileUrl, string $submissionId, string $fieldName, ?int $index = null): ?string
    {
        try {
            // Get original filename from URL
            $path = parse_url($fileUrl, PHP_URL_PATH);
            $originalFilename = basename($path);

            // Handle query parameters in filename (JotForm adds ?ts=timestamp)
            $originalFilename = preg_replace('/\?.*$/', '', $originalFilename);

            // If filename is empty, generate one
            if (empty($originalFilename)) {
                $extension = $this->getFileExtension($fileUrl);
                $originalFilename = $fieldName . ($index !== null ? "_{$index}" : '') . '.' . $extension;
            }

            // Generate storage path with original filename
            $filename = "jotform/{$submissionId}/{$originalFilename}";

            // Create directory if not exists
            $directory = dirname(Storage::disk('public')->path($filename));
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Append API key to URL for JotForm authentication
            $downloadUrl = $fileUrl . (strpos($fileUrl, '?') !== false ? '&' : '?') . 'apiKey=' . $this->apiKey;

            // Download file using HTTP client with proper headers
            $response = Http::withOptions([
                'verify' => false,
                'follow_redirects' => true,
                'timeout' => 60,
            ])->get($downloadUrl);

            if (!$response->successful()) {
                Log::warning('Failed to download file', [
                    'url' => $fileUrl,
                    'status' => $response->status(),
                ]);
                return $fileUrl;
            }

            // Get raw body content (binary safe)
            $fileContent = $response->body();

            if (empty($fileContent)) {
                Log::warning('Downloaded file content is empty', ['url' => $fileUrl]);
                return $fileUrl;
            }

            // Store file as binary
            Storage::disk('public')->put($filename, $fileContent);

            // Verify file was created and has content
            $filePath = Storage::disk('public')->path($filename);
            if (!file_exists($filePath) || filesize($filePath) === 0) {
                Log::warning('Downloaded file is empty or does not exist', ['url' => $fileUrl, 'path' => $filePath]);
                return $fileUrl;
            }

            // Return storage path only (not full URL)
            return $filename;
        } catch (\Exception $e) {
            Log::error('Failed to download and store file', [
                'url' => $fileUrl,
                'error' => $e->getMessage(),
            ]);

            return $fileUrl;
        }
    }

    /**
     * Get file extension from URL or Content-Type
     */
    protected function getFileExtension(string $url, ?string $contentType = null): string
    {
        // Try to get extension from URL path first
        $path = parse_url($url, PHP_URL_PATH);

        if ($path && preg_match('/\.([^.]+)$/', $path, $matches)) {
            return strtolower($matches[1]);
        }

        // Try to get extension from Content-Type header
        if ($contentType) {
            $mimeMap = [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
            ];

            foreach ($mimeMap as $mime => $ext) {
                if (stristr($contentType, $mime)) {
                    return $ext;
                }
            }
        }

        // Default extension
        return 'jpg';
    }
}
