<?php

namespace App\Jobs;

use App\Models\JotformSync;
use App\Models\SiHalal;
use App\Services\HalalGoIdService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SubmitToSiHalalJob implements ShouldQueue
{
    use Queueable;

    protected $recordId;
    protected ?int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $recordId, ?int $userId = null)
    {
        $this->recordId = $recordId;
        $this->userId = $userId ?? auth()->id();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get the JotformSync record
            $record = JotformSync::findOrFail($this->recordId);

            // Check if reg_id exists
            if (empty($record->reg_id)) {
                Log::error('Reg ID not found for submission', [
                    'record_id' => $this->recordId,
                ]);
                return;
            }

            // Check if all sections are done
            $sections = [
                'data_pengajuan' => $record->data_pengajuan['status'] ?? null,
                'komitmen_tanggung_jawab' => $record->komitmen_tanggung_jawab['status'] ?? null,
                'bahan' => $record->bahan['status'] ?? null,
                'proses' => $record->proses['status'] ?? null,
                'produk' => $record->produk['status'] ?? null,
                'pemantauan_evaluasi' => $record->pemantauan_evaluasi['status'] ?? null,
            ];

            // Check if all sections are 'done'
            $allDone = collect($sections)->every(fn ($status) => $status === 'done');

            if (!$allDone) {
                Log::warning('Not all sections are done', [
                    'record_id' => $this->recordId,
                    'sections' => $sections,
                ]);
                return;
            }

            // Get bearer token from si_halal_configuration
            $config = SiHalal::latest()->first();

            if (!$config || empty($config->bearer_token)) {
                Log::error('Bearer token not found in si_halal_configuration');
                return;
            }

            // Initialize service with bearer token
            $service = new HalalGoIdService($config->bearer_token);

            // Submit to API
            $result = $service->submitSubmission($record->reg_id);

            if ($result['success']) {
                // Update status_submit to SENT
                $record->update([
                    'status_submit' => 'SENT',
                ]);

                Log::info('Successfully submitted to SiHalal', [
                    'submission_id' => $record->submission_id,
                    'reg_id' => $record->reg_id,
                    'response' => $result['data'],
                ]);
            } else {
                Log::error('Failed to submit to SiHalal', [
                    'submission_id' => $record->submission_id,
                    'reg_id' => $record->reg_id,
                    'message' => $result['message'],
                    'status' => $result['status'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception in SubmitToSiHalalJob', [
                'record_id' => $this->recordId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SubmitToSiHalalJob failed', [
            'record_id' => $this->recordId,
            'error' => $exception->getMessage(),
        ]);
    }
}
