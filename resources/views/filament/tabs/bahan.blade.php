@php
    use Illuminate\Support\Facades\Storage;
@endphp

<div class="p-4 space-y-8">
    {{-- Card Notes Bahan --}}
    @if(!empty($record->bahan['status']))
        @php
            $status = $record->bahan['status'];
            $notes = $record->bahan['notes'] ?? [];
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

    <!-- Daftar Nama Bahan dan Kemasan -->
    <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981;">
        Daftar Nama Bahan dan Kemasan
    </h3>

    @php
        $daftarBahan = $record->getDaftarBahan();
    @endphp

    @if($daftarBahan && count($daftarBahan) > 0)
        <table style="width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
            <thead>
                <tr style="background-color: #10b981;">
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Nama Bahan</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Jenis Bahan</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Produsen</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Negara</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Supplier</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Lembaga Penerbit</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Nomor Sertifikat/Registrasi</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Masa Berlaku</th>
                </tr>
            </thead>
            <tbody>
                @foreach($daftarBahan as $index => $bahan)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $index + 1 }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $bahan['nama_bahan'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $bahan['jenis_bahan'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $bahan['produsen'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $bahan['negara'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $bahan['supplier'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $bahan['lembaga_penerbit'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $bahan['nomor_sertifikat'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $bahan['masa_berlaku'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 16px; font-size: 14px; color: #6b7280;">
            <p>Total: <strong style="color: #111827;">{{ count($daftarBahan) }}</strong> bahan</p>
        </div>
    @else
        <div style="text-align: center; padding: 32px 0; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <span style="font-size: 3rem; color: #d1d5db;">⊘</span>
            <p style="margin-top: 8px; font-size: 14px; color: #6b7280;">
                Tidak ada data bahan. File tidak ditemukan, bukan file Excel, atau format kolom tidak sesuai (harus 8 kolom).
            </p>
        </div>
    @endif

    <br><br>

    <!-- Daftar Nama Produk -->
    <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981;">
        Daftar Nama Produk
    </h3>

    @php
        $daftarProduk = $record->getDaftarProduk();
    @endphp

    @if($daftarProduk && count($daftarProduk) > 0)
        <table style="width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
            <thead>
                <tr style="background-color: #10b981;">
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Nama Produk</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Foto Produk</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Jumlah Bahan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($daftarProduk as $index => $produk)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $index + 1 }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $produk['nama_produk'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #9ca3af;">-</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #9ca3af;">-</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 16px; font-size: 14px; color: #6b7280;">
            <p>Total: <strong style="color: #111827;">{{ count($daftarProduk) }}</strong> produk</p>
        </div>
    @else
        <div style="text-align: center; padding: 32px 0; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <span style="font-size: 3rem; color: #d1d5db;">⊘</span>
            <p style="margin-top: 8px; font-size: 14px; color: #6b7280;">
                Tidak ada data produk. File tidak ditemukan, bukan file Excel, atau file kosong.
            </p>
        </div>
    @endif

    <br><br>

    <!-- Catatan Pembelian Bahan -->
    <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981;">
        Catatan Pembelian Bahan
    </h3>

    @php
        $catatanPembelian = $record->getCatatanPembelian();
    @endphp

    @if($catatanPembelian && count($catatanPembelian) > 0)
        <table style="width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
            <thead>
                <tr style="background-color: #10b981;">
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Nama</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Tipe Penambahan</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Jumlah</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Tanggal Pembelian</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">File Dokumen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($catatanPembelian as $index => $catatan)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $index + 1 }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $catatan['nama'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $catatan['tipe_penambahan'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #9ca3af;">-</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #9ca3af;">-</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">
                            @if($catatan['file_dokumen'])
                                <a href="{{ Storage::disk('public')->url($catatan['file_dokumen']) }}"
                                   target="_blank"
                                   style="color: #2563eb; text-decoration: none;">
                                    📄 {{ basename($catatan['file_dokumen']) }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 16px; font-size: 14px; color: #6b7280;">
            <p>Total: <strong style="color: #111827;">{{ count($catatanPembelian) }}</strong> dokumen</p>
        </div>
    @else
        <div style="text-align: center; padding: 32px 0; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <span style="font-size: 3rem; color: #d1d5db;">⊘</span>
            <p style="margin-top: 8px; font-size: 14px; color: #6b7280;">
                Tidak ada data catatan pembelian bahan.
            </p>
        </div>
    @endif

    <br><br>

    <!-- Formulir Pemeriksaan Bahan -->
    <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981;">
        Formulir Pemeriksaan Bahan
    </h3>

    @php
        $formPemeriksaan = $record->getFormPemeriksaan();
    @endphp

    @if($formPemeriksaan && count($formPemeriksaan) > 0)
        <table style="width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
            <thead>
                <tr style="background-color: #10b981;">
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Nama Produk</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Tipe Penambahan</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Lokasi</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Tanggal Pembelian</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">File Dokumen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($formPemeriksaan as $index => $form)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $index + 1 }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $form['nama_produk'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $form['tipe_penambahan'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #9ca3af;">-</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #9ca3af;">-</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">
                            @if($form['file_dokumen'])
                                <a href="{{ Storage::disk('public')->url($form['file_dokumen']) }}"
                                   target="_blank"
                                   style="color: #2563eb; text-decoration: none;">
                                    📄 {{ basename($form['file_dokumen']) }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 16px; font-size: 14px; color: #6b7280;">
            <p>Total: <strong style="color: #111827;">{{ count($formPemeriksaan) }}</strong> dokumen</p>
        </div>
    @else
        <div style="text-align: center; padding: 32px 0; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <span style="font-size: 3rem; color: #d1d5db;">⊘</span>
            <p style="margin-top: 8px; font-size: 14px; color: #6b7280;">
                Tidak ada data formulir pemeriksaan bahan.
            </p>
        </div>
    @endif
</div>
