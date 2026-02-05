<?php

namespace App\Jobs;

use App\Models\JotformSync;
use App\Services\JotformService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class JotformSyncJob implements ShouldQueue
{
    use Queueable;

    // Maximum time in seconds before job is considered failed
    public int $timeout = 1800; // 30 minutes

    // Allow multiple attempts for stateful sync
    public int $tries = 100; // Allow many retries

    // Don't release back to queue on timeout - handle it ourselves
    public int $maxExceptions = 1;

    protected int $userId;
    protected ?string $syncId = null;

    // Process submissions in batches to avoid memory exhaustion
    protected int $batchSize = 50;

    // Safe time buffer (seconds) before timeout to dispatch next job
    protected int $timeBuffer = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $userId = null, ?string $syncId = null)
    {
        $this->userId = $userId ?? auth()->id();
        $this->syncId = $syncId ?? 'jotform_sync_' . now()->timestamp;
    }

    /**
     * Get the cache key for this sync session
     */
    protected function getCacheKey(string $suffix): string
    {
        return "{$this->syncId}_{$suffix}";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Increase memory limit for this job only
        ini_set('memory_limit', '256M');

        $startTime = time();
        $timeout = $this->timeout - $this->timeBuffer; // Leave buffer time

        Log::info('JotForm sync job started', [
            'user_id' => $this->userId,
            'sync_id' => $this->syncId,
            'timeout' => $timeout,
        ]);

        $syncedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $deletedCount = 0;
        $errors = [];
        $allJotformIds = [];

        try {
            $jotformService = app(JotformService::class);

            // Get offset from cache (resume from last position or start fresh)
            $offset = cache()->get($this->getCacheKey('offset'), 0);
            $limit = $this->batchSize;
            $hasMore = true;

            // Initialize counters from cache
            $syncedCount = cache()->get($this->getCacheKey('synced'), 0);
            $updatedCount = cache()->get($this->getCacheKey('updated'), 0);
            $skippedCount = cache()->get($this->getCacheKey('skipped'), 0);
            $allJotformIds = cache()->get($this->getCacheKey('jotform_ids'), []);

            Log::info('Resuming JotForm sync', [
                'sync_id' => $this->syncId,
                'offset' => $offset,
                'previous_synced' => $syncedCount,
                'previous_updated' => $updatedCount,
                'previous_skipped' => $skippedCount,
            ]);

            while ($hasMore) {
                // Check if we're approaching timeout
                if ((time() - $startTime) >= $timeout) {
                    Log::info('Approaching timeout, dispatching next job', [
                        'sync_id' => $this->syncId,
                        'elapsed' => time() - $startTime,
                        'offset' => $offset,
                    ]);

                    // Save progress to cache
                    cache()->put($this->getCacheKey('offset'), $offset, now()->addHours(2));
                    cache()->put($this->getCacheKey('synced'), $syncedCount, now()->addHours(2));
                    cache()->put($this->getCacheKey('updated'), $updatedCount, now()->addHours(2));
                    cache()->put($this->getCacheKey('skipped'), $skippedCount, now()->addHours(2));
                    cache()->put($this->getCacheKey('jotform_ids'), $allJotformIds, now()->addHours(2));

                    // Dispatch next job to continue
                    self::dispatch($this->userId, $this->syncId);

                    Log::info('Next job dispatched', [
                        'sync_id' => $this->syncId,
                        'will_continue_from' => $offset,
                    ]);

                    return; // Exit gracefully, next job will continue
                }

                Log::info('Fetching JotForm submissions batch', [
                    'sync_id' => $this->syncId,
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
                    'sync_id' => $this->syncId,
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
                                $skippedCount++;
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

                // Log batch summary
                Log::info('Batch completed', [
                    'sync_id' => $this->syncId,
                    'offset' => $offset,
                    'batch_synced' => $syncedCount,
                    'batch_updated' => $updatedCount,
                    'batch_skipped' => $skippedCount,
                    'total_jotform_ids' => count($allJotformIds),
                ]);

                // Move to next batch
                $offset += $limit;

                // Save progress after each batch
                cache()->put($this->getCacheKey('offset'), $offset, now()->addHours(2));
                cache()->put($this->getCacheKey('synced'), $syncedCount, now()->addHours(2));
                cache()->put($this->getCacheKey('updated'), $updatedCount, now()->addHours(2));
                cache()->put($this->getCacheKey('skipped'), $skippedCount, now()->addHours(2));
                cache()->put($this->getCacheKey('jotform_ids'), $allJotformIds, now()->addHours(2));

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
                'sync_id' => $this->syncId,
                'synced' => $syncedCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'deleted' => $deletedCount,
                'errors' => count($errors),
                'total_jotform_ids' => count($allJotformIds),
                'total_time' => time() - $startTime,
            ]);

            // Clear all cache keys for this sync session
            $this->clearSyncCache();

        } catch (\Exception $e) {
            Log::error('JotForm sync job failed', [
                'sync_id' => $this->syncId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Save progress before throwing
            cache()->put($this->getCacheKey('offset'), $offset ?? 0, now()->addHours(2));
            cache()->put($this->getCacheKey('synced'), $syncedCount, now()->addHours(2));
            cache()->put($this->getCacheKey('updated'), $updatedCount, now()->addHours(2));
            cache()->put($this->getCacheKey('skipped'), $skippedCount, now()->addHours(2));
            cache()->put($this->getCacheKey('jotform_ids'), $allJotformIds ?? [], now()->addHours(2));

            throw $e;
        }
    }

    /**
     * Clear all cache keys for this sync session
     */
    protected function clearSyncCache(): void
    {
        cache()->forget($this->getCacheKey('offset'));
        cache()->forget($this->getCacheKey('synced'));
        cache()->forget($this->getCacheKey('updated'));
        cache()->forget($this->getCacheKey('skipped'));
        cache()->forget($this->getCacheKey('jotform_ids'));
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('JotForm sync job failed', [
            'sync_id' => $this->syncId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Cache is kept for retry, will be cleared on next successful completion
    }
}
