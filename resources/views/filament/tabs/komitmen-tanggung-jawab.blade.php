@php
    // Helper function to get field data
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
@endphp

{{-- Card Notes Komitmen dan Tanggung Jawab --}}
@if(!empty($record->komitmen_tanggung_jawab['status']))
    @php
        $status = $record->komitmen_tanggung_jawab['status'];
        $notes = $record->komitmen_tanggung_jawab['notes'] ?? [];
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

<!-- Section Komitmen dan Tanggung Jawab -->
<h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #10b981;">
    Komitmen dan Tanggung Jawab
</h3>

<table style="width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <thead>
        <tr style="background-color: #10b981;">
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">No</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Nama</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Jabatan</th>
            <th style="padding: 12px 16px; text-align: left; font-size: 14px; font-weight: 600; color: white; border-bottom: 2px solid #059669;">Posisi</th>
        </tr>
    </thead>
    <tbody>
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td colspan="4" style="padding: 24px; text-align: center; font-size: 14px; color: #9ca3af; font-style: italic;">
                Belum ada data
            </td>
        </tr>
    </tbody>
</table>
