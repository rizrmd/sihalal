<?php

namespace App\Jobs;

use App\Models\JotformSync;
use App\Services\JotformService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class JotformSyncJob implements ShouldQueue
{
    use Queueable;

    // Maximum time in seconds before job is considered failed
    public int $timeout = 600; // 10 minutes

    // Number of times the job may be attempted
    public int $tries = 1; // No retry for sync to avoid duplicate data

    protected int $userId;

    // Process submissions in batches to avoid memory exhaustion
    protected int $batchSize = 20;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $userId = null)
    {
        $this->userId = $userId ?? auth()->id();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Increase memory limit for this job only
        ini_set('memory_limit', '256M');

        // Set sync status to running
        cache()->put('jotform_sync_running', true, now()->addMinutes(10));

        Log::info('JotForm sync job started', [
            'user_id' => $this->userId,
        ]);

        $syncedCount = 0;
        $updatedCount = 0;
        $deletedCount = 0;
        $errors = [];
        $allJotformIds = [];

        try {
            $jotformService = app(JotformService::class);

            // Process submissions in batches with pagination
            $offset = 0;
            $limit = $this->batchSize;
            $hasMore = true;

            while ($hasMore) {
                Log::info('Fetching JotForm submissions batch', [
                    'offset' => $offset,
                    'limit' => $limit,
                ]);

                // Get batch of submissions
                $submissions = $jotformService->getSubmissionsPaginated($limit, $offset);

                if (empty($submissions)) {
                    $hasMore = false;
                    break;
                }

                Log::info('Processing batch', [
                    'batch_count' => count($submissions),
                    'offset' => $offset,
                ]);

                // Collect all JotForm IDs for deletion check later
                $batchIds = array_filter(array_column($submissions, 'id'));
                $allJotformIds = array_merge($allJotformIds, $batchIds);

                // Sync or update submissions from JotForm
                foreach ($submissions as $submission) {
                    try {
                        $submissionId = $submission['id'] ?? null;

                        if (!$submissionId) {
                            continue;
                        }

                        // Format data
                        $data = $jotformService->formatSubmissionData($submission);

                        // Check if submission already exists
                        $existing = JotformSync::where('submission_id', $submissionId)->first();

                        if ($existing) {
                            if ($existing->status_submit == 'SENT') {
                                Log::debug('Skipping submission with SENT status', [
                                    'submission_id' => $submissionId,
                                ]);
                                continue;
                            }

                            $existing->update($data);
                            $updatedCount++;

                            Log::debug('Updated existing submission', [
                                'submission_id' => $submissionId,
                            ]);
                        } else {
                            JotformSync::create($data);
                            $syncedCount++;

                            Log::debug('Created new submission', [
                                'submission_id' => $submissionId,
                            ]);
                        }
                    } catch (\Exception $e) {
                        $errors[] = [
                            'submission_id' => $submission['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ];

                        Log::error('Failed to sync JotForm submission', [
                            'submission_id' => $submission['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Free memory after processing batch
                unset($submissions);

                // Move to next batch
                $offset += $limit;

                // If we got less than limit, we're done
                if (count($batchIds) < $limit) {
                    $hasMore = false;
                }
            }

            // Find and delete submissions that are not in JotForm anymore
            // Use chunk() to avoid loading all records into memory
            JotformSync::chunk(100, function ($localSubmissions) use ($allJotformIds, &$deletedCount) {
                foreach ($localSubmissions as $localSubmission) {
                    if (!in_array($localSubmission->submission_id, $allJotformIds)) {
                        try {
                            // Delete files first
                            $localSubmission->deleteSubmissionFiles();

                            // Delete record
                            $localSubmission->delete();

                            $deletedCount++;

                            Log::info('Deleted submission that was removed from JotForm', [
                                'submission_id' => $localSubmission->submission_id,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to delete local submission', [
                                'submission_id' => $localSubmission->submission_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            });

            Log::info('JotForm sync completed', [
                'synced' => $syncedCount,
                'updated' => $updatedCount,
                'deleted' => $deletedCount,
                'errors' => count($errors),
            ]);

        } catch (\Exception $e) {
            Log::error('JotForm sync job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            // Clear sync status regardless of success or failure
            cache()->forget('jotform_sync_running');
        }
    }

    /**
     * Handle a job failure.
     * This method is ALWAYS called by Laravel queue worker when the job fails,
     * ensuring the sync status is cleared even if the job crashes, times out,
     * or encounters any other type of failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Make sure cache is cleared
        cache()->forget('jotform_sync_running');

        Log::error('JotForm sync job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
