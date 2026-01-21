<?php

namespace App\Http\Controllers;

use App\Jobs\SubmitToSiHalalJob;
use App\Models\JotformSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubmitToSiHalalController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        try {
            // Get the JotformSync record
            $record = JotformSync::findOrFail($id);

            // Check if reg_id exists
            if (empty($record->reg_id)) {
                return redirect()->back()
                    ->with('error', "Reg ID tidak ditemukan untuk submission ini");
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
                return redirect()->back()
                    ->with('error', 'Tidak semua section sudah selesai. Mohon lengkapi semua section sebelum submit.');
            }

            // Dispatch job to submit to SiHalal
            dispatch(new SubmitToSiHalalJob($record->id, auth()->id()));

            Log::info('SubmitToSiHalalJob dispatched', [
                'submission_id' => $record->submission_id,
                'reg_id' => $record->reg_id,
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()
                ->with('success', 'Data sedang diproses untuk disubmit ke halal.go.id. Status akan diperbarui setelah proses selesai.');

        } catch (\Exception $e) {
            Log::error('Exception in SubmitToSiHalalController', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', "Terjadi kesalahan: {$e->getMessage()}");
        }
    }
}
