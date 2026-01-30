<?php

namespace App\Http\Controllers;

use App\Services\JotformService;
use App\Models\JotformSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JotFormController extends Controller
{
    protected JotformService $jotformService;

    public function __construct(JotformService $jotformService)
    {
        $this->jotformService = $jotformService;
    }

    /**
     * Sync submissions from JotForm
     */
    public function sync(Request $request)
    {
        try {
            // Get submissions from JotForm API
            $submissions = $this->jotformService->getSubmissions();

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
                    $data = $this->jotformService->formatSubmissionData($submission);

                    // Check if submission already exists
                    $existing = JotformSync::where('submission_id', $submissionId)->first();

                    if ($existing) {
                        // Update existing record
                        $existing->update($data);
                        $updatedCount++;
                    } else {
                        // Create new record
                        JotformSync::create($data);
                        $syncedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'submission_id' => $submission['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
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
                    } catch (\Exception $e) {
                        $errors[] = [
                            'submission_id' => $localSubmission->submission_id,
                            'error' => 'Failed to delete: ' . $e->getMessage(),
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Sync completed',
                'data' => [
                    'total_submissions' => count($submissions),
                    'synced' => $syncedCount,
                    'updated' => $updatedCount,
                    'deleted' => $deletedCount,
                    'errors' => count($errors),
                    'error_details' => $errors,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync submissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all synced submissions
     */
    public function index(Request $request)
    {
        $query = JotformSync::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status_submit', $request->input('status'));
        }

        // Search by name or email in JSON answers array
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                // Search in answers array using JSON functions
                $q->whereJsonContains('payload->answers', [
                    ['name' => 'nama', 'answer' => $search]
                ])
                ->orWhere('payload->answers', 'like', '%' . $search . '%');
            });
        }

        // Order by created_at
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = $request->input('per_page', 15);
        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get form details from JotForm
     */
    public function formDetails(Request $request)
    {
        $details = $this->jotformService->getFormDetails();

        if (!$details) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get form details',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    /**
     * Manual sync from web
     */
    public function syncWeb(Request $request)
    {
        try {
            $submissions = $this->jotformService->getSubmissions();

            // Get all submission IDs from JotForm
            $jotformSubmissionIds = array_filter(array_column($submissions, 'id'));

            $syncedCount = 0;
            $updatedCount = 0;
            $deletedCount = 0;

            // Sync or update submissions from JotForm
            foreach ($submissions as $submission) {
                $submissionId = $submission['id'] ?? null;

                if (!$submissionId) {
                    continue;
                }

                $data = $this->jotformService->formatSubmissionData($submission);
                $existing = JotformSync::where('submission_id', $submissionId)->first();

                if ($existing) {
                    $existing->update($data);
                    $updatedCount++;
                } else {
                    JotformSync::create($data);
                    $syncedCount++;
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
                    } catch (\Exception $e) {
                        Log::error('Failed to delete local submission', [
                            'submission_id' => $localSubmission->submission_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return back()->with('success', "Sync completed: {$syncedCount} new, {$updatedCount} updated, {$deletedCount} deleted");

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to sync: ' . $e->getMessage());
        }
    }

    /**
     * Show sync stats
     */
    public function stats()
    {
        $stats = [
            'total' => JotformSync::count(),
            'active' => JotformSync::where('status_submit', 'ACTIVE')->count(),
            'today' => JotformSync::whereDate('created_at', today())->count(),
            'this_week' => JotformSync::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])->count(),
            'this_month' => JotformSync::whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
