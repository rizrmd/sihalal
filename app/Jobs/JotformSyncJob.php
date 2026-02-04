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

    protected int $userId;

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
        // Set sync status to running
        cache()->put('jotform_sync_running', true, now()->addMinutes(10));

        Log::info('JotForm sync job started', [
            'job_id' => $this->job->getJobId() ?? method_exists($this, 'jobId') ? $this->jobId() : 'unknown',
            'user_id' => $this->userId,
        ]);

        try {
            $jotformService = app(JotformService::class);
            $submissions = $jotformService->getSubmissions();

            Log::info('Fetched submissions from JotForm', [
                'count' => count($submissions),
            ]);

            // Get all submission IDs from JotForm
            $jotformSubmissionIds = array_filter(array_column($submissions, 'id'));

            $syncedCount = 0;
            $updatedCount = 0;
            $deletedCount = 0;
            $errors = [];

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

            // Find and delete submissions that are not in JotForm anymore
            $localSubmissions = JotformSync::all();
            foreach ($localSubmissions as $localSubmission) {
                if (!in_array($localSubmission->submission_id, $jotformSubmissionIds)) {
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

            Log::info('JotForm sync completed', [
                'synced' => $syncedCount,
                'updated' => $updatedCount,
                'deleted' => $deletedCount,
                'errors' => count($errors),
                'total_submissions' => count($submissions),
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
}
