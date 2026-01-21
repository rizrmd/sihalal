@php
    use Illuminate\Support\Facades\Storage;

    // Helper function to get field text and answer from payload
    $getFieldData = function ($fieldName) use ($record) {
        $payload = $record->payload;
        if (!isset($payload['answers']) || !is_array($payload['answers'])) {
            return null;
        }

        foreach ($payload['answers'] as $field) {
            if (isset($field['name']) && $field['name'] === $fieldName) {
                return $field;
            }
        }
        return null;
    };

    // Helper function to get file color
    $getFileColor = function ($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $files = [
            'pdf' => '#dc2626',
            'jpg' => '#2563eb',
            'jpeg' => '#2563eb',
            'png' => '#2563eb',
            'gif' => '#2563eb',
            'webp' => '#2563eb',
            'doc' => '#4f46e5',
            'docx' => '#4f46e5',
            'xls' => '#16a34a',
            'xlsx' => '#16a34a',
        ];
        return $files[$ext] ?? '#6b7280';
    };

    // Helper function to check if file is an image
    $isImage = function ($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        return in_array($ext, $imageExtensions);
    };

    $fields = ['namaPerusahaan', 'nomorSurat', 'tanggalSurat', 'jenisLayanan', 'jenisProduk147', 'merekDagang', 'areaPemasaran', 'jenisProduk150', 'jenisPendaftaran'];

    $fieldsPenanggungJawab = ['namaPenanggung', 'noHp170', 'emailPenanggung'];

    // Get pabrik data
    $pabrikNama = $getFieldData('namaPerusahaan');
    $pabrikAlamat = $getFieldData('alamatSppg');
    $pabrikStatus = $getFieldData('statusPabrik');

    $namaPabrik = $pabrikNama['answer'] ?? '-';
    $alamatPabrik = isset($pabrikAlamat['answer']['addr_line1']) ? $pabrikAlamat['answer']['addr_line1'] : '-';
    $statusPabrik = $pabrikStatus['answer'] ?? '-';

    // Get penyelia halal data
    $penyeliaData = $getFieldData('namaPenyelia');
    $skphData = $getFieldData('fotoSk115');
    $spphData = $getFieldData('unggahSertifikat');
    $ktpData = $getFieldData('fileUpload');
    $noKtpData = $getFieldData('noKtp');
    $agamaData = $getFieldData('typeA');
    $sertifikatData = $getFieldData('nomorSertifikat');
    $noHpData = $getFieldData('noHp');
    $tanggalSertifikatData = $getFieldData('date');

    // Process nama penyelia (first + last)
    $namaPenyelia = '-';
    if ($penyeliaData && isset($penyeliaData['answer'])) {
        $answer = $penyeliaData['answer'];
        $first = $answer['first'] ?? '';
        $last = $answer['last'] ?? '';
        $namaPenyelia = trim($first . ' ' . $last);
    }

    // Process SKPH files
    $skphFiles = $skphData['answer'] ?? [];
    $skphHtml = [];

    // Debug: log jumlah file
    // \Log::info('SKPH Files count: ' . count($skphFiles), ['files' => $skphFiles]);

    if (is_array($skphFiles) && count($skphFiles) > 0) {
        foreach ($skphFiles as $index => $file) {
            if (empty($file)) continue;

            $filename = basename($file);
            $filename = preg_replace('/\?.*$/', '', $filename);
            $fileColor = $getFileColor($filename);
            $downloadUrl = Storage::disk('public')->url($file);
            $isImageFile = $isImage($filename);
            $ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));

            if ($isImageFile) {
                $skphHtml[] = '<a href="' . $downloadUrl . '" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 50px; height: 50px; border-radius: 6px; overflow: hidden; border: 2px solid #e5e7eb; transition: all 0.2s; text-decoration: none; margin-right: 8px; margin-bottom: 8px;" onmouseover="this.style.borderColor=\'#10b981\'; this.style.boxShadow=\'0 4px 6px -1px rgba(0, 0, 0, 0.1)\';" onmouseout="this.style.borderColor=\'#e5e7eb\'; this.style.boxShadow=\'none\';"><img src="' . $downloadUrl . '" alt="' . $filename . '" style="width: 100%; height: 100%; object-fit: cover;" /></a>';
            } else {
                $skphHtml[] = '<a href="' . $downloadUrl . '" target="_blank" rel="noopener noreferrer" title="' . $filename . '" style="display: inline-flex; flex-direction: column; align-items: center; gap: 2px; text-decoration: none; margin-right: 8px; margin-bottom: 8px;"><div style="width: 50px; height: 50px; border-radius: 6px; border: 2px solid #e5e7eb; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f9fafb; transition: all 0.2s; padding: 6px;" onmouseover="this.style.borderColor=\'#10b981\'; this.style.boxShadow=\'0 4px 6px -1px rgba(0, 0, 0, 0.1)\';" onmouseout="this.style.borderColor=\'#e5e7eb\'; this.style.boxShadow=\'none\';"><svg style="width: 18px; height: 18px; margin-bottom: 1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg><span style="font-size: 0.55rem; font-weight: 700; color: ' . $fileColor . '; text-transform: uppercase;">' . $ext . '</span></div></a>';
            }
        }
    }

    // Process SPPH files
    $spphFiles = $spphData['answer'] ?? [];
    $spphHtml = [];

    if (is_array($spphFiles) && count($spphFiles) > 0) {
        foreach ($spphFiles as $index => $file) {
            if (empty($file)) continue;

            $filename = basename($file);
            $filename = preg_replace('/\?.*$/', '', $filename);
            $fileColor = $getFileColor($filename);
            $downloadUrl = Storage::disk('public')->url($file);
            $isImageFile = $isImage($filename);
            $ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));

            if ($isImageFile) {
                $spphHtml[] = '<a href="' . $downloadUrl . '" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 50px; height: 50px; border-radius: 6px; overflow: hidden; border: 2px solid #e5e7eb; transition: all 0.2s; text-decoration: none; margin-right: 8px; margin-bottom: 8px;" onmouseover="this.style.borderColor=\'#10b981\'; this.style.boxShadow=\'0 4px 6px -1px rgba(0, 0, 0, 0.1)\';" onmouseout="this.style.borderColor=\'#e5e7eb\'; this.style.boxShadow=\'none\';"><img src="' . $downloadUrl . '" alt="' . $filename . '" style="width: 100%; height: 100%; object-fit: cover;" /></a>';
            } else {
                $spphHtml[] = '<a href="' . $downloadUrl . '" target="_blank" rel="noopener noreferrer" title="' . $filename . '" style="display: inline-flex; flex-direction: column; align-items: center; gap: 2px; text-decoration: none; margin-right: 8px; margin-bottom: 8px;"><div style="width: 50px; height: 50px; border-radius: 6px; border: 2px solid #e5e7eb; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f9fafb; transition: all 0.2s; padding: 6px;" onmouseover="this.style.borderColor=\'#10b981\'; this.style.boxShadow=\'0 4px 6px -1px rgba(0, 0, 0, 0.1)\';" onmouseout="this.style.borderColor=\'#e5e7eb\'; this.style.boxShadow=\'none\';"><svg style="width: 18px; height: 18px; margin-bottom: 1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg><span style="font-size: 0.55rem; font-weight: 700; color: ' . $fileColor . '; text-transform: uppercase;">' . $ext . '</span></div></a>';
            }
        }
    }

    // Process KTP files
    $ktpFiles = $ktpData['answer'] ?? [];
    $ktpHtml = [];
    if (is_array($ktpFiles) && count($ktpFiles) > 0) {
        foreach ($ktpFiles as $index => $file) {
            if (empty($file)) continue;
            $filename = basename($file);
            $filename = preg_replace('/\?.*$/', '', $filename);
            $fileColor = $getFileColor($filename);
            $downloadUrl = Storage::disk('public')->url($file);
            $isImageFile = $isImage($filename);
            $ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));

            if ($isImageFile) {
                $ktpHtml[] = '<a href="' . $downloadUrl . '" target="_blank" rel="noopener noreferrer" style="display: inline-block; width: 50px; height: 50px; border-radius: 6px; overflow: hidden; border: 2px solid #e5e7eb; transition: all 0.2s; text-decoration: none; margin-right: 8px; margin-bottom: 8px;" onmouseover="this.style.borderColor=\'#10b981\'; this.style.boxShadow=\'0 4px 6px -1px rgba(0, 0, 0, 0.1)\';" onmouseout="this.style.borderColor=\'#e5e7eb\'; this.style.boxShadow=\'none\';"><img src="' . $downloadUrl . '" alt="' . $filename . '" style="width: 100%; height: 100%; object-fit: cover;" /></a>';
            } else {
                $ktpHtml[] = '<a href="' . $downloadUrl . '" target="_blank" rel="noopener noreferrer" title="' . $filename . '" style="display: inline-flex; flex-direction: column; align-items: center; gap: 2px; text-decoration: none; margin-right: 8px; margin-bottom: 8px;"><div style="width: 50px; height: 50px; border-radius: 6px; border: 2px solid #e5e7eb; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f9fafb; transition: all 0.2s; padding: 6px;" onmouseover="this.style.borderColor=\'#10b981\'; this.style.boxShadow=\'0 4px 6px -1px rgba(0, 0, 0, 0.1)\';" onmouseout="this.style.borderColor=\'#e5e7eb\'; this.style.boxShadow=\'none\';"><svg style="width: 18px; height: 18px; margin-bottom: 1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg><span style="font-size: 0.55rem; font-weight: 700; color: ' . $fileColor . '; text-transform: uppercase;">' . $ext . '</span></div></a>';
            }
        }
    }

    $noKtp = $noKtpData['answer'] ?? '-';
    $agama = $agamaData['answer'] ?? '-';
    $noSertifikat = $sertifikatData['answer'] ?? '-';

    // Process no HP
    $noHp = '-';
    if ($noHpData && isset($noHpData['answer']) && is_array($noHpData['answer'])) {
        $area = $noHpData['answer']['area'] ?? '';
        $phone = $noHpData['answer']['phone'] ?? '';
        $noHp = $area . $phone;
    }

    // Process tanggal sertifikat
    $tanggalSertifikat = '-';
    if ($tanggalSertifikatData && isset($tanggalSertifikatData['answer'])) {
        $answer = $tanggalSertifikatData['answer'];
        if (is_array($answer) && isset($answer['datetime'])) {
            // Ambil hanya date portion (Y-m-d)
            $tanggalSertifikat = date('Y-m-d', strtotime($answer['datetime']));
        }
    }

    // Combine no sertifikat dan tanggal
    $noDanTglSertifikat = $noSertifikat;
    if ($noSertifikat !== '-' && $tanggalSertifikat !== '-') {
        $noDanTglSertifikat = $noSertifikat . ' / ' . $tanggalSertifikat;
    } elseif ($noSertifikat === '-' && $tanggalSertifikat !== '-') {
        $noDanTglSertifikat = $tanggalSertifikat;
    }
@endphp

{{-- Card Notes Data Pengajuan --}}
@if(!empty($record->data_pengajuan['status']))
    @php
        $status = $record->data_pengajuan['status'];
        $notes = $record->data_pengajuan['notes'] ?? [];
        $isDone = $status === 'done';
    @endphp

    @if($isDone && empty($notes))
        {{-- Success Card --}}
        <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <h4 style="font-size: 16px; font-weight: 600; color: #166534; margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Data berhasil disubmit
            </h4>
        </div>
    @else
        {{-- Error Card --}}
        @if(!empty($notes) && is_array($notes))
        <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <h4 style="font-size: 16px; font-weight: 600; color: #991b1b; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.932-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.932 3z"/>
                </svg>
                Gagal Submit ke halal.go.id ({{ count($notes) }})
            </h4>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                @foreach($notes as $index => $note)
                <div style="display: flex; align-items: flex-start; gap: 12px; padding: 8px; background-color: white; border-radius: 6px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px; background-color: #fecaca; color: #dc2626; border-radius: 50%; font-size: 12px; font-weight: 600; flex-shrink: 0;">
                        {{ $index + 1 }}
                    </span>
                    <p style="margin: 0; color: #7f1d1d; font-size: 14px; line-height: 1.5; white-space: pre-wrap;">{{ $note }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    @endif
@endif

<!-- Section Pengajuan Sertifikasi Halal -->
<h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981;">
    Pengajuan Sertifikasi Halal
</h3>

<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;margin-bottom:20px;">
    @foreach ($fields as $fieldName)
        @php
            $field = $getFieldData($fieldName);
            if (!$field) {
                continue;
            }

            $label = $field['text'] ?? $fieldName;
            $answer = $field['answer'] ?? '-';

            // Handle tanggal surat (datetime object)
            if (is_array($answer) && isset($answer['datetime'])) {
                $answer = $answer['datetime'];
            }
        @endphp

        <div style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 14px; font-weight: 500; color: #374151;">
                {{ $label }}
            </label>
            <div style="padding: 10px 14px; background-color: #f9fafb; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; color: #1f2937; min-height: 40px; display: flex; align-items: center;">
                {{ is_string($answer) ? $answer : json_encode($answer) }}
            </div>
        </div>
    @endforeach
</div>

<!-- Section Penanggung Jawab -->
<h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981;">
    Penanggung Jawab
</h3>

<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
    @foreach ($fieldsPenanggungJawab as $fieldName)
        @php
            $field = $getFieldData($fieldName);
            if (!$field) {
                continue;
            }

            $label = $field['text'] ?? $fieldName;
            $answer = $field['answer'] ?? '-';
        @endphp

        <div style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 14px; font-weight: 500; color: #374151;">
                {{ $label }}
            </label>
            <div style="padding: 10px 14px; background-color: #f9fafb; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; color: #1f2937; min-height: 40px; display: flex; align-items: center;">
                {{ is_string($answer) ? $answer : json_encode($answer) }}
            </div>
        </div>
    @endforeach
</div>

<!-- Section Pabrik -->
<h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981; margin-top: 32px;">
    Pabrik
</h3>

<table style="width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <thead>
        <tr style="background-color: #10b981;">
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Nama</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Alamat</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Status</th>
        </tr>
    </thead>
    <tbody>
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">1</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ is_string($namaPabrik) ? $namaPabrik : json_encode($namaPabrik) }}</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ is_string($alamatPabrik) ? $alamatPabrik : json_encode($alamatPabrik) }}</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ is_string($statusPabrik) ? $statusPabrik : json_encode($statusPabrik) }}</td>
        </tr>
    </tbody>
</table>

<!-- Section Penyelia Halal -->
<h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981; margin-top: 32px;">
    Penyelia Halal
</h3>

<table style="width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <thead>
        <tr style="background-color: #10b981;">
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Nama</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Unduh SKPH</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Unduh SPPH</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Unduh KTP</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No KTP</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Agama</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No/Tgl Sertif Penyelia Halal</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No Telepon</th>
        </tr>
    </thead>
    <tbody>
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">1</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $namaPenyelia }}</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{!! !empty($skphHtml) ? implode('<br>', $skphHtml) : '-' !!}</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{!! !empty($spphHtml) ? implode('<br>', $spphHtml) : '-' !!}</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{!! !empty($ktpHtml) ? implode('<br>', $ktpHtml) : '-' !!}</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ is_string($noKtp) ? $noKtp : json_encode($noKtp) }}</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ is_string($agama) ? $agama : json_encode($agama) }}</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $noDanTglSertifikat }}</td>
            <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $noHp }}</td>
        </tr>
    </tbody>
</table>
