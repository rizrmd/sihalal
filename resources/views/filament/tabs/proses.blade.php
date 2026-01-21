@php
    use Illuminate\Support\Facades\Storage;
@endphp

<div class="p-4 space-y-8">
    {{-- Card Notes Proses --}}
    @if(!empty($record->proses['status']))
        @php
            $status = $record->proses['status'];
            $notes = $record->proses['notes'] ?? [];
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

    <!-- Layout / Denah Ruang Produksi -->
    <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981;">
        Layout / Denah Ruang Produksi
    </h3>

    @php
        $layoutDenah = $record->getLayoutDenah();
    @endphp

    @if($layoutDenah && count($layoutDenah) > 0)
        <table style="width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
            <thead>
                <tr style="background-color: #10b981;">
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Nama Pabrik</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">File Layout Pabrik</th>
                </tr>
            </thead>
            <tbody>
                @foreach($layoutDenah as $index => $layout)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $index + 1 }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $layout['nama_pabrik'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">
                            @if($layout['file_layout'])
                                @php
                                    $filePath = $layout['file_layout'];
                                    $isImage = in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                @endphp

                                @if($isImage)
                                    <a href="{{ Storage::disk('public')->url($filePath) }}"
                                       target="_blank"
                                       style="display: inline-block; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 2px solid #e5e7eb; transition: all 0.2s; text-decoration: none;"
                                       onmouseover="this.style.borderColor='#10b981'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.1)';"
                                       onmouseout="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none';">
                                        <img src="{{ Storage::disk('public')->url($filePath) }}"
                                             alt="{{ basename($filePath) }}"
                                             style="width: 100%; height: 100%; object-fit: cover;" />
                                    </a>
                                @else
                                    <a href="{{ Storage::disk('public')->url($filePath) }}"
                                       target="_blank"
                                       style="color: #2563eb; text-decoration: none;">
                                        📄 {{ basename($filePath) }}
                                    </a>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 16px; font-size: 14px; color: #6b7280;">
            <p>Total: <strong style="color: #111827;">{{ count($layoutDenah) }}</strong> data</p>
        </div>
    @else
        <div style="text-align: center; padding: 32px 0; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <span style="font-size: 3rem; color: #d1d5db;">⊘</span>
            <p style="margin-top: 8px; font-size: 14px; color: #6b7280;">
                Tidak ada data layout/denah ruang produksi.
            </p>
        </div>
    @endif

    <br><br>

    <!-- Diagram Alur Proses Produksi -->
    <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981;">
        Diagram Alur Proses Produksi
    </h3>

    @php
        $diagramAlir = $record->getDiagramAlir();
    @endphp

    @if($diagramAlir && count($diagramAlir) > 0)
        <table style="width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
            <thead>
                <tr style="background-color: #10b981;">
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Nama Produk</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Tipe Penambahan</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Diagram Alur Proses</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">File Dokumen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($diagramAlir as $diagram)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $diagram['nama_produk'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">{{ $diagram['tipe_penambahan'] ?? '-' }}</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #9ca3af;">-</td>
                        <td style="padding: 12px 16px; font-size: 14px; color: #1f2937;">
                            @if($diagram['file_dokumen'])
                                @php
                                    $filePath = $diagram['file_dokumen'];
                                    $isImage = in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                @endphp

                                @if($isImage)
                                    <a href="{{ Storage::disk('public')->url($filePath) }}"
                                       target="_blank"
                                       style="display: inline-block; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 2px solid #e5e7eb; transition: all 0.2s; text-decoration: none;"
                                       onmouseover="this.style.borderColor='#10b981'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.1)';"
                                       onmouseout="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none';">
                                        <img src="{{ Storage::disk('public')->url($filePath) }}"
                                             alt="{{ basename($filePath) }}"
                                             style="width: 100%; height: 100%; object-fit: cover;" />
                                    </a>
                                @else
                                    <a href="{{ Storage::disk('public')->url($filePath) }}"
                                       target="_blank"
                                       style="color: #2563eb; text-decoration: none;">
                                        📄 {{ basename($filePath) }}
                                    </a>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 16px; font-size: 14px; color: #6b7280;">
            <p>Total: <strong style="color: #111827;">{{ count($diagramAlir) }}</strong> dokumen</p>
        </div>
    @else
        <div style="text-align: center; padding: 32px 0; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <span style="font-size: 3rem; color: #d1d5db;">⊘</span>
            <p style="margin-top: 8px; font-size: 14px; color: #6b7280;">
                Tidak ada data diagram alur proses produksi.
            </p>
        </div>
    @endif
</div>
