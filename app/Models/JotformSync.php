<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;

class JotformSync extends Model
{
    use HasUuids;

    protected $table = 'jotform_syncs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'reg_id',
        'pabrik_id',
        'form_id',
        'submission_id',
        'payload',
        'status_submit',
        'nama_lengkap',
        'email',
        'nama_sppg',
        'pabrik_id',
        'data_pengajuan',
        'komitmen_tanggung_jawab',
        'bahan',
        'proses',
        'produk',
        'pemantauan_evaluasi',
    ];

    protected $casts = [
        'payload' => 'array',
        'data_pengajuan' => 'array',
        'komitmen_tanggung_jawab' => 'array',
        'bahan' => 'array',
        'proses' => 'array',
        'produk' => 'array',
        'pemantauan_evaluasi' => 'array',
    ];

    /**
     * Get a specific value from payload
     */
    public function getPayloadValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    /**
     * Get answer from answers array by name
     */
    public function getAnswerByName(string $name): mixed
    {
        $answers = $this->getPayloadValue('answers', []);

        foreach ($answers as $answer) {
            if (isset($answer['name']) && $answer['name'] === $name) {
                return $answer['answer'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get answer as string (convert array to comma-separated values)
     */
    protected function getAnswerAsString(string $name): ?string
    {
        $value = $this->getAnswerByName($name);

        if ($value === null) {
            return null;
        }

        // If array, convert to comma-separated string
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }

    /**
     * Get nested answer value by name and key
     * For fields like address where answer is an object with multiple keys
     */
    protected function getNestedAnswerValue(string $name, string $key): ?string
    {
        $value = $this->getAnswerByName($name);

        if ($value === null) {
            return null;
        }

        // If array (object), extract the specific key
        if (is_array($value) && isset($value[$key])) {
            return (string) $value[$key];
        }

        return null;
    }

    /**
     * Helper to get alamat_sppg from answers (name = "alamatSppg", key = "addr_line1")
     */
    public function getAlamatSppgAttribute(): ?string
    {
        return $this->getNestedAnswerValue('alamatSppg', 'addr_line1');
    }

    /**
     * Delete all files associated with this submission
     */
    public function deleteSubmissionFiles(): void
    {
        try {
            // Delete the entire submission directory
            $submissionDir = "jotform/{$this->submission_id}";
            if (Storage::disk('public')->exists($submissionDir)) {
                Storage::disk('public')->deleteDirectory($submissionDir);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete submission files', [
                'submission_id' => $this->submission_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get daftar bahan from Excel file
     * Returns array of bahan data or null if file is not valid Excel
     */
    public function getDaftarBahan(): ?array
    {
        $answer = $this->getAnswerByName('daftarBahan');

        if (!$answer || !is_array($answer) || empty($answer)) {
            return null;
        }

        // Get the first file path
        $filePath = $answer[0] ?? null;

        if (!$filePath) {
            return null;
        }

        // Check if file exists
        $fullPath = Storage::disk('public')->path($filePath);

        if (!file_exists($fullPath)) {
            Log::warning('Daftar bahan file not found', [
                'file_path' => $filePath,
                'full_path' => $fullPath,
            ]);
            return null;
        }

        // Check file extension
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (!in_array($extension, ['xls', 'xlsx'])) {
            // Not an Excel file, return null
            return null;
        }

        try {
            // Read Excel file using OpenSpout
            $reader = new XLSXReader();
            $reader->open($fullPath);

            $daftarBahan = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                // We only process the first sheet
                $rowIndex = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowIndex++;

                    // Skip header row (row 1)
                    if ($rowIndex === 1) {
                        continue;
                    }

                    // Get cells as array
                    $cells = $row->getCells();
                    $cellCount = count($cells);

                    // Check if column count is exactly 8
                    if ($cellCount !== 8) {
                        continue;
                    }

                    // Extract data from columns (0-indexed)
                    $cellValue7 = $cells[7]->getValue();
                    // Convert DateTime objects to string
                    if ($cellValue7 instanceof \DateTimeInterface) {
                        $cellValue7 = $cellValue7->format('Y-m-d');
                    } else {
                        // If empty or not a date, set to null instead of empty string
                        $cellValue7 = null;
                    }

                    $daftarBahan[] = [
                        'nama_bahan' => trim($cells[0]->getValue() ?? ''),
                        'jenis_bahan' => trim($cells[1]->getValue() ?? ''),
                        'produsen' => trim($cells[2]->getValue() ?? ''),
                        'negara' => trim($cells[3]->getValue() ?? ''),
                        'supplier' => trim($cells[4]->getValue() ?? ''),
                        'lembaga_penerbit' => trim($cells[5]->getValue() ?? ''),
                        'nomor_sertifikat' => trim($cells[6]->getValue() ?? ''),
                        'masa_berlaku' => $cellValue7,
                    ];
                }

                // Only process first sheet
                break;
            }

            $reader->close();

            return $daftarBahan;

        } catch (\Exception $e) {
            Log::error('Failed to parse daftar bahan Excel file', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get daftar nama produk from Excel file
     * Returns array of produk data or null if file is not valid Excel
     */
    public function getDaftarProduk(): ?array
    {
        $answer = $this->getAnswerByName('daftarNama');

        if (!$answer || !is_array($answer) || empty($answer)) {
            return null;
        }

        // Get the first file path
        $filePath = $answer[0] ?? null;

        if (!$filePath) {
            return null;
        }

        // Check if file exists
        $fullPath = Storage::disk('public')->path($filePath);

        if (!file_exists($fullPath)) {
            Log::warning('Daftar nama produk file not found', [
                'file_path' => $filePath,
                'full_path' => $fullPath,
            ]);
            return null;
        }

        // Check file extension
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (!in_array($extension, ['xls', 'xlsx'])) {
            // Not an Excel file, return null
            return null;
        }

        try {
            // Read Excel file using OpenSpout
            $reader = new XLSXReader();
            $reader->open($fullPath);

            $daftarProduk = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                // We only process the first sheet
                $rowIndex = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowIndex++;

                    // Skip header row (row 1)
                    if ($rowIndex === 1) {
                        continue;
                    }

                    // Get cells as array
                    $cells = $row->getCells();

                    // Skip empty rows
                    $namaProduk = trim($cells[0]->getValue() ?? '');
                    if (empty($namaProduk)) {
                        continue;
                    }

                    // Extract data from columns (only nama produk from column 1)
                    $daftarProduk[] = [
                        'nama_produk' => $namaProduk,
                        'foto_produk' => null,
                        'jumlah_bahan' => null,
                    ];
                }

                // Only process first sheet
                break;
            }

            $reader->close();

            return $daftarProduk;

        } catch (\Exception $e) {
            Log::error('Failed to parse daftar nama produk Excel file', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get catatan pembelian bahan from file upload
     * Returns array of catatan data or null if no files
     */
    public function getCatatanPembelian(): ?array
    {
        $answers = $this->payload['answers'] ?? [];

        $catatanPembelian = [];

        foreach ($answers as $answer) {
            if (isset($answer['name']) && $answer['name'] === 'catatanPembelian') {
                $files = $answer['answer'] ?? [];

                if (!is_array($files) || empty($files)) {
                    return null;
                }

                foreach ($files as $filePath) {
                    $catatanPembelian[] = [
                        'nama' => $answer['text'] ?? 'Catatan Pembelian Bahan',
                        'tipe_penambahan' => 'Unggah',
                        'jumlah' => null,
                        'tanggal_pembelian' => null,
                        'file_dokumen' => $filePath,
                    ];
                }

                return $catatanPembelian;
            }
        }

        return null;
    }

    /**
     * Get formulir pemeriksaan bahan from file upload
     * Returns array of formulir data or null if no files
     */
    public function getFormPemeriksaan(): ?array
    {
        $answers = $this->payload['answers'] ?? [];

        $formPemeriksaan = [];

        foreach ($answers as $answer) {
            if (isset($answer['name']) && $answer['name'] === 'formPemeriksaan') {
                $files = $answer['answer'] ?? [];

                if (!is_array($files) || empty($files)) {
                    return null;
                }

                foreach ($files as $filePath) {
                    $formPemeriksaan[] = [
                        'nama_produk' => $answer['text'] ?? 'Form Pemeriksaan Bahan Masuk',
                        'tipe_penambahan' => 'Unggah',
                        'lokasi' => null,
                        'tanggal_pembelian' => null,
                        'file_dokumen' => $filePath,
                    ];
                }

                return $formPemeriksaan;
            }
        }

        return null;
    }

    /**
     * Get layout denah ruang produksi
     * Returns array with nama pabrik and file layout
     */
    public function getLayoutDenah(): ?array
    {
        $answers = $this->payload['answers'] ?? [];

        $namaPabrik = null;
        $fileLayout = null;

        foreach ($answers as $answer) {
            // Get nama pabrik from namaSppg135
            if (isset($answer['name']) && $answer['name'] === 'namaSppg135') {
                $namaPabrik = $answer['answer'] ?? null;
            }

            // Get file layout from fotoLokasidenah
            if (isset($answer['name']) && $answer['name'] === 'fotoLokasidenah') {
                $files = $answer['answer'] ?? [];
                if (is_array($files) && !empty($files)) {
                    $fileLayout = $files[0];
                }
            }
        }

        if (!$namaPabrik && !$fileLayout) {
            return null;
        }

        return [
            [
                'nama_pabrik' => $namaPabrik,
                'file_layout' => $fileLayout,
            ]
        ];
    }

    /**
     * Get diagram alir proses produksi
     * Returns array with diagram data or null if no files
     */
    public function getDiagramAlir(): ?array
    {
        $answers = $this->payload['answers'] ?? [];

        $diagramAlir = [];

        foreach ($answers as $answer) {
            if (isset($answer['name']) && $answer['name'] === 'diagramAlir') {
                $files = $answer['answer'] ?? [];

                if (!is_array($files) || empty($files)) {
                    return null;
                }

                foreach ($files as $filePath) {
                    $diagramAlir[] = [
                        'nama_produk' => $answer['text'] ?? 'Diagram Alir Proses Produksi',
                        'tipe_penambahan' => 'Unggah',
                        'diagram_alur_proses' => null,
                        'file_dokumen' => $filePath,
                    ];
                }

                return $diagramAlir;
            }
        }

        return null;
    }

    /**
     * Get pemetaan produk dan pabrik
     * Returns array with nama pabrik and list of produk
     */
    public function getPemetaanProdukPabrik(): ?array
    {
        // Get nama pabrik from namaSppg135
        $namaPabrik = $this->getAnswerByName('namaSppg135');

        if (!$namaPabrik) {
            return null;
        }

        // Get daftar produk (sama seperti di tab bahan)
        $daftarProduk = $this->getDaftarProduk();

        if (!$daftarProduk || empty($daftarProduk)) {
            return null;
        }

        // Combine nama pabrik with each produk
        $pemetaan = [];
        foreach ($daftarProduk as $produk) {
            $pemetaan[] = [
                'nama_pabrik' => $namaPabrik,
                'nama_produk' => $produk['nama_produk'] ?? null,
            ];
        }

        return $pemetaan;
    }

    /**
     * Get dokumen lainnya for pemantauan dan evaluasi
     * Returns array of dokumen data with nama dokumen (without parentheses),
     * file dokumen, and dokumen pendukung (filename)
     */
    public function getDokumenLainnya(): ?array
    {
        $targetFields = [
            'kebijakanHalal',
            'fotoKebijakan152',
            'matriksProduk',
        ];

        $answers = $this->payload['answers'] ?? [];
        $dokumenLainnya = [];

        foreach ($answers as $answer) {
            if (!isset($answer['name']) || !in_array($answer['name'], $targetFields)) {
                continue;
            }

            $files = $answer['answer'] ?? [];

            if (!is_array($files) || empty($files)) {
                continue;
            }

            // Extract nama dokumen from text, remove content in parentheses
            $namaDokumen = $answer['text'] ?? '';
            $namaDokumen = preg_replace('/\s*\([^)]*\)\s*/', ' ', $namaDokumen);
            $namaDokumen = trim($namaDokumen);

            foreach ($files as $filePath) {
                // Get filename from path
                $filename = basename($filePath);

                $dokumenLainnya[] = [
                    'nama_dokumen' => $namaDokumen,
                    'file_dokumen' => $filePath,
                    'dokumen_pendukung' => $filename,
                ];
            }
        }

        return empty($dokumenLainnya) ? null : $dokumenLainnya;
    }

    /**
     * Get tanda tangan data for pemantauan dan evaluasi
     * Returns array with tanda tangan penanggung jawab, nama penyelia halal,
     * and tanda tangan penyelia halal
     */
    public function getTandaTangan(): ?array
    {
        $answers = $this->payload['answers'] ?? [];

        $tandaTanganPenanggungJawab = null;
        $namaPenyeliaHalal = null;
        $tandaTanganPenyeliaHalal = null;

        foreach ($answers as $answer) {
            // Get tanda tangan penanggung jawab from tandaTangan
            if (isset($answer['name']) && $answer['name'] === 'tandaTangan') {
                $files = $answer['answer'] ?? [];
                if (is_array($files) && !empty($files)) {
                    $tandaTanganPenanggungJawab = $files[0];
                }
            }

            // Get nama penyelia halal from namaPenyelia
            if (isset($answer['name']) && $answer['name'] === 'namaPenyelia') {
                $namaData = $answer['answer'] ?? [];
                if (is_array($namaData)) {
                    $first = $namaData['first'] ?? '';
                    $last = $namaData['last'] ?? '';
                    $namaPenyeliaHalal = trim($first . ' ' . $last);
                }
            }

            // Get tanda tangan penyelia halal from buktiPelaksanaan160
            if (isset($answer['name']) && $answer['name'] === 'buktiPelaksanaan160') {
                $files = $answer['answer'] ?? [];
                if (is_array($files) && !empty($files)) {
                    $tandaTanganPenyeliaHalal = $files[0];
                }
            }
        }

        // Only return data if at least one field has a value
        if (!$tandaTanganPenanggungJawab && !$namaPenyeliaHalal && !$tandaTanganPenyeliaHalal) {
            return null;
        }

        return [
            [
                'tanda_tangan_penanggung_jawab' => $tandaTanganPenanggungJawab,
                'nama_penyelia_halal' => $namaPenyeliaHalal,
                'tanda_tangan_penyelia_halal' => $tandaTanganPenyeliaHalal,
            ]
        ];
    }
}
