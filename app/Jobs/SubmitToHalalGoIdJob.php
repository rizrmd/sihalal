<?php

namespace App\Jobs;

use App\Models\JotformSync;
use App\Models\SiHalal;
use App\Notifications\HalalGoIdTokenExpired;
use App\Services\HalalGoIdService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SubmitToHalalGoIdJob implements ShouldQueue
{
    use Queueable;

    protected array $recordIds;
    protected ?int $userId;
    protected ?string $section;

    /**
     * Create a new job instance.
     */
    public function __construct(array $recordIds, ?int $userId = null, ?string $section = null)
    {
        $this->recordIds = $recordIds;
        $this->userId = $userId ?? auth()->id();
        $this->section = $section; // null = all sections, 'data_pengajuan' = only data pengajuan, etc.
    }

    /**
     * Check if a specific section should be processed
     */
    protected function shouldProcessSection(string $sectionName): bool
    {
        // If section is null, process all sections
        if ($this->section === null) {
            return true;
        }

        // Only process the specified section
        return $this->section === $sectionName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get bearer token from si_halal_configuration
            $config = SiHalal::latest()->first();

            if (!$config || empty($config->bearer_token)) {
                Log::error('Bearer token not found in si_halal_configuration');
                return;
            }

            // Initialize service with bearer token
            $service = new HalalGoIdService($config->bearer_token);

            $successCount = 0;
            $failedCount = 0;
            $errors = [];

            // Get all records
            $records = JotformSync::whereIn('id', $this->recordIds)->get();

            // Call pelaku usaha profile endpoint
            $pelakuUsahaResult = $service->getPelakuUsahaProfile();

            if (!$pelakuUsahaResult['success']) {
                $errorMessage = $pelakuUsahaResult['message'];
                $errorCode = $pelakuUsahaResult['status'] ?? null;

                // Check if error is unauthorized token
                if (
                    $errorCode === 400006 ||
                    str_contains($errorMessage, 'user token is unauthorized') ||
                    str_contains($errorMessage, 'unauthorized')
                ) {
                    // Send database notification to user about unauthorized token
                    if ($this->userId) {
                        $user = \App\Models\User::find($this->userId);
                        if ($user) {
                            $user->notify(new HalalGoIdTokenExpired());
                        }
                    }

                    Log::error('Unauthorized token error detected', [
                        'error_code' => $errorCode,
                        'message' => $errorMessage,
                        'user_id' => $this->userId,
                    ]);

                    // Stop job execution
                    return;
                }

                throw new \Exception('Failed to get pelaku usaha profile: ' . $errorMessage);
            }
            $pelakuUsahaData = $pelakuUsahaResult['data'];
            $profile = $pelakuUsahaData['data']['business_actor']['profile'];

            foreach ($records as $record) {
                try {
                    // Check if reg_id already exists in database
                    $isNew = false;
                    $idReg = $record->reg_id;
                    $needsSubmitDraft = empty($idReg);

                    if (!empty($idReg)) {
                        // Check if reg_id is still valid by getting pelaku usaha detail
                        $pelakuUsahaDetailResult = $service->getPelakuUsahaDetail($idReg);

                        if (
                            !$pelakuUsahaDetailResult['success'] ||
                            empty($pelakuUsahaDetailResult['data']['data'])
                        ) {
                            // If failed or data is empty, need to submit draft again
                            Log::warning('Pelaku Usaha Detail not found or invalid, resetting reg_id', [
                                'submission_id' => $record->submission_id,
                                'reg_id' => $idReg,
                                'success' => $pelakuUsahaDetailResult['success'],
                                'has_data' => !empty($pelakuUsahaDetailResult['data']['data']),
                            ]);

                            // Reset reg_id and set needsSubmitDraft to true
                            $record->update(['reg_id' => null]);
                            $needsSubmitDraft = true;
                            $idReg = null;
                        }
                    }

                    if ($needsSubmitDraft) {
                        $isNew = true;
                        // Initialize JSON columns with default values
                        $defaultJsonData = [
                            'status' => 'new',
                            'notes' => [],
                        ];

                        // Submit to API using service
                        $result = $service->submitDraft('JD.1');

                        if ($result['success']) {
                            // Update status_submit to INCOMPLETE (not SUBMITTED yet)
                            // Initialize JSON columns with default values
                            $record->update([
                                'status_submit' => 'INCOMPLETE',
                                'data_pengajuan' => $defaultJsonData,
                                'komitmen_tanggung_jawab' => $defaultJsonData,
                                'bahan' => $defaultJsonData,
                                'proses' => $defaultJsonData,
                                'produk' => $defaultJsonData,
                                'pemantauan_evaluasi' => $defaultJsonData,
                            ]);
                            $successCount++;

                            // Get id_reg from submitDraft response
                            $idReg = $result['data']['data']['certificate_halal']['id_reg'] ?? null;

                            // Debug logging
                            Log::info('SubmitDraft Response', [
                                'submission_id' => $record->submission_id,
                                'id_reg_extracted' => $idReg,
                                'response_data' => $result['data'] ?? null,
                            ]);

                            // Save reg_id to database
                            if (!empty($idReg)) {
                                $record->update(['reg_id' => $idReg]);
                                Log::info('reg_id updated successfully', [
                                    'submission_id' => $record->submission_id,
                                    'reg_id' => $idReg,
                                ]);
                            } else {
                                Log::warning('id_reg is empty in submitDraft response', [
                                    'submission_id' => $record->submission_id,
                                    'full_response' => $result,
                                ]);
                            }
                        } else {
                            // Update status_submit to FAILED
                            $record->update(['status_submit' => 'FAILED']);
                            $failedCount++;

                            $errors[] = [
                                'submission_id' => $record->submission_id,
                                'status' => $result['status'],
                                'message' => $result['message'],
                            ];

                            Log::error('Failed to submit to halal.go.id', [
                                'submission_id' => $record->submission_id,
                                'status' => $result['status'],
                                'message' => $result['message'],
                            ]);

                            // Skip further processing if submitDraft failed
                            continue;
                        }
                    }

                    // Get jenis layanan from payload
                    $payload = $record->payload;
                    $jenisLayananAnswer = null;
                    $areaPemasaranAnswer = null;
                    $namaPerusahaanAnswer = null;
                    $alamatPerusahaanAnswer = [];
                    $statusPerusahaanAnswer = null;
                    $nomorSuratAnswer = null;
                    $tanggalSuratAnswer = null;
                    $merekDagangAnswer = null;
                    $jenisProdukAnswer = null;
                    $lphNameAnswer = null;
                    $namaPenanggungAnswer = null;
                    $noHpPenanggungAnswer = null;
                    $emailPenanggungAnswer = null;
                    $daftarBahanAnswer = null;
                    $daftarNamaAnswer = null;
                    $catatanPembelianAnswer = null;
                    $formPemeriksaanAnswer = null;
                    $diagramAlirAnswer = null;
                    $kebijakanHalalAnswer = null;
                    $fotoKebijakanAnswer = null;
                    $fotoLokasidenahAnswer = null;
                    $matriksProdukAnswer = null;
                    $tandaTanganAnswer = null;
                    $buktiPelaksanaanAnswer = null;
                    $namaPenyeliaAnswer = null;

                    if (isset($payload['answers']) && is_array($payload['answers'])) {
                        foreach ($payload['answers'] as $answer) {
                            if (isset($answer['name']) && $answer['name'] === 'jenisLayanan') {
                                $jenisLayananAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'areaPemasaran') {
                                $areaPemasaranAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'namaPerusahaan') {
                                $namaPerusahaanAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'nomorSurat') {
                                $nomorSuratAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'tanggalSurat') {
                                $tanggalSuratAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'merekDagang') {
                                $merekDagangAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'jenisProduk147') {
                                $jenisProdukAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'jenisProduk150') {
                                $lphNameAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'namaPenanggung') {
                                $namaPenanggungAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'noHp170') {
                                $noHpPenanggungAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'emailPenanggung') {
                                $emailPenanggungAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'daftarBahan') {
                                $daftarBahanAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'daftarNama') {
                                $daftarNamaAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'catatanPembelian') {
                                $catatanPembelianAnswer = $answer ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'formPemeriksaan') {
                                $formPemeriksaanAnswer = $answer ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'diagramAlir') {
                                $diagramAlirAnswer = $answer ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'kebijakanHalal') {
                                $kebijakanHalalAnswer = $answer ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'fotoKebijakan152') {
                                $fotoKebijakanAnswer = $answer ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'matriksProduk') {
                                $matriksProdukAnswer = $answer ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'tandaTangan') {
                                $tandaTanganAnswer = $answer ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'buktiPelaksanaan160') {
                                $buktiPelaksanaanAnswer = $answer ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'namaPenyelia') {
                                $namaPenyeliaAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'alamatSppg') {
                                $alamatPerusahaanAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'statusPabrik') {
                                $statusPerusahaanAnswer = $answer['answer'] ?? null;
                            }
                            if (isset($answer['name']) && $answer['name'] === 'fotoLokasidenah') {
                                $fotoLokasidenahAnswer = $answer['answer'] ?? null;
                            }
                        }
                    }

                    // Initialize data_pengajuan notes early (before factory operations)
                    $dataPengajuanNotes = [];

                    // Delete and Create Factory
                    if (empty($record->pabrik_id)) {
                        try {
                            $pelakuUsahaDetailResult = $service->getPelakuUsahaDetail($idReg);
                            if ($pelakuUsahaDetailResult['success']) {
                                $pabriks = $pelakuUsahaDetailResult['data']['data']['pabrik'];
                                foreach ($pabriks as $pabrik) {
                                    // Delete factory if nama_pabrik doesn't match with namaPerusahaanAnswer
                                    if ($pabrik['nama_pabrik'] !== $namaPerusahaanAnswer) {
                                        Log::info('Deleting factory', [
                                            'submission_id' => $record->submission_id,
                                            'id_pabrik' => $pabrik['id_pabrik'],
                                            'nama_pabrik' => $pabrik['nama_pabrik'],
                                            'expected_nama' => $namaPerusahaanAnswer,
                                        ]);

                                        $deleteResult = $service->deleteFactory($pabrik['id_pabrik']);

                                        if ($deleteResult['success']) {
                                            Log::info('Factory deleted successfully', [
                                                'submission_id' => $record->submission_id,
                                                'id_pabrik' => $pabrik['id_pabrik'],
                                            ]);
                                        } else {
                                            $dataPengajuanNotes[] = "Gagal menghapus factory: {$deleteResult['message']} (Status: {$deleteResult['status']})";
                                            Log::warning('Failed to delete factory', [
                                                'submission_id' => $record->submission_id,
                                                'id_pabrik' => $pabrik['id_pabrik'],
                                                'message' => $deleteResult['message'],
                                                'status' => $deleteResult['status'],
                                            ]);
                                        }
                                    }
                                }
                            }

                            // Check if factory with matching name exists, if not create one
                            $factoryExists = false;
                            foreach ($pabriks as $pabrik) {
                                if ($pabrik['nama_pabrik'] === $namaPerusahaanAnswer) {
                                    $factoryExists = true;
                                    $record->update(['pabrik_id' => $pabrik['id_pabrik']]);
                                    Log::info('Factory already exists', [
                                        'submission_id' => $record->submission_id,
                                        'id_pabrik' => $pabrik['id_pabrik'],
                                        'nama_pabrik' => $pabrik['nama_pabrik'],
                                    ]);
                                    break;
                                }
                            }

                            // Create factory if not exists
                            if (!$factoryExists && !empty($namaPerusahaanAnswer) && !empty($alamatPerusahaanAnswer)) {
                                $factoryResult = $this->createFactory($record, $service, $profile['id'], $idReg, $namaPerusahaanAnswer, $alamatPerusahaanAnswer, $statusPerusahaanAnswer);

                                // Check if factory creation failed and add error to notes
                                if (!$factoryResult['success']) {
                                    $dataPengajuanNotes[] = $factoryResult['message'];
                                    Log::warning('Factory error added to notes', [
                                        'submission_id' => $record->submission_id,
                                        'error_message' => $factoryResult['message'],
                                        'notes_so_far' => $dataPengajuanNotes,
                                    ]);
                                }
                            }
                        } catch (\Exception $e) {
                            // Catch any exception during factory operations and add to notes
                            $dataPengajuanNotes[] = "Gagal proses factory: " . $e->getMessage();
                            Log::error('Exception during factory operations', [
                                'submission_id' => $record->submission_id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }

                    # Section : Data Pengajuan
                    if ($this->shouldProcessSection('data_pengajuan')) {
                        // Debug: Check if factory errors are in notes
                        Log::info('Entering data_pengajuan section', [
                            'submission_id' => $record->submission_id,
                            'notes_count' => count($dataPengajuanNotes),
                            'notes' => $dataPengajuanNotes,
                        ]);

                        // Initialize variables with default values
                        $productFilterData = null;
                        $businessActorData = null;
                        $jenisProduk = null;
                        $lphId = null;
                        $jenisLayananCode = null;

                        // Get jenis layanan code from API (optional - for getting product filter and LPH data)
                        if (!empty($jenisLayananAnswer)) {
                            $jenisLayananCode = $service->getJenisLayananCodeByName($jenisLayananAnswer);

                            if ($jenisLayananCode) {
                                Log::info('Jenis Layanan Code Retrieved', [
                                    'submission_id' => $record->submission_id,
                                    'jenis_layanan_name' => $jenisLayananAnswer,
                                    'jenis_layanan_code' => $jenisLayananCode,
                                ]);

                                // Get product filter by layanan code
                                $productFilterResult = $service->getProductFilter($jenisLayananCode);

                                if ($productFilterResult['success']) {
                                    Log::info('Product Filter Retrieved', [
                                        'submission_id' => $record->submission_id,
                                        'layanan_code' => $jenisLayananCode,
                                        'product_filter_data' => $productFilterResult['data'],
                                    ]);

                                    $productFilterData = $productFilterResult['data'];
                                } else {
                                    $dataPengajuanNotes[] = "Gagal mengambil Product Filter: {$productFilterResult['message']}";
                                }

                                // Get business actor LPH
                                if (!empty($idReg)) {
                                    $businessActorResult = $service->getBusinessActorLph(
                                        $idReg,
                                        $jenisLayananCode,
                                        $areaPemasaranAnswer
                                    );

                                    if ($businessActorResult['success']) {
                                        Log::info('Business Actor LPH Retrieved', [
                                            'submission_id' => $record->submission_id,
                                            'id_reg' => $idReg,
                                            'jenis_layanan' => $jenisLayananCode,
                                            'area_pemasaran' => $areaPemasaranAnswer,
                                            'lph_data' => $businessActorResult['data'],
                                        ]);

                                        $businessActorData = $businessActorResult['data'];
                                    } else {
                                        $dataPengajuanNotes[] = "Gagal mengambil Business Actor LPH: {$businessActorResult['message']}";
                                    }
                                }
                            } else {
                                $dataPengajuanNotes[] = "Jenis Layanan Code '{$jenisLayananAnswer}' tidak ditemukan";
                            }
                        }

                        // Extract jenis_produk from product filter response
                        // Find matching product by name from jenisProdukAnswer
                        if (!empty($jenisProdukAnswer) && isset($productFilterData) && is_array($productFilterData)) {
                            foreach ($productFilterData as $product) {
                                if (isset($product['name']) && $product['name'] === $jenisProdukAnswer) {
                                    $jenisProduk = $product['code'] ?? null;
                                    break;
                                }
                            }
                        }

                        // Extract lph_id from business actor response
                        // Find matching LPH by nama_lph from lphNameAnswer
                        if (!empty($lphNameAnswer) && isset($businessActorData) && is_array($businessActorData)) {
                            foreach ($businessActorData['data'] as $lph) {
                                if (isset($lph['nama_lph']) && $lph['nama_lph'] === $lphNameAnswer) {
                                    $lphId = $lph['lph_id'] ?? null;
                                    break;
                                }
                            }
                        }

                        // Format tgl_daftar from datetime to date only
                        $tglDaftar = null;
                        if (!empty($tanggalSuratAnswer)) {
                            if (is_array($tanggalSuratAnswer) && isset($tanggalSuratAnswer['datetime'])) {
                                $tglDaftar = date('Y-m-d', strtotime($tanggalSuratAnswer['datetime']));
                            } else {
                                $tglDaftar = date('Y-m-d', strtotime($tanggalSuratAnswer));
                            }
                        } else {
                            $tglDaftar = date('Y-m-d');
                        }

                        $halalSubmissionData = [
                            'id_reg' => $idReg,
                            'nama_pu' => $namaPerusahaanAnswer ?? '',
                            'no_mohon' => $nomorSuratAnswer ?? '',
                            'tgl_daftar' => $tglDaftar,
                            'jenis_layanan' => $jenisLayananCode ?? '',
                            'jenis_produk' => $jenisProduk ?? '',
                            'merk_dagang' => $merekDagangAnswer ?? '',
                            'area_pemasaran' => $areaPemasaranAnswer ?? '',
                            'lph_id' => $lphId ?? '',
                            'channel_id' => 'CH001',
                            'fac_id' => '',
                        ];

                        // Submit certificate data to API
                        Log::info('Halal Submission Data Aggregated', [
                            'submission_id' => $record->submission_id,
                            'raw_answers' => [
                                'jenisProdukAnswer' => $jenisProdukAnswer,
                                'lphNameAnswer' => $lphNameAnswer,
                                'namaPerusahaanAnswer' => $namaPerusahaanAnswer,
                                'nomorSuratAnswer' => $nomorSuratAnswer,
                                'tanggalSuratAnswer' => $tanggalSuratAnswer,
                                'merekDagangAnswer' => $merekDagangAnswer,
                                'areaPemasaranAnswer' => $areaPemasaranAnswer,
                            ],
                            'halal_submission_data' => $halalSubmissionData,
                        ]);

                        $certificateResult = $service->submitCertificate($halalSubmissionData);

                        if ($certificateResult['success']) {
                            Log::info('Certificate Data Submitted Successfully', [
                                'submission_id' => $record->submission_id,
                                'certificate_response' => $certificateResult['data'],
                            ]);
                        } else {
                            $dataPengajuanNotes[] = "Gagal submit certificate data: {$certificateResult['message']} (Status: {$certificateResult['status']})";
                        }

                        // Prepare penanggung jawab data
                        $penanggungJawabData = [
                            'nama_pj' => $namaPenanggungAnswer ?? '',
                            'no_kontak_pj' => $noHpPenanggungAnswer ?? '',
                            'email_pj' => $emailPenanggungAnswer ?? '',
                            'id_reg' => $idReg,
                        ];

                        // Submit penanggung jawab data
                        $penanggungJawabResult = $service->submitPenanggungJawab($penanggungJawabData);

                        if ($penanggungJawabResult['success']) {
                            Log::info('Penanggung Jawab Data Submitted Successfully', [
                                'submission_id' => $record->submission_id,
                                'penanggung_jawab_response' => $penanggungJawabResult['data'],
                                'penanggung_jawab_data' => $penanggungJawabData,
                            ]);
                        } else {
                            $dataPengajuanNotes[] = "Gagal submit Penanggung Jawab data: {$penanggungJawabResult['message']} (Status: {$penanggungJawabResult['status']})";
                        }

                        // Update data_pengajuan column with status and notes
                        $dataPengajuanStatus = empty($dataPengajuanNotes) ? 'done' : 'failed';

                        // Debug logging before update
                        Log::info('About to update data_pengajuan', [
                            'submission_id' => $record->submission_id,
                            'status' => $dataPengajuanStatus,
                            'notes_count' => count($dataPengajuanNotes),
                            'notes' => $dataPengajuanNotes,
                        ]);

                        $record->update([
                            'data_pengajuan' => [
                                'status' => $dataPengajuanStatus,
                                'notes' => $dataPengajuanNotes,
                            ],
                        ]);

                        // Debug logging after update
                        Log::info('data_pengajuan updated successfully', [
                            'submission_id' => $record->submission_id,
                            'updated_data_pengajuan' => $record->data_pengajuan,
                        ]);
                        # End-Section : Data Pengajuan
                    }

                    # Section : Komitmen dan TanggungJawab
                    if ($this->shouldProcessSection('komitmen_tanggung_jawab')) {
                        $komitmenNotes = [];

                        // Edit Condition
                        if (!$isNew) {
                            $this->resetKomitmenTanggungJawab($record, $service, $idReg, $komitmenNotes);
                        }
                        // End:Edit Condition

                        // Process fotoSk115 - Surat Keputusan Penetapan Penyelia Halal dan Tim Manajemen Halal
                        $fotoSk115Answer = null;
                        if (isset($payload['answers']) && is_array($payload['answers'])) {
                            foreach ($payload['answers'] as $answer) {
                                if (isset($answer['name']) && $answer['name'] === 'fotoSk115') {
                                    $fotoSk115Answer = $answer;
                                    break;
                                }
                            }
                        }

                        if (!empty($fotoSk115Answer) && isset($fotoSk115Answer['answer']) && is_array($fotoSk115Answer['answer'])) {
                            // Find xlsx file in answer array
                            $xlsxFile = null;
                            foreach ($fotoSk115Answer['answer'] as $filePath) {
                                if (str_ends_with(strtolower($filePath), '.xlsx') || str_ends_with(strtolower($filePath), '.xls')) {
                                    $xlsxFile = $filePath;
                                    break;
                                }
                            }

                            if (!empty($xlsxFile)) {
                                $fullPath = storage_path('app/public/' . $xlsxFile);

                                if (file_exists($fullPath)) {
                                    try {
                                        // Use PhpSpreadsheet to read Excel file
                                        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
                                        $worksheet = $spreadsheet->getActiveSheet();

                                        // Get the highest row number
                                        $highestRow = $worksheet->getHighestRow();

                                        // Collect all team members starting from row 14
                                        $timManajemenHalal = [];
                                        $startRow = 14;

                                        for ($row = $startRow; $row <= $highestRow; $row++) {
                                            // Column B = nomor
                                            // Column C = nama
                                            // Column F = jabatan
                                            $nomor = $worksheet->getCell('B' . $row)->getValue();
                                            $nama = $worksheet->getCell('C' . $row)->getValue();
                                            $jabatan = $worksheet->getCell('F' . $row)->getValue();

                                            // Stop if nomor is empty - data below is not part of the team list
                                            if (empty($nomor)) {
                                                break;
                                            }

                                            $timManajemenHalal[] = [
                                                'nomor' => $nomor,
                                                'nama' => $nama,
                                                'jabatan' => $jabatan,
                                            ];
                                        }

                                        Log::info('Tim Manajemen Halal data extracted', [
                                            'submission_id' => $record->submission_id,
                                            'file_path' => $fullPath,
                                            'total_members' => count($timManajemenHalal),
                                            'members' => $timManajemenHalal,
                                        ]);

                                        // Submit each team member to API
                                        if (empty($timManajemenHalal)) {
                                            $komitmenNotes[] = "Tidak ada data Tim Manajemen Halal yang ditemukan dalam file Excel";
                                        } else {
                                            // Edit Condition
                                            if (!$isNew) {
                                                $this->resetKomitmenTanggungJawab($record, $service, $idReg, $komitmenNotes);
                                            }
                                            // End:Edit Condition

                                            $successCount = 0;
                                            $failedCount = 0;

                                            foreach ($timManajemenHalal as $member) {
                                                // nama from Excel column C
                                                // jabatan from Excel column F → posisi parameter
                                                // jabatan parameter is hardcoded as "Tim Manajemen Halal"
                                                $result = $service->addKomitmenTanggungJawab(
                                                    idReg: $idReg,
                                                    nama: $member['nama'],
                                                    posisi: $member['jabatan'],
                                                    jabatan: 'Tim Manajemen Halal'
                                                );

                                                if ($result['success']) {
                                                    $successCount++;
                                                    Log::info('Tim Manajemen Halal member submitted successfully', [
                                                        'submission_id' => $record->submission_id,
                                                        'member' => $member,
                                                        'response' => $result['data'],
                                                    ]);
                                                } else {
                                                    $failedCount++;
                                                    $komitmenNotes[] = "Gagal submit {$member['nama']}: {$result['message']} (Status: {$result['status']})";
                                                    Log::warning('Failed to submit Tim Manajemen Halal member', [
                                                        'submission_id' => $record->submission_id,
                                                        'member' => $member,
                                                        'message' => $result['message'],
                                                        'status' => $result['status'],
                                                    ]);
                                                }
                                            }

                                            Log::info('Tim Manajemen Halal submission completed', [
                                                'submission_id' => $record->submission_id,
                                                'total' => count($timManajemenHalal),
                                                'success' => $successCount,
                                                'failed' => $failedCount,
                                            ]);
                                        }
                                    } catch (\Exception $e) {
                                        $komitmenNotes[] = "Gagal membaca file Excel: " . $e->getMessage();
                                        Log::error('Error reading Excel file', [
                                            'submission_id' => $record->submission_id,
                                            'file_path' => $fullPath,
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                } else {
                                    $komitmenNotes[] = "File Excel tidak ditemukan: {$xlsxFile}";
                                }
                            } else {
                                Log::info('No xlsx file found in fotoSk115 answer', [
                                    'submission_id' => $record->submission_id,
                                    'answer_files' => $fotoSk115Answer['answer'],
                                ]);
                            }
                        } else {
                            Log::info('No fotoSk115 answer found', [
                                'submission_id' => $record->submission_id,
                            ]);
                        }

                        // Update komitmen_tanggung_jawab column with status and notes
                        $komitmenStatus = empty($komitmenNotes) ? 'done' : 'failed';
                        $record->update([
                            'komitmen_tanggung_jawab' => [
                                'status' => $komitmenStatus,
                                'notes' => $komitmenNotes,
                            ],
                        ]);
                    }
                    # End-Section : Komitmen dan TanggungJawab

                    # Section : Data Bahan
                    if ($this->shouldProcessSection('bahan')) {
                        $bahanNotes = []; // Initialize notes array

                        // Upload daftar bahan file if exists
                        if (!empty($daftarBahanAnswer)) {
                            // Handle different answer formats
                            $filePath = null;
                            $fileName = null;

                            if (is_array($daftarBahanAnswer)) {
                                // JotForm file upload format
                                if (isset($daftarBahanAnswer[0])) {
                                    $firstFile = $daftarBahanAnswer[0];
                                    if (is_string($firstFile)) {
                                        // Simple path string
                                        $filePath = storage_path('app/public/' . $firstFile);
                                        $fileName = basename($firstFile);
                                    } elseif (is_array($firstFile)) {
                                        // Array with file details
                                        $filePath = storage_path('app/public/' . ($firstFile['file'] ?? ''));
                                        $fileName = $firstFile['name'] ?? basename($filePath);
                                    }
                                }
                            } elseif (is_string($daftarBahanAnswer)) {
                                // Direct path string
                                $filePath = storage_path('app/public/' . $daftarBahanAnswer);
                                $fileName = basename($daftarBahanAnswer);
                            }

                            if (!empty($filePath) && !empty($fileName) && file_exists($filePath)) {
                                // Edit Condition
                                if (!$isNew) {
                                    $this->resetBahan($record, $service, $idReg, $bahanNotes);
                                }
                                // End:Edit Condition

                                $uploadResult = $service->uploadDaftarBahan($filePath, $fileName);

                                $validatedBahan = $uploadResult['data']['data']['validated_bahan'] ?? null;
                                if ($validatedBahan) {
                                    Log::info('Daftar Bahan File Uploaded Successfully', [
                                        'submission_id' => $record->submission_id,
                                        'upload_response' => $uploadResult['data'],
                                        'file_name' => $fileName,
                                    ]);

                                    // Check if pabrik_id exists before bulk insert
                                    if (empty($record->pabrik_id)) {
                                        $bahanNotes[] = "Gagal bulk insert bahan: Pabrik ID tidak ditemukan. Silakan buat factory terlebih dahulu.";
                                        Log::warning('Cannot bulk insert bahan - no pabrik_id', [
                                            'submission_id' => $record->submission_id,
                                            'id_reg' => $idReg,
                                        ]);
                                    } else {
                                        // Bulk insert bahan
                                        $bulkInsertBahanResult = $service->bulkInsertBahan(
                                            idReg: $idReg,
                                            idPabrik: $record->pabrik_id,
                                            validatedBahan: $validatedBahan
                                        );

                                        if ($bulkInsertBahanResult['success']) {
                                            Log::info('Bahan Bulk Inserted Successfully', [
                                                'submission_id' => $record->submission_id,
                                                'bulk_insert_response' => $bulkInsertBahanResult['data'],
                                                'bahan_count' => count($validatedBahan),
                                            ]);
                                        } else {
                                            $bahanNotes[] = "Gagal bulk insert bahan: {$bulkInsertBahanResult['message']} (Status: {$bulkInsertBahanResult['status']})";
                                        }
                                    }
                                } else {
                                    $errors = $uploadResult['data']['data']['errors']['list_error'] ?? [];
                                    $bahanNotes[] = "Gagal Upload Daftar Bahan : " . implode(', ', $errors);
                                }
                            } else {
                                $bahanNotes[] = "File Daftar Bahan tidak ditemukan: {$fileName}";
                            }
                        } else {
                            Log::info('No Daftar Bahan file to upload', [
                                'submission_id' => $record->submission_id,
                            ]);
                        }

                        // Upload daftar produk file if exists
                        if (!empty($daftarNamaAnswer)) {
                            // Handle different answer formats
                            $filePathProduk = null;
                            $fileNameProduk = null;

                            if (is_array($daftarNamaAnswer)) {
                                // JotForm file upload format
                                if (isset($daftarNamaAnswer[0])) {
                                    $firstFile = $daftarNamaAnswer[0];
                                    if (is_string($firstFile)) {
                                        // Simple path string
                                        $filePathProduk = storage_path('app/public/' . $firstFile);
                                        $fileNameProduk = basename($firstFile);
                                    } elseif (is_array($firstFile)) {
                                        // Array with file details
                                        $filePathProduk = storage_path('app/public/' . ($firstFile['file'] ?? ''));
                                        $fileNameProduk = $firstFile['name'] ?? basename($filePathProduk);
                                    }
                                }
                            } elseif (is_string($daftarNamaAnswer)) {
                                // Direct path string
                                $filePathProduk = storage_path('app/public/' . $daftarNamaAnswer);
                                $fileNameProduk = basename($daftarNamaAnswer);
                            }

                            if (!empty($filePathProduk) && !empty($fileNameProduk) && file_exists($filePathProduk)) {
                                // Edit Condition
                                if (!$isNew) {
                                    $this->resetProduk($record, $service, $idReg, $bahanNotes);
                                }
                                // End:Edit Condition

                                $uploadResultProduk = $service->uploadDaftarProduk($filePathProduk, $fileNameProduk);

                                if ($uploadResultProduk['success']) {
                                    Log::info('Daftar Produk File Uploaded Successfully', [
                                        'submission_id' => $record->submission_id,
                                        'upload_response' => $uploadResultProduk['data'],
                                        'file_name' => $fileNameProduk,
                                    ]);

                                    // Extract validated_produk from response
                                    $validatedProducts = $uploadResultProduk['data']['data']['validated_produk'] ?? [];

                                    if (!empty($validatedProducts)) {
                                        Log::info('Validated Products Retrieved', [
                                            'submission_id' => $record->submission_id,
                                            'total_products' => count($validatedProducts),
                                        ]);

                                        // Extract and format products for bulk insert
                                        $productsToInsert = [];
                                        foreach ($validatedProducts as $product) {
                                            if (isset($product['HalalCertificateRegulerProduk']['reg_prod_name'])) {
                                                $productsToInsert[] = [
                                                    'reg_prod_name' => $product['HalalCertificateRegulerProduk']['reg_prod_name']
                                                ];
                                            }
                                        }

                                        if (!empty($productsToInsert)) {
                                            Log::info('Products Formatted for Bulk Insert', [
                                                'submission_id' => $record->submission_id,
                                                'products_count' => count($productsToInsert),
                                                'products' => $productsToInsert,
                                            ]);

                                            // Bulk insert products
                                            $bulkInsertResult = $service->bulkInsertProducts($idReg, $productsToInsert);
                                            // check $bulkInsertResult
                                            if ($bulkInsertResult['success']) {
                                                Log::info('Products Bulk Inserted Successfully', [
                                                    'submission_id' => $record->submission_id,
                                                    'bulk_insert_response' => $bulkInsertResult['data'],
                                                    'products_count' => count($productsToInsert),
                                                ]);
                                            } else {
                                                $bahanNotes[] = "Gagal bulk insert produk: {$bulkInsertResult['message']} (Status: {$bulkInsertResult['status']})";
                                            }
                                        } else {
                                            $bahanNotes[] = "Tidak ada produk untuk diinsert setelah formatting";
                                        }
                                    } else {
                                        $bahanNotes[] = "Tidak ada validated produk yang ditemukan dalam response";
                                    }
                                } else {
                                    $bahanNotes[] = "Gagal upload Daftar Produk: {$uploadResultProduk['message']} (Status: {$uploadResultProduk['status']})";
                                }
                            } else {
                                $bahanNotes[] = "File Daftar Produk tidak ditemukan: {$fileNameProduk}";
                            }
                        } else {
                            Log::info('No Daftar Produk file to upload', [
                                'submission_id' => $record->submission_id,
                            ]);
                        }

                        // Process catatan pembelian if exists (di eksekusi terpisah, tidak bergantung bulk insert)
                        // if (!empty($catatanPembelianAnswer)) {
                        //     Log::info('Processing Catatan Pembelian', [
                        //         'submission_id' => $record->submission_id,
                        //         'catatan_field' => $catatanPembelianAnswer,
                        //     ]);

                        //     $catatanPembelianResult = $service->processCatatanPembelian(
                        //         idReg: $idReg,
                        //         catatanPembelianField: $catatanPembelianAnswer,
                        //         storageBasePath: storage_path('app/public')
                        //     );

                        //     if ($catatanPembelianResult['success']) {
                        //         Log::info('Catatan Pembelian Processed Successfully', [
                        //             'submission_id' => $record->submission_id,
                        //             'catatan_data' => $catatanPembelianResult['data'],
                        //         ]);
                        //     } else {
                        //         $bahanNotes[] = "Gagal process Catatan Pembelian: {$catatanPembelianResult['message']} (Status: {$catatanPembelianResult['status']})";
                        //     }
                        // } else {
                        //     Log::info('No Catatan Pembelian to process', [
                        //         'submission_id' => $record->submission_id,
                        //     ]);
                        // }

                        // Process form pemeriksaan bahan masuk if exists
                        // if (!empty($formPemeriksaanAnswer)) {
                        //     Log::info('Processing Form Pemeriksaan Bahan Masuk', [
                        //         'submission_id' => $record->submission_id,
                        //         'form_pemeriksaan_field' => $formPemeriksaanAnswer,
                        //     ]);

                        //     // Extract text and clean it for nama formulir
                        //     $formPemeriksaanText = $formPemeriksaanAnswer['text'] ?? '';
                        //     $namaFormulir = preg_replace('/\s*\(Cek Nama\/Merek Sesuai Daftar\)\.\s*\(Upload file dalam format[^)]*\)/', '', $formPemeriksaanText);
                        //     $namaFormulir = trim($namaFormulir);

                        //     // Extract file path from answer
                        //     $formPemeriksaanFilePath = $formPemeriksaanAnswer['answer'][0] ?? '';

                        //     if (!empty($formPemeriksaanFilePath)) {
                        //         // Get filename from path
                        //         $formPemeriksaanFileName = basename($formPemeriksaanFilePath);

                        //         // Build full file path
                        //         $formPemeriksaanFullPath = storage_path('app/public/' . $formPemeriksaanFilePath);

                        //         if (file_exists($formPemeriksaanFullPath)) {
                        //             // Upload form pemeriksaan document
                        //             $formPemeriksaanUploadResult = $service->uploadSubmissionDocument(
                        //                 idReg: $idReg,
                        //                 filePath: $formPemeriksaanFullPath,
                        //                 fileName: $formPemeriksaanFileName,
                        //                 type: 'produk'
                        //             );

                        //             if ($formPemeriksaanUploadResult['success']) {
                        //                 Log::info('Form Pemeriksaan Bahan Masuk Uploaded Successfully', [
                        //                     'submission_id' => $record->submission_id,
                        //                     'upload_response' => $formPemeriksaanUploadResult['data'],
                        //                     'file_name' => $formPemeriksaanFileName,
                        //                 ]);

                        //                 // Extract file_url from response
                        //                 $formPemeriksaanFileUrl = $formPemeriksaanUploadResult['data']['data']['file_url'] ?? null;

                        //                 if ($formPemeriksaanFileUrl) {
                        //                     // Add formulir
                        //                     $addFormulirResult = $service->addFormulir(
                        //                         idReg: $idReg,
                        //                         fileDok: $formPemeriksaanFileUrl,
                        //                         nama: $namaFormulir
                        //                     );

                        //                     if ($addFormulirResult['success']) {
                        //                         Log::info('Formulir Added Successfully', [
                        //                             'submission_id' => $record->submission_id,
                        //                             'add_formulir_response' => $addFormulirResult['data'],
                        //                             'nama' => $namaFormulir,
                        //                             'file_url' => $formPemeriksaanFileUrl,
                        //                         ]);
                        //                     } else {
                        //                         $bahanNotes[] = "Gagal add formulir '{$namaFormulir}': {$addFormulirResult['message']} (Status: {$addFormulirResult['status']})";
                        //                     }
                        //                 } else {
                        //                     $bahanNotes[] = "file_url tidak ditemukan dalam response Form Pemeriksaan";
                        //                 }
                        //             } else {
                        //                 $bahanNotes[] = "Gagal upload Form Pemeriksaan Bahan Masuk: {$formPemeriksaanUploadResult['message']} (Status: {$formPemeriksaanUploadResult['status']})";
                        //             }
                        //         } else {
                        //             $bahanNotes[] = "File Form Pemeriksaan Bahan Masuk tidak ditemukan: {$formPemeriksaanFileName}";
                        //         }
                        //     } else {
                        //         $bahanNotes[] = "File path Form Pemeriksaan Bahan Masuk tidak ditemukan dalam answer";
                        //     }
                        // } else {
                        //     Log::info('No Form Pemeriksaan Bahan Masuk to process', [
                        //         'submission_id' => $record->submission_id,
                        //     ]);
                        // }

                        // Update bahan column with status and notes
                        $bahanStatus = empty($bahanNotes) ? 'done' : 'failed';
                        $record->update([
                            'bahan' => [
                                'status' => $bahanStatus,
                                'notes' => $bahanNotes,
                            ],
                        ]);
                    }
                    # End-Section : Data Bahan

                    # Section : Proses
                    if ($this->shouldProcessSection('proses')) {
                        $prosesNotes = []; // Initialize notes array

                        // Diagram Alur Proses Produksi
                        if (!empty($diagramAlirAnswer)) {
                            Log::info('Processing Diagram Alir Proses Produksi', [
                                'submission_id' => $record->submission_id,
                                'diagram_alir_field' => $diagramAlirAnswer,
                            ]);

                            // Extract text and clean it for nama produk
                            $diagramAlirText = $diagramAlirAnswer['text'] ?? '';
                            $namaProdukDiagram = preg_replace('/\s*\(Upload file dalam format[^)]*\)/', '', $diagramAlirText);
                            $namaProdukDiagram = trim($namaProdukDiagram);

                            // Extract file path from answer
                            $diagramAlirFilePath = $diagramAlirAnswer['answer'][0] ?? '';

                            if (!empty($diagramAlirFilePath)) {
                                // Get filename from path
                                $diagramAlirFileName = basename($diagramAlirFilePath);

                                // Build full file path
                                $diagramAlirFullPath = storage_path('app/public/' . $diagramAlirFilePath);

                                if (file_exists($diagramAlirFullPath)) {
                                    if (!$isNew) {
                                        $this->resetDiagramAlur($record, $service, $idReg, $prosesNotes);
                                    }

                                    // Upload diagram alir document
                                    $diagramAlirUploadResult = $service->uploadSubmissionDocument(
                                        idReg: $idReg,
                                        filePath: $diagramAlirFullPath,
                                        fileName: $diagramAlirFileName,
                                        type: 'produk'
                                    );

                                    if ($diagramAlirUploadResult['success']) {
                                        Log::info('Diagram Alir Proses Produksi Uploaded Successfully', [
                                            'submission_id' => $record->submission_id,
                                            'upload_response' => $diagramAlirUploadResult['data'],
                                            'file_name' => $diagramAlirFileName,
                                        ]);

                                        // Extract file_url from response
                                        $diagramAlirFileUrl = $diagramAlirUploadResult['data']['data']['file_url'] ?? null;

                                        if ($diagramAlirFileUrl) {
                                            // Add diagram alur
                                            $addDiagramAlurResult = $service->addDiagramAlur(
                                                idReg: $idReg,
                                                fileDok: $diagramAlirFileUrl,
                                                namaProduk: $namaProdukDiagram
                                            );

                                            if ($addDiagramAlurResult['success']) {
                                                Log::info('Diagram Alur Added Successfully', [
                                                    'submission_id' => $record->submission_id,
                                                    'add_diagram_alur_response' => $addDiagramAlurResult['data'],
                                                    'nama_produk' => $namaProdukDiagram,
                                                    'file_url' => $diagramAlirFileUrl,
                                                ]);
                                            } else {
                                                $prosesNotes[] = "Gagal menambahkan Diagram Alur: {$addDiagramAlurResult['message']} (Status: {$addDiagramAlurResult['status']})";
                                                Log::warning('Failed to add diagram alur', [
                                                    'submission_id' => $record->submission_id,
                                                    'message' => $addDiagramAlurResult['message'],
                                                    'status' => $addDiagramAlurResult['status'],
                                                ]);
                                            }
                                        } else {
                                            $prosesNotes[] = "file_url tidak ditemukan dalam response upload Diagram Alir";
                                            Log::warning('file_url not found in Diagram Alir upload response', [
                                                'submission_id' => $record->submission_id,
                                            ]);
                                        }
                                    } else {
                                        $prosesNotes[] = "Gagal upload Diagram Alir Proses Produksi: {$diagramAlirUploadResult['message']} (Status: {$diagramAlirUploadResult['status']})";
                                        Log::warning('Failed to upload Diagram Alir Proses Produksi', [
                                            'submission_id' => $record->submission_id,
                                            'message' => $diagramAlirUploadResult['message'],
                                            'status' => $diagramAlirUploadResult['status'],
                                        ]);
                                    }
                                } else {
                                    $prosesNotes[] = "File Diagram Alir Proses Produksi tidak ditemukan: {$diagramAlirFileName}";
                                    Log::warning('Diagram Alir Proses Produksi file not found', [
                                        'submission_id' => $record->submission_id,
                                        'file_path' => $diagramAlirFullPath,
                                        'file_name' => $diagramAlirFileName,
                                    ]);
                                }
                            } else {
                                $prosesNotes[] = "File path Diagram Alir Proses Produksi tidak ditemukan dalam answer";
                                Log::warning('Diagram Alir Proses Produksi file path not found in answer', [
                                    'submission_id' => $record->submission_id,
                                ]);
                            }
                        } else {
                            Log::info('No Diagram Alir Proses Produksi to process', [
                                'submission_id' => $record->submission_id,
                            ]);
                        }

                        // Layout / Denah Ruang Produksi
                        if (!empty($fotoLokasidenahAnswer)) {
                            Log::info('Processing Foto Lokasi Denah', [
                                'submission_id' => $record->submission_id,
                                'foto_lokasi_denah_field' => $fotoLokasidenahAnswer,
                            ]);

                            // Extract file path from answer
                            $fotoLokasidenahFilePath = is_array($fotoLokasidenahAnswer) && isset($fotoLokasidenahAnswer[0])
                                ? $fotoLokasidenahAnswer[0]
                                : $fotoLokasidenahAnswer;

                            if (!empty($fotoLokasidenahFilePath)) {
                                // Get filename from path
                                $fotoLokasidenahFileName = basename($fotoLokasidenahFilePath);

                                // Build full file path
                                $fotoLokasidenahFullPath = storage_path('app/public/' . $fotoLokasidenahFilePath);

                                if (file_exists($fotoLokasidenahFullPath)) {
                                    if (!$isNew) {
                                        $this->resetLayout($record, $service, $idReg, $prosesNotes);
                                    }

                                    // Upload foto lokasi denah document
                                    $fotoLokasidenahUploadResult = $service->uploadSubmissionDocument(
                                        idReg: $idReg,
                                        filePath: $fotoLokasidenahFullPath,
                                        fileName: $fotoLokasidenahFileName,
                                        type: 'produk'
                                    );

                                    if ($fotoLokasidenahUploadResult['success']) {
                                        Log::info('Foto Lokasi Denah Uploaded Successfully', [
                                            'submission_id' => $record->submission_id,
                                            'upload_response' => $fotoLokasidenahUploadResult['data'],
                                            'file_name' => $fotoLokasidenahFileName,
                                        ]);

                                        // Extract file_url from response
                                        $fileUrl = $fotoLokasidenahUploadResult['data']['data']['file_url'] ?? null;

                                        if ($fileUrl) {
                                            // Add layout
                                            $addLayoutResult = $service->addLayout(
                                                idReg: $idReg,
                                                fileLayout: $fileUrl,
                                                idPabrik: $record->pabrik_id ?? null,
                                                namaPabrik: $namaPerusahaanAnswer
                                            );

                                            if ($addLayoutResult['success']) {
                                                Log::info('Layout Added Successfully', [
                                                    'submission_id' => $record->submission_id,
                                                    'add_layout_response' => $addLayoutResult['data'],
                                                    'file_url' => $fileUrl,
                                                    'id_pabrik' => $record->pabrik_id ?? null,
                                                    'nama_pabrik' => $namaPerusahaanAnswer,
                                                ]);
                                            } else {
                                                $prosesNotes[] = "Gagal menambahkan Layout: {$addLayoutResult['message']} (Status: {$addLayoutResult['status']})";
                                                Log::warning('Failed to add layout', [
                                                    'submission_id' => $record->submission_id,
                                                    'message' => $addLayoutResult['message'],
                                                    'status' => $addLayoutResult['status'],
                                                ]);
                                            }
                                        } else {
                                            $prosesNotes[] = "file_url tidak ditemukan dalam response upload Foto Lokasi Denah";
                                            Log::warning('file_url not found in Foto Lokasi Denah upload response', [
                                                'submission_id' => $record->submission_id,
                                            ]);
                                        }
                                    } else {
                                        $prosesNotes[] = "Gagal upload Foto Lokasi Denah: {$fotoLokasidenahUploadResult['message']} (Status: {$fotoLokasidenahUploadResult['status']})";
                                        Log::warning('Failed to upload Foto Lokasi Denah', [
                                            'submission_id' => $record->submission_id,
                                            'message' => $fotoLokasidenahUploadResult['message'],
                                            'status' => $fotoLokasidenahUploadResult['status'],
                                        ]);
                                    }
                                } else {
                                    $prosesNotes[] = "File Foto Lokasi Denah tidak ditemukan: {$fotoLokasidenahFileName}";
                                    Log::warning('Foto Lokasi Denah file not found', [
                                        'submission_id' => $record->submission_id,
                                        'file_path' => $fotoLokasidenahFullPath,
                                        'file_name' => $fotoLokasidenahFileName,
                                    ]);
                                }
                            } else {
                                $prosesNotes[] = "File path Foto Lokasi Denah tidak ditemukan dalam answer";
                                Log::warning('Foto Lokasi Denah file path not found in answer', [
                                    'submission_id' => $record->submission_id,
                                ]);
                            }
                        } else {
                            Log::info('No Foto Lokasi Denah to process', [
                                'submission_id' => $record->submission_id,
                            ]);
                        }
                        // End:Layout / Denah Ruang Produksi

                        // Update proses column with status and notes
                        $prosesStatus = empty($prosesNotes) ? 'done' : 'failed';
                        $record->update([
                            'proses' => [
                                'status' => $prosesStatus,
                                'notes' => $prosesNotes,
                            ],
                        ]);
                    }
                    # End-Section : Proses

                    // Section : Produk
                    if ($this->shouldProcessSection('produk')) {
                        $produkNotes = []; // Initialize notes array
                        Log::info('Processing Produk Section', [
                            'submission_id' => $record->submission_id,
                        ]);

                        // Check if pabrik_id exists
                        if (empty($record->pabrik_id)) {
                            $produkNotes[] = "Pabrik ID tidak ditemukan. Silakan buat factory terlebih dahulu.";
                            Log::warning('Pabrik ID not found', [
                                'submission_id' => $record->submission_id,
                            ]);
                        } else {
                            // Get product list
                            $getProductListResult = $service->getProductList($idReg);

                            if ($getProductListResult['success']) {
                                Log::info('Product List Retrieved Successfully', [
                                    'submission_id' => $record->submission_id,
                                    'product_list_response' => $getProductListResult['data'],
                                ]);

                                // Extract product IDs from response
                                $productListData = $getProductListResult['data']['data'] ?? [];
                                $productIds = [];

                                // Assuming the response structure contains products with IDs
                                if (!empty($productListData)) {
                                    foreach ($productListData as $product) {
                                        if (isset($product['id'])) {
                                            $productIds[] = $product['id'];
                                        }
                                    }
                                }

                                if (!empty($productIds)) {
                                    Log::info('Product IDs Extracted', [
                                        'submission_id' => $record->submission_id,
                                        'product_count' => count($productIds),
                                        'product_ids' => $productIds,
                                    ]);

                                    // Reset existing produk data if not new
                                    if (!$isNew) {
                                        $this->resetProdukForTabProduk($record, $service, $idReg, $produkNotes);
                                    }

                                    // Get pabrik_id from pelaku usaha detail endpoint
                                    $pabrikId = $this->getFactoryIdFromDetail($service, $idReg, $namaPerusahaanAnswer);
                                    if ($pabrikId) {
                                        // Update record with pabrik_id if found
                                        $record->update(['pabrik_id' => $pabrikId]);
                                        Log::info('Factory ID found from detail endpoint', [
                                            'submission_id' => $record->submission_id,
                                            'id_reg' => $idReg,
                                            'pabrik_id' => $pabrikId,
                                            'factory_name' => $namaPerusahaanAnswer,
                                        ]);
                                    } else {
                                        $produkNotes[] = "Factory ID tidak ditemukan untuk nama: {$namaPerusahaanAnswer}";
                                        Log::warning('Factory ID not found from detail endpoint', [
                                            'submission_id' => $record->submission_id,
                                            'id_reg' => $idReg,
                                            'factory_name' => $namaPerusahaanAnswer,
                                        ]);
                                    }

                                    // Create products
                                    $createProductsResult = $service->createProducts(
                                        idReg: $idReg,
                                        idPabrik: $pabrikId ?? $record->pabrik_id ?? '',
                                        productIds: $productIds
                                    );

                                    if ($createProductsResult['success']) {
                                        Log::info('Products Created Successfully', [
                                            'submission_id' => $record->submission_id,
                                            'create_products_response' => $createProductsResult['data'],
                                            'product_count' => count($productIds),
                                        ]);
                                    } else {
                                        $produkNotes[] = "Gagal create products: {$createProductsResult['message']} (Status: {$createProductsResult['status']})";
                                        Log::warning('Failed to create products', [
                                            'submission_id' => $record->submission_id,
                                            'message' => $createProductsResult['message'],
                                            'status' => $createProductsResult['status'],
                                        ]);
                                    }
                                } else {
                                    $produkNotes[] = "Tidak ada product IDs yang ditemukan dalam response product list";
                                    Log::warning('No product IDs found in product list response', [
                                        'submission_id' => $record->submission_id,
                                        'response_data' => $productListData,
                                    ]);
                                }
                            } else {
                                $produkNotes[] = "Gagal get product list: {$getProductListResult['message']} (Status: {$getProductListResult['status']})";
                                Log::warning('Failed to get product list', [
                                    'submission_id' => $record->submission_id,
                                    'message' => $getProductListResult['message'],
                                    'status' => $getProductListResult['status'],
                                ]);
                            }

                            // Update produk column with status and notes
                            $produkStatus = empty($produkNotes) ? 'done' : 'failed';
                            $record->update([
                                'produk' => [
                                    'status' => $produkStatus,
                                    'notes' => $produkNotes,
                                ],
                            ]);
                        } // End of else block for pabrik_id check
                    }
                    // End-Section : Produk

                    # Section : Pemantauan dan Evaluasi
                    if ($this->shouldProcessSection('pemantauan_evaluasi')) {
                        $pemantauanEvaluasiNotes = []; // Initialize notes array

                        // Reset existing dokumen evaluasi data if not new
                        if (!$isNew) {
                            $this->resetDokumenEvaluasi($record, $service, $idReg, $pemantauanEvaluasiNotes);
                        }

                        // Update dokumen Lainnya (Kebijakan Halal, Foto Kebijakan, Matriks Produk)
                        $dokumenLainnyaFields = [
                            'kebijakanHalal' => $kebijakanHalalAnswer,
                            'fotoKebijakan152' => $fotoKebijakanAnswer,
                            'matriksProduk' => $matriksProdukAnswer,
                        ];

                        foreach ($dokumenLainnyaFields as $fieldName => $dokumenField) {
                            if (!empty($dokumenField)) {
                                Log::info('Processing Dokumen Lainnya', [
                                    'submission_id' => $record->submission_id,
                                    'field_name' => $fieldName,
                                    'dokumen_field' => $dokumenField,
                                ]);

                                $dokumenLainnyaResult = $service->processDokumenLainnya(
                                    idReg: $idReg,
                                    dokumenField: $dokumenField,
                                    storageBasePath: storage_path('app/public')
                                );

                                if ($dokumenLainnyaResult['success']) {
                                    Log::info('Dokumen Lainnya Processed Successfully', [
                                        'submission_id' => $record->submission_id,
                                        'field_name' => $fieldName,
                                        'dokumen_data' => $dokumenLainnyaResult['data'],
                                    ]);
                                } else {
                                    $pemantauanEvaluasiNotes[] = "Gagal process Dokumen Lainnya ({$fieldName}): {$dokumenLainnyaResult['message']} (Status: {$dokumenLainnyaResult['status']})";
                                    Log::warning('Failed to process Dokumen Lainnya', [
                                        'submission_id' => $record->submission_id,
                                        'field_name' => $fieldName,
                                        'message' => $dokumenLainnyaResult['message'],
                                        'status' => $dokumenLainnyaResult['status'],
                                    ]);
                                }
                            } else {
                                Log::info('No Dokumen Lainnya to process', [
                                    'submission_id' => $record->submission_id,
                                    'field_name' => $fieldName,
                                ]);
                            }
                        }

                        // Upload Tanda Tangan
                        $ttdPj = null; // File URL for TTD Penanggung Jawab
                        $ttdPh = null; // File URL for TTD Penyelia Halal
                        $namaPenyelia = ''; // Nama Penyelia

                        // Upload TTD Penanggung Jawab
                        if (!empty($tandaTanganAnswer)) {
                            Log::info('Processing Tanda Tangan Penanggung Jawab', [
                                'submission_id' => $record->submission_id,
                                'tanda_tangan_field' => $tandaTanganAnswer,
                            ]);

                            $tandaTanganFilePath = $tandaTanganAnswer['answer'][0] ?? '';

                            if (!empty($tandaTanganFilePath)) {
                                $tandaTanganFileName = basename($tandaTanganFilePath);
                                $tandaTanganFullPath = storage_path('app/public/' . $tandaTanganFilePath);

                                if (file_exists($tandaTanganFullPath)) {
                                    $tandaTanganUploadResult = $service->uploadSubmissionDocument(
                                        idReg: $idReg,
                                        filePath: $tandaTanganFullPath,
                                        fileName: $tandaTanganFileName,
                                        type: 'produk'
                                    );

                                    if ($tandaTanganUploadResult['success']) {
                                        $ttdPj = $tandaTanganUploadResult['data']['data']['file_url'] ?? null;
                                        Log::info('Tanda Tangan Penanggung Jawab Uploaded Successfully', [
                                            'submission_id' => $record->submission_id,
                                            'file_url' => $ttdPj,
                                        ]);
                                    } else {
                                        $pemantauanEvaluasiNotes[] = "Gagal upload Tanda Tangan Penanggung Jawab: {$tandaTanganUploadResult['message']} (Status: {$tandaTanganUploadResult['status']})";
                                        Log::warning('Failed to upload Tanda Tangan Penanggung Jawab', [
                                            'submission_id' => $record->submission_id,
                                            'message' => $tandaTanganUploadResult['message'],
                                            'status' => $tandaTanganUploadResult['status'],
                                        ]);
                                    }
                                } else {
                                    $pemantauanEvaluasiNotes[] = "File Tanda Tangan Penanggung Jawab tidak ditemukan: {$tandaTanganFileName}";
                                    Log::warning('Tanda Tangan Penanggung Jawab file not found', [
                                        'submission_id' => $record->submission_id,
                                        'file_path' => $tandaTanganFullPath,
                                    ]);
                                }
                            }
                        } else {
                            Log::info('No Tanda Tangan Penanggung Jawab to process', [
                                'submission_id' => $record->submission_id,
                            ]);
                        }

                        // Upload TTD Penyelia Halal
                        if (!empty($buktiPelaksanaanAnswer)) {
                            Log::info('Processing Tanda Tangan Penyelia Halal', [
                                'submission_id' => $record->submission_id,
                                'bukti_pelaksanaan_field' => $buktiPelaksanaanAnswer,
                            ]);

                            $buktiPelaksanaanFilePath = $buktiPelaksanaanAnswer['answer'][0] ?? '';

                            if (!empty($buktiPelaksanaanFilePath)) {
                                $buktiPelaksanaanFileName = basename($buktiPelaksanaanFilePath);
                                $buktiPelaksanaanFullPath = storage_path('app/public/' . $buktiPelaksanaanFilePath);

                                if (file_exists($buktiPelaksanaanFullPath)) {
                                    $buktiPelaksanaanUploadResult = $service->uploadSubmissionDocument(
                                        idReg: $idReg,
                                        filePath: $buktiPelaksanaanFullPath,
                                        fileName: $buktiPelaksanaanFileName,
                                        type: 'produk'
                                    );

                                    if ($buktiPelaksanaanUploadResult['success']) {
                                        $ttdPh = $buktiPelaksanaanUploadResult['data']['data']['file_url'] ?? null;
                                        Log::info('Tanda Tangan Penyelia Halal Uploaded Successfully', [
                                            'submission_id' => $record->submission_id,
                                            'file_url' => $ttdPh,
                                        ]);
                                    } else {
                                        $pemantauanEvaluasiNotes[] = "Gagal upload Tanda Tangan Penyelia Halal: {$buktiPelaksanaanUploadResult['message']} (Status: {$buktiPelaksanaanUploadResult['status']})";
                                        Log::warning('Failed to upload Tanda Tangan Penyelia Halal', [
                                            'submission_id' => $record->submission_id,
                                            'message' => $buktiPelaksanaanUploadResult['message'],
                                            'status' => $buktiPelaksanaanUploadResult['status'],
                                        ]);
                                    }
                                } else {
                                    $pemantauanEvaluasiNotes[] = "File Tanda Tangan Penyelia Halal tidak ditemukan: {$buktiPelaksanaanFileName}";
                                    Log::warning('Tanda Tangan Penyelia Halal file not found', [
                                        'submission_id' => $record->submission_id,
                                        'file_path' => $buktiPelaksanaanFullPath,
                                    ]);
                                }
                            }
                        } else {
                            Log::info('No Tanda Tangan Penyelia Halal to process', [
                                'submission_id' => $record->submission_id,
                            ]);
                        }

                        // Get nama penyelia
                        if (!empty($namaPenyeliaAnswer) && is_array($namaPenyeliaAnswer)) {
                            $first = $namaPenyeliaAnswer['first'] ?? '';
                            $last = $namaPenyeliaAnswer['last'] ?? '';
                            $namaPenyelia = trim($first . ' ' . $last);
                        }

                        // Add TTD jika kedua file berhasil diupload
                        if (!empty($ttdPj) && !empty($ttdPh)) {
                            if (!$isNew) {
                                $this->resetTTD($record, $service, $idReg, $pemantauanEvaluasiNotes);
                            }

                            Log::info('Adding TTD', [
                                'submission_id' => $record->submission_id,
                                'nama_penyelia' => $namaPenyelia,
                                'ttd_pj' => $ttdPj,
                                'ttd_ph' => $ttdPh,
                            ]);

                            $addTTDResult = $service->addTTD(
                                idReg: $idReg,
                                namaPenyelia: $namaPenyelia,
                                ttdPj: $ttdPj,
                                ttdPh: $ttdPh
                            );

                            if ($addTTDResult['success']) {
                                Log::info('TTD Added Successfully', [
                                    'submission_id' => $record->submission_id,
                                    'add_ttd_response' => $addTTDResult['data'],
                                ]);
                            } else {
                                $pemantauanEvaluasiNotes[] = "Gagal add TTD: {$addTTDResult['message']} (Status: {$addTTDResult['status']})";
                                Log::warning('Failed to add TTD', [
                                    'submission_id' => $record->submission_id,
                                    'message' => $addTTDResult['message'],
                                    'status' => $addTTDResult['status'],
                                ]);
                            }
                        } else {
                            $pemantauanEvaluasiNotes[] = "Tidak dapat add TTD - satu atau kedua file tidak berhasil diupload (TTD PJ: " . ($ttdPj ? 'uploaded' : 'not uploaded') . ", TTD PH: " . ($ttdPh ? 'uploaded' : 'not uploaded') . ")";
                            Log::warning('Cannot add TTD - one or both files not uploaded', [
                                'submission_id' => $record->submission_id,
                                'ttd_pj' => $ttdPj ? 'uploaded' : 'not uploaded',
                                'ttd_ph' => $ttdPh ? 'uploaded' : 'not uploaded',
                            ]);
                        }
                        // End-Section : Pemantauan dan Evaluasi

                        // Update pemantauan_evaluasi column with status and notes
                        $pemantauanEvaluasiStatus = empty($pemantauanEvaluasiNotes) ? 'done' : 'failed';
                        $record->update([
                            'pemantauan_evaluasi' => [
                                'status' => $pemantauanEvaluasiStatus,
                                'notes' => $pemantauanEvaluasiNotes,
                            ],
                        ]);
                    }
                    // End-Section : Pemantauan dan Evaluasi

                    if (empty($idReg)) {
                        Log::warning('ID Reg not found', [
                            'submission_id' => $record->submission_id,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Update status_submit to ERROR
                    $record->update(['status_submit' => 'ERROR']);
                    $failedCount++;

                    $errors[] = [
                        'submission_id' => $record->submission_id,
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Exception when submitting to halal.go.id', [
                        'submission_id' => $record->submission_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Submit to halal.go.id completed', [
                'total' => $records->count(),
                'success' => $successCount,
                'failed' => $failedCount,
                'errors' => count($errors),
            ]);
        } catch (\Exception $e) {
            Log::error('Submit to halal.go.id job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Check if the error is unauthorized token
        $errorMessage = $exception->getMessage();
        $isUnauthorizedToken = false;

        // Check for various unauthorized token error patterns
        if (
            str_contains($errorMessage, '400006') ||
            str_contains($errorMessage, 'user token is unauthorized') ||
            str_contains($errorMessage, '401') ||
            str_contains($errorMessage, 'unauthorized')
        ) {
            $isUnauthorizedToken = true;
        }

        if ($isUnauthorizedToken && $this->userId) {
            // Send database notification to user about unauthorized token
            $user = \App\Models\User::find($this->userId);
            if ($user) {
                $user->notify(new HalalGoIdTokenExpired());
            }

            Log::error('Unauthorized token error detected', [
                'user_id' => $this->userId,
                'error' => $errorMessage,
            ]);
        } else {
            Log::error('Submit to halal.go.id job failed', [
                'user_id' => $this->userId,
                'error' => $errorMessage,
            ]);
        }
    }

    /**
     * Create factory for pelaku usaha
     *
     * @param \App\Models\JotformSync $record
     * @param \App\Services\HalalGoIdService $service
     * @param string $idReg
     * @param string $namaPerusahaanAnswer
     * @param array $alamatPerusahaanAnswer
     * @param string|null $statusPerusahaanAnswer
     * @return array ['success' => bool, 'message' => string]
     */
    protected function createFactory($record, $service, $profileId, $idReg, $namaPerusahaanAnswer, $alamatPerusahaanAnswer, $statusPerusahaanAnswer): array
    {
        Log::info('Creating new factory', [
            'submission_id' => $record->submission_id,
            'nama_perusahaan' => $namaPerusahaanAnswer,
        ]);

        // Get province code
        $provinceCode = null;
        $provincesResult = $service->getProvinces();
        if ($provincesResult['success']) {
            $provinces = $provincesResult['data'] ?? [];
            foreach ($provinces as $province) {
                if (strcasecmp($province['name'], $alamatPerusahaanAnswer['state']) === 0) {
                    $provinceCode = $province['code'];
                    break;
                }
            }
        }

        if (empty($provinceCode)) {
            Log::warning('Province code not found', [
                'submission_id' => $record->submission_id,
                'state' => $alamatPerusahaanAnswer['state'],
            ]);
        }

        // Get district/city code
        $cityCode = null;
        if (!empty($provinceCode)) {
            $districtsResult = $service->getDistricts($provinceCode);
            if ($districtsResult['success']) {
                $districts = $districtsResult['data'] ?? [];
                foreach ($districts as $district) {
                    if (strcasecmp($district['name'], $alamatPerusahaanAnswer['city']) === 0) {
                        $cityCode = $district['code'];
                        break;
                    }
                }
            }

            if (empty($cityCode)) {
                Log::warning('City code not found', [
                    'submission_id' => $record->submission_id,
                    'city' => $alamatPerusahaanAnswer['city'],
                    'province_code' => $provinceCode,
                ]);
            }
        }

        // Get factory status code
        $statusCode = null;
        if (!empty($statusPerusahaanAnswer)) {
            $statusCodesResult = $service->getFactoryStatusCodes();
            if ($statusCodesResult['success']) {
                $statusCodes = $statusCodesResult['data'] ?? [];
                foreach ($statusCodes as $status) {
                    if (strcasecmp($status['name'], $statusPerusahaanAnswer) === 0) {
                        $statusCode = $status['code'];
                        break;
                    }
                }
            }

            if (empty($statusCode)) {
                Log::warning('Factory status code not found', [
                    'submission_id' => $record->submission_id,
                    'status' => $statusPerusahaanAnswer,
                ]);
            }
        }

        // Prepare factory data
        $factoryData = [
            'name' => $namaPerusahaanAnswer,
            'address' => $alamatPerusahaanAnswer['addr_line1'] ?? '',
            'city' => $cityCode ?? '',
            'province' => $provinceCode ?? '',
            'country' => 'Indonesia',
            'zip_code' => $alamatPerusahaanAnswer['postal'] ?? '',
            'status' => $statusCode ?? '',
        ];

        // Add factory
        if (!empty($factoryData['city']) && !empty($factoryData['province']) && !empty($factoryData['status'])) {
            $addFactoryResult = $service->addFactory($profileId, $factoryData);

            if ($addFactoryResult['success']) {
                // Get the factory ID by name using the new function
                $newPabrikId = $this->getFactoryIdByName($service, $namaPerusahaanAnswer);

                if (!empty($newPabrikId)) {
                    $record->update(['pabrik_id' => $newPabrikId]);
                    Log::info('Factory created successfully', [
                        'submission_id' => $record->submission_id,
                        'pabrik_id' => $newPabrikId,
                        'factory_data' => $factoryData,
                    ]);

                    // Add factory to submission
                    $addFactoryToSubmissionResult = $service->addFactoryToSubmission($idReg, $newPabrikId);

                    if ($addFactoryToSubmissionResult['success']) {
                        Log::info('Factory added to submission successfully', [
                            'submission_id' => $record->submission_id,
                            'id_reg' => $idReg,
                            'id_pabrik' => $newPabrikId,
                        ]);
                    } else {
                        Log::warning('Failed to add factory to submission', [
                            'submission_id' => $record->submission_id,
                            'id_reg' => $idReg,
                            'id_pabrik' => $newPabrikId,
                            'message' => $addFactoryToSubmissionResult['message'],
                            'status' => $addFactoryToSubmissionResult['status'],
                        ]);
                    }
                } else {
                    Log::warning('Factory created but ID not found in response', [
                        'submission_id' => $record->submission_id,
                        'response' => $addFactoryResult['data'],
                    ]);
                }

                return [
                    'success' => true,
                    'message' => 'Factory created successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Gagal membuat factory: {$addFactoryResult['message']} (Status: {$addFactoryResult['status']})"
                ];
            }
        } else {
            // Missing required data
            $missingFields = [];
            if (empty($factoryData['city'])) $missingFields[] = 'city';
            if (empty($factoryData['province'])) $missingFields[] = 'province';
            if (empty($factoryData['status'])) $missingFields[] = 'status';

            $errorMessage = 'Gagal membuat factory - data tidak lengkap: ' . implode(', ', $missingFields);
            if (!empty($factoryData['city'])) {
                $errorMessage .= " (City: '{$alamatPerusahaanAnswer['city']}' tidak ditemukan di province {$provinceCode})";
            }

            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }
    }

    /**
     * Reset existing Komitmen dan Tanggung Jawab data
     *
     * @param \App\Models\JotformSync $record
     * @param \App\Services\HalalGoIdService $service
     * @param string $idReg
     * @param array &$komitmenNotes - Reference to notes array to append errors
     * @return void
     */
    protected function resetKomitmenTanggungJawab($record, $service, $idReg, &$komitmenNotes): void
    {
        // Get existing tim-manajemen-halal data
        $detailTabResult = $service->getDetailTab(
            idReg: $idReg,
            type: 'tim-manajemen-halal',
            page: 1,
            size: 10
        );

        if ($detailTabResult['success']) {
            $detailData = $detailTabResult['data']['data'] ?? [];

            if (!empty($detailData)) {
                Log::info('Existing Tim Manajemen Halal data found', [
                    'submission_id' => $record->submission_id,
                    'total_items' => count($detailData),
                ]);

                // Loop through existing data
                foreach ($detailData as $item) {
                    Log::info('Processing existing Tim Manajemen Halal item', [
                        'submission_id' => $record->submission_id,
                        'item' => $item,
                    ]);

                    // Delete existing item
                    $deleteResult = $service->deleteKomitmenTanggungJawab(
                        id: $item['id_reg'],
                        idEdit: $item['id_reg_tim']
                    );

                    if ($deleteResult['success']) {
                        Log::info('Successfully deleted existing Tim Manajemen Halal item', [
                            'submission_id' => $record->submission_id,
                            'item' => $item,
                            'response' => $deleteResult['data'],
                        ]);
                    } else {
                        $komitmenNotes[] = "Gagal menghapus data lama: {$deleteResult['message']} (Status: {$deleteResult['status']})";
                        Log::warning('Failed to delete existing Tim Manajemen Halal item', [
                            'submission_id' => $record->submission_id,
                            'item' => $item,
                            'message' => $deleteResult['message'],
                            'status' => $deleteResult['status'],
                        ]);
                    }
                }
            } else {
                Log::info('No existing Tim Manajemen Halal data found', [
                    'submission_id' => $record->submission_id,
                ]);
            }
        } else {
            $komitmenNotes[] = "Gagal mengambil data Tim Manajemen Halal yang ada: {$detailTabResult['message']} (Status: {$detailTabResult['status']})";
            Log::warning('Failed to get existing Tim Manajemen Halal data', [
                'submission_id' => $record->submission_id,
                'message' => $detailTabResult['message'],
                'status' => $detailTabResult['status'],
            ]);
        }
    }

    /**
     * Reset existing Bahan data
     *
     * @param \App\Models\JotformSync $record
     * @param \App\Services\HalalGoIdService $service
     * @param string $idReg
     * @param array &$bahanNotes - Reference to notes array to append errors
     * @return void
     */
    protected function resetBahan($record, $service, $idReg, &$bahanNotes): void
    {
        // Get existing ingredient list
        $ingredientListResult = $service->getIngredientList($idReg);

        if ($ingredientListResult['success']) {
            $ingredientData = $ingredientListResult['data']['data'] ?? [];

            if (!empty($ingredientData)) {
                Log::info('Existing Bahan data found', [
                    'submission_id' => $record->submission_id,
                    'total_items' => count($ingredientData),
                ]);

                // Loop through existing data
                foreach ($ingredientData as $item) {
                    Log::info('Processing existing Bahan item', [
                        'submission_id' => $record->submission_id,
                        'item' => $item,
                    ]);

                    // Delete existing ingredient using product_id
                    $productId = $item['id'] ?? null;
                    if ($productId) {
                        $removeResult = $service->removeIngredient(
                            idReg: $idReg,
                            productId: $productId
                        );

                        if ($removeResult['success']) {
                            Log::info('Successfully deleted existing Bahan item', [
                                'submission_id' => $record->submission_id,
                                'product_id' => $productId,
                                'response' => $removeResult['data'],
                            ]);
                        } else {
                            $bahanNotes[] = "Gagal menghapus data lama: {$removeResult['message']} (Status: {$removeResult['status']})";
                            Log::warning('Failed to delete existing Bahan item', [
                                'submission_id' => $record->submission_id,
                                'product_id' => $productId,
                                'message' => $removeResult['message'],
                                'status' => $removeResult['status'],
                            ]);
                        }
                    }
                }
            } else {
                Log::info('No existing Bahan data found', [
                    'submission_id' => $record->submission_id,
                ]);
            }
        } else {
            $bahanNotes[] = "Gagal mengambil data Bahan yang ada: {$ingredientListResult['message']} (Status: {$ingredientListResult['status']})";
            Log::warning('Failed to get existing Bahan data', [
                'submission_id' => $record->submission_id,
                'message' => $ingredientListResult['message'],
                'status' => $ingredientListResult['status'],
            ]);
        }
    }

    /**
     * Reset existing Produk data
     *
     * @param \App\Models\JotformSync $record
     * @param \App\Services\HalalGoIdService $service
     * @param string $idReg
     * @param array &$bahanNotes - Reference to notes array to append errors
     * @return void
     */
    protected function resetProduk($record, $service, $idReg, &$bahanNotes): void
    {
        // Get existing product list
        $productListResult = $service->getProductListForReset($idReg);

        if ($productListResult['success']) {
            $productData = $productListResult['data']['data'] ?? [];

            if (!empty($productData)) {
                Log::info('Existing Produk data found', [
                    'submission_id' => $record->submission_id,
                    'total_items' => count($productData),
                ]);

                // Loop through existing data
                foreach ($productData as $item) {
                    Log::info('Processing existing Produk item', [
                        'submission_id' => $record->submission_id,
                        'item' => $item,
                    ]);

                    // Delete existing product using id
                    $productId = $item['id'] ?? null;
                    if ($productId) {
                        $removeResult = $service->removeProduct(
                            idReg: $idReg,
                            productId: $productId
                        );

                        if ($removeResult['success']) {
                            Log::info('Successfully deleted existing Produk item', [
                                'submission_id' => $record->submission_id,
                                'product_id' => $productId,
                                'response' => $removeResult['data'],
                            ]);
                        } else {
                            $bahanNotes[] = "Gagal menghapus data lama: {$removeResult['message']} (Status: {$removeResult['status']})";
                            Log::warning('Failed to delete existing Produk item', [
                                'submission_id' => $record->submission_id,
                                'product_id' => $productId,
                                'message' => $removeResult['message'],
                                'status' => $removeResult['status'],
                            ]);
                        }
                    }
                }
            } else {
                Log::info('No existing Produk data found', [
                    'submission_id' => $record->submission_id,
                ]);
            }
        } else {
            $bahanNotes[] = "Gagal mengambil data Produk yang ada: {$productListResult['message']} (Status: {$productListResult['status']})";
            Log::warning('Failed to get existing Produk data', [
                'submission_id' => $record->submission_id,
                'message' => $productListResult['message'],
                'status' => $productListResult['status'],
            ]);
        }
    }

    /**
     * Reset existing Layout data
     *
     * @param \App\Models\JotformSync $record
     * @param \App\Services\HalalGoIdService $service
     * @param string $idReg
     * @param array &$prosesNotes - Reference to notes array to append errors
     * @return void
     */
    protected function resetLayout($record, $service, $idReg, &$prosesNotes): void
    {
        // Get existing layout list
        $layoutListResult = $service->getLayoutList($idReg);
        
        if ($layoutListResult['success']) {
            $layoutData = $layoutListResult['data']['data'] ?? [];

            if (!empty($layoutData)) {
                Log::info('Existing Layout data found', [
                    'submission_id' => $record->submission_id,
                    'total_items' => count($layoutData),
                ]);

                // Loop through existing data
                foreach ($layoutData as $item) {
                    Log::info('Processing existing Layout item', [
                        'submission_id' => $record->submission_id,
                        'item' => $item,
                    ]);

                    // Delete existing layout using id
                    $layoutId = $item['id_reg_layout'] ?? null;
                    if ($layoutId) {
                        $removeResult = $service->removeLayout(
                            idReg: $idReg,
                            idLayout: $layoutId
                        );

                        if ($removeResult['success']) {
                            Log::info('Successfully deleted existing Layout item', [
                                'submission_id' => $record->submission_id,
                                'layout_id' => $layoutId,
                                'response' => $removeResult['data'],
                            ]);
                        } else {
                            $prosesNotes[] = "Gagal menghapus data lama: {$removeResult['message']} (Status: {$removeResult['status']})";
                            Log::warning('Failed to delete existing Layout item', [
                                'submission_id' => $record->submission_id,
                                'layout_id' => $layoutId,
                                'message' => $removeResult['message'],
                                'status' => $removeResult['status'],
                            ]);
                        }
                    }
                }
            } else {
                Log::info('No existing Layout data found', [
                    'submission_id' => $record->submission_id,
                ]);
            }
        } else {
            $prosesNotes[] = "Gagal mengambil data Layout yang ada: {$layoutListResult['message']} (Status: {$layoutListResult['status']})";
            Log::warning('Failed to get existing Layout data', [
                'submission_id' => $record->submission_id,
                'message' => $layoutListResult['message'],
                'status' => $layoutListResult['status'],
            ]);
        }
    }

    /**
     * Reset existing Diagram Alur data
     *
     * @param \App\Models\JotformSync $record
     * @param \App\Services\HalalGoIdService $service
     * @param string $idReg
     * @param array &$prosesNotes - Reference to notes array to append errors
     * @return void
     */
    protected function resetDiagramAlur($record, $service, $idReg, &$prosesNotes): void
    {
        // Get existing diagram alur list
        $diagramAlurListResult = $service->getDiagramAlurList($idReg);

        if ($diagramAlurListResult['success']) {
            $diagramAlurData = $diagramAlurListResult['data']['data'] ?? [];

            if (!empty($diagramAlurData)) {
                Log::info('Existing Diagram Alur data found', [
                    'submission_id' => $record->submission_id,
                    'total_items' => count($diagramAlurData),
                ]);

                // Loop through existing data
                foreach ($diagramAlurData as $item) {
                    Log::info('Processing existing Diagram Alur item', [
                        'submission_id' => $record->submission_id,
                        'item' => $item,
                    ]);

                    // Delete existing diagram alur using id
                    $diagramAlurId = $item['id_narasi'] ?? null;
                    if ($diagramAlurId) {
                        $removeResult = $service->removeDiagramAlur(
                            idReg: $idReg,
                            idDiagramAlur: $diagramAlurId
                        );

                        if ($removeResult['success']) {
                            Log::info('Successfully deleted existing Diagram Alur item', [
                                'submission_id' => $record->submission_id,
                                'diagram_alur_id' => $diagramAlurId,
                                'response' => $removeResult['data'],
                            ]);
                        } else {
                            $prosesNotes[] = "Gagal menghapus data lama: {$removeResult['message']} (Status: {$removeResult['status']})";
                            Log::warning('Failed to delete existing Diagram Alur item', [
                                'submission_id' => $record->submission_id,
                                'diagram_alur_id' => $diagramAlurId,
                                'message' => $removeResult['message'],
                                'status' => $removeResult['status'],
                            ]);
                        }
                    }
                }
            } else {
                Log::info('No existing Diagram Alur data found', [
                    'submission_id' => $record->submission_id,
                ]);
            }
        } else {
            $prosesNotes[] = "Gagal mengambil data Diagram Alur yang ada: {$diagramAlurListResult['message']} (Status: {$diagramAlurListResult['status']})";
            Log::warning('Failed to get existing Diagram Alur data', [
                'submission_id' => $record->submission_id,
                'message' => $diagramAlurListResult['message'],
                'status' => $diagramAlurListResult['status'],
            ]);
        }
    }

    /**
     * Reset existing Produk data from tab-produk endpoint
     *
     * @param \App\Models\JotformSync $record
     * @param \App\Services\HalalGoIdService $service
     * @param string $idReg
     * @param array &$produkNotes - Reference to notes array to append errors
     * @return void
     */
    protected function resetProdukForTabProduk($record, $service, $idReg, &$produkNotes): void
    {
        // Get existing produk list from tab-produk endpoint
        $produkTabProdukListResult = $service->getProdukTabProdukList($idReg);

        if ($produkTabProdukListResult['success']) {
            $produkTabProdukData = $produkTabProdukListResult['data']['data'] ?? [];

            if (!empty($produkTabProdukData)) {
                Log::info('Existing Produk (tab-produk) data found', [
                    'submission_id' => $record->submission_id,
                    'total_items' => count($produkTabProdukData),
                ]);

                // Loop through existing data
                foreach ($produkTabProdukData as $item) {
                    Log::info('Processing existing Produk (tab-produk) item', [
                        'submission_id' => $record->submission_id,
                        'item' => $item,
                    ]);

                    // Delete existing produk using id
                    $produkId = $item['id'] ?? null;
                    if ($produkId) {
                        $removeResult = $service->removeProdukTabProduk(
                            idProduk: $produkId
                        );

                        if ($removeResult['success']) {
                            Log::info('Successfully deleted existing Produk (tab-produk) item', [
                                'submission_id' => $record->submission_id,
                                'produk_id' => $produkId,
                                'response' => $removeResult['data'],
                            ]);
                        } else {
                            $produkNotes[] = "Gagal menghapus data lama: {$removeResult['message']} (Status: {$removeResult['status']})";
                            Log::warning('Failed to delete existing Produk (tab-produk) item', [
                                'submission_id' => $record->submission_id,
                                'produk_id' => $produkId,
                                'message' => $removeResult['message'],
                                'status' => $removeResult['status'],
                            ]);
                        }
                    }
                }
            } else {
                Log::info('No existing Produk (tab-produk) data found', [
                    'submission_id' => $record->submission_id,
                ]);
            }
        } else {
            $produkNotes[] = "Gagal mengambil data Produk (tab-produk) yang ada: {$produkTabProdukListResult['message']} (Status: {$produkTabProdukListResult['status']})";
            Log::warning('Failed to get existing Produk (tab-produk) data', [
                'submission_id' => $record->submission_id,
                'message' => $produkTabProdukListResult['message'],
                'status' => $produkTabProdukListResult['status'],
            ]);
        }
    }

    /**
     * Reset existing Dokumen Evaluasi data
     *
     * @param \App\Models\JotformSync $record
     * @param \App\Services\HalalGoIdService $service
     * @param string $idReg
     * @param array &$pemantauanEvaluasiNotes - Reference to notes array to append errors
     * @return void
     */
    protected function resetDokumenEvaluasi($record, $service, $idReg, &$pemantauanEvaluasiNotes): void
    {
        // Get existing dokumen evaluasi list
        $dokumenEvaluasiListResult = $service->getDokumenEvaluasiList($idReg);

        if ($dokumenEvaluasiListResult['success']) {
            $dokumenEvaluasiData = $dokumenEvaluasiListResult['data']['data'] ?? [];

            if (!empty($dokumenEvaluasiData)) {
                Log::info('Existing Dokumen Evaluasi data found', [
                    'submission_id' => $record->submission_id,
                    'total_items' => count($dokumenEvaluasiData),
                ]);

                // Loop through existing data
                foreach ($dokumenEvaluasiData as $item) {
                    Log::info('Processing existing Dokumen Evaluasi item', [
                        'submission_id' => $record->submission_id,
                        'item' => $item,
                    ]);

                    // Delete existing dokumen evaluasi using docId
                    $docId = $item['id_reg_dok'] ?? $item['id'] ?? null;
                    if ($docId) {
                        $deleteResult = $service->deleteDokumenEvaluasi(
                            idReg: $idReg,
                            docId: $docId
                        );

                        if ($deleteResult['success']) {
                            Log::info('Successfully deleted existing Dokumen Evaluasi item', [
                                'submission_id' => $record->submission_id,
                                'doc_id' => $docId,
                                'response' => $deleteResult['data'],
                            ]);
                        } else {
                            $pemantauanEvaluasiNotes[] = "Gagal menghapus data lama: {$deleteResult['message']} (Status: {$deleteResult['status']})";
                            Log::warning('Failed to delete existing Dokumen Evaluasi item', [
                                'submission_id' => $record->submission_id,
                                'doc_id' => $docId,
                                'message' => $deleteResult['message'],
                                'status' => $deleteResult['status'],
                            ]);
                        }
                    }
                }
            } else {
                Log::info('No existing Dokumen Evaluasi data found', [
                    'submission_id' => $record->submission_id,
                ]);
            }
        } else {
            $pemantauanEvaluasiNotes[] = "Gagal mengambil data Dokumen Evaluasi yang ada: {$dokumenEvaluasiListResult['message']} (Status: {$dokumenEvaluasiListResult['status']})";
            Log::warning('Failed to get existing Dokumen Evaluasi data', [
                'submission_id' => $record->submission_id,
                'message' => $dokumenEvaluasiListResult['message'],
                'status' => $dokumenEvaluasiListResult['status'],
            ]);
        }
    }

    /**
     * Reset existing TTD data
     *
     * @param \App\Models\JotformSync $record
     * @param \App\Services\HalalGoIdService $service
     * @param string $idReg
     * @param array &$pemantauanEvaluasiNotes - Reference to notes array to append errors
     * @return void
     */
    protected function resetTTD($record, $service, $idReg, &$pemantauanEvaluasiNotes): void
    {
        // Get existing TTD list
        $ttdListResult = $service->getTTDList($idReg);

        if ($ttdListResult['success']) {
            $ttdData = $ttdListResult['data']['data'] ?? [];

            if (!empty($ttdData)) {
                Log::info('Existing TTD data found', [
                    'submission_id' => $record->submission_id,
                    'total_items' => count($ttdData),
                ]);

                // Loop through existing data
                foreach ($ttdData as $item) {
                    Log::info('Processing existing TTD item', [
                        'submission_id' => $record->submission_id,
                        'item' => $item,
                    ]);

                    // Delete existing TTD using id_reg_ttd
                    $docId = $item['id_reg_ttd'] ?? null;
                    if ($docId) {
                        $deleteResult = $service->deleteTTD(
                            idReg: $idReg,
                            docId: $docId
                        );

                        if ($deleteResult['success']) {
                            Log::info('Successfully deleted existing TTD item', [
                                'submission_id' => $record->submission_id,
                                'doc_id' => $docId,
                                'response' => $deleteResult['data'],
                            ]);
                        } else {
                            $pemantauanEvaluasiNotes[] = "Gagal menghapus TTD lama: {$deleteResult['message']} (Status: {$deleteResult['status']})";
                            Log::warning('Failed to delete existing TTD item', [
                                'submission_id' => $record->submission_id,
                                'doc_id' => $docId,
                                'message' => $deleteResult['message'],
                                'status' => $deleteResult['status'],
                            ]);
                        }
                    }
                }
            } else {
                Log::info('No existing TTD data found', [
                    'submission_id' => $record->submission_id,
                ]);
            }
        } else {
            $pemantauanEvaluasiNotes[] = "Gagal mengambil data TTD yang ada: {$ttdListResult['message']} (Status: {$ttdListResult['status']})";
            Log::warning('Failed to get existing TTD data', [
                'submission_id' => $record->submission_id,
                'message' => $ttdListResult['message'],
                'status' => $ttdListResult['status'],
            ]);
        }
    }

    /**
     * Find and retrieve factory ID by factory name from pelaku usaha profile
     *
     * @param \App\Services\HalalGoIdService $service
     * @param string $factoryName
     * @return string|null Factory ID or null if not found
     */
    protected function getFactoryIdByName($service, string $factoryName): ?string
    {
        $pelakuUsahaResult = $service->getPelakuUsahaProfile();

        if (!$pelakuUsahaResult['success']) {
            Log::warning('Failed to get pelaku usaha profile for factory lookup', [
                'message' => $pelakuUsahaResult['message'],
                'status' => $pelakuUsahaResult['status'],
            ]);
            return null;
        }

        $factories = $pelakuUsahaResult['data']['data']['business_actor']['factory'] ?? [];

        foreach ($factories as $factory) {
            if (strcasecmp($factory['name'], $factoryName) === 0) {
                return $factory['id'] ?? null;
            }
        }

        return null;
    }

    /**
     * Find and retrieve factory ID from pelaku usaha detail endpoint
     *
     * @param \App\Services\HalalGoIdService $service
     * @param string $idReg - ID Registrasi
     * @param string $factoryName - Factory name to search for
     * @return string|null Factory ID (id_pabrik) or null if not found
     */
    protected function getFactoryIdFromDetail($service, string $idReg, string $factoryName): ?string
    {
        $pelakuUsahaDetailResult = $service->getPelakuUsahaDetail($idReg);

        if (!$pelakuUsahaDetailResult['success']) {
            Log::warning('Failed to get pelaku usaha detail for factory lookup', [
                'id_reg' => $idReg,
                'message' => $pelakuUsahaDetailResult['message'],
                'status' => $pelakuUsahaDetailResult['status'],
            ]);
            return null;
        }

        $pabriks = $pelakuUsahaDetailResult['data']['data']['pabrik'] ?? [];

        foreach ($pabriks as $pabrik) {
            if (strcasecmp($pabrik['nama_pabrik'], $factoryName) === 0) {
                return $pabrik['id_pabrik'] ?? null;
            }
        }

        return null;
    }
}
