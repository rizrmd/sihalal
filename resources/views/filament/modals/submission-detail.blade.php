@php
    use Illuminate\Support\Facades\Storage;

    $payload = $record->payload;
    $answers = $payload['answers'] ?? [];
    $submissionData = $payload['submission_data'] ?? [];

    // Get nama lengkap from 'nama' field (control_fullname type)
    $namaLengkap = '';
    foreach ($answers as $answer) {
        if (($answer['name'] ?? '') === 'nama' && ($answer['type'] ?? '') === 'control_fullname') {
            $labelNamaLengkap = $answer['text'];
            $nameAnswer = $answer['answer'] ?? [];
            if (is_array($nameAnswer)) {
                $first = $nameAnswer['first'] ?? '';
                $last = $nameAnswer['last'] ?? '';
                $namaLengkap = trim($first . ' ' . $last);
            }
            break;
        }
    }

    // Get dates
    $updatedAt = $submissionData['updated_at'] ?? null;
    $createdAt = $payload['created_at'] ?? null;
    $status = $payload['status'] ?? 'ACTIVE';

    // Helper function to get file color
    $getFileColor = function ($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $files = [
            'pdf' => 'text-red-600 bg-red-50 hover:bg-red-100',
            'jpg' => 'text-blue-600 bg-blue-50 hover:bg-blue-100',
            'jpeg' => 'text-blue-600 bg-blue-50 hover:bg-blue-100',
            'png' => 'text-blue-600 bg-blue-50 hover:bg-blue-100',
            'gif' => 'text-blue-600 bg-blue-50 hover:bg-blue-100',
            'webp' => 'text-blue-600 bg-blue-50 hover:bg-blue-100',
            'doc' => 'text-indigo-600 bg-indigo-50 hover:bg-indigo-100',
            'docx' => 'text-indigo-600 bg-indigo-50 hover:bg-indigo-100',
            'xls' => 'text-green-600 bg-green-50 hover:bg-green-100',
            'xlsx' => 'text-green-600 bg-green-50 hover:bg-green-100',
        ];
        return $files[$ext] ?? 'text-gray-600 bg-gray-50 hover:bg-gray-100';
    };

    // Helper function to check if file is an image
    $isImage = function ($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        return in_array($ext, $imageExtensions);
    };
@endphp

<div class="space-y-4">
    <!-- Header Section -->
    <div class="border-b border-gray-200 pb-4">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-medium text-gray-700 mb-1"><b>{{ $labelNamaLengkap }}</b></div>
                <div class="text-xl font-semibold text-gray-900">{{ $namaLengkap ?: 'No Name' }}</div>
            </div>
        </div>
        @if ($updatedAt)
            <div class="mt-2 text-sm text-gray-500">
                Updated At {{ \Carbon\Carbon::make($updatedAt)->isoFormat('D MMMM Y HH:mm') }}
            </div>
        @endif
    </div>

    <!-- Form Details -->
    <div class="space-y-4">
        @php
            // Sort answers by order field
            usort($answers, function($a, $b) {
                $orderA = isset($a['order']) ? intval($a['order']) : 999;
                $orderB = isset($b['order']) ? intval($b['order']) : 999;
                return $orderA <=> $orderB;
            });
        @endphp

        @foreach ($answers as $index => $answer)
            @php
                $label = $answer['text'] ?? '';
                $name = $answer['name'] ?? '';
                $value = $answer['answer'] ?? null;
                $type = $answer['type'] ?? '';

                // Skip 'nama' field as it's already displayed above
                if ($name === 'nama' && $type === 'control_fullname') {
                    continue;
                }

                // Skip if no label
                if (empty($label)) {
                    continue;
                }
            @endphp
            <div style="margin-top:0.5rem">
                <div class="text-sm font-medium text-gray-700 mb-1"><b>{{ $label }}</b></div>

                @if ($type === 'control_fileupload' && !empty($value))
                    @if (is_array($value))
                        <div style="display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px;">
                            @foreach ($value as $filePath)
                                @if (is_string($filePath))
                                    @php
                                        // Check if it's a storage path or external URL
                                        if (str_starts_with($filePath, 'jotform/')) {
                                        // It's a local storage path
                                            $filename = basename($filePath);
                                            $filename = preg_replace('/\?.*$/', '', $filename);
                                            $fileColor = $getFileColor($filename);
                                            $downloadUrl = Storage::disk('public')->url($filePath);
                                            $isImageFile = $isImage($filename);
                                        } elseif (str_starts_with($filePath, 'http')) {
                                            // It's an external URL
                                            $filename = basename(parse_url($filePath, PHP_URL_PATH));
                                            $filename = preg_replace('/\?.*$/', '', $filename);
                                            $fileColor = $getFileColor($filename);
                                            $downloadUrl = $filePath;
                                            $isImageFile = $isImage($filename);
                                        } else {
                                            continue;
                                        }
                                    @endphp
                                    @if($isImageFile)
                                        <!-- Image Preview -->
                                        <div style="flex-shrink: 0;">
                                            <a href="{{ $downloadUrl }}" target="_blank" rel="noopener noreferrer"
                                               style="display: block; width: 64px; height: 64px; border-radius: 8px; overflow: hidden; border: 2px solid #e5e7eb; transition: all 0.2s;" onmouseover="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 10px 15px -3px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none';">
                                                <img src="{{ $downloadUrl }}"
                                                     alt="{{ $filename }}"
                                                     style="width: 100%; height: 100%; object-fit: cover;"
                                                     onerror="this.parentElement.innerHTML='<div style=\\'width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background-color: #f3f4f6;\\'><svg style=\\'width: 24px; height: 24px; color: #9ca3af;\\' fill=\\'none\\' stroke=\\'currentColor\\' viewBox=\\'0 0 24 24\\'><path stroke-linecap=\\'round\\' stroke-linejoin=\\'round\\' stroke-width=\\'2\\' d=\\'M4 16l4.586-4.586a2 2 0 012.828 0l16 16m-2-2l1.586-1.586a2 2 0 012.828 0l20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\\'/></svg></div>'" />
                                            </a>
                                        </div>
                                    @else
                                        <!-- Non-Image File -->
                                        <div style="flex-shrink: 0;">
                                            <a href="{{ $downloadUrl }}" target="_blank" rel="noopener noreferrer"
                                               title="{{ $filename }}"
                                               style="display: inline-flex; flex-direction: column; align-items: center; gap: 4px; text-decoration: none;">
                                                <div style="width: 64px; height: 64px; border-radius: 8px; border: 2px solid #e5e7eb; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f9fafb; transition: all 0.2s; padding: 8px; position: relative;"
                                                   onmouseover="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 10px 15px -3px rgba(0, 0, 0, 0.1)';"
                                                   onmouseout="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none';">
                                                    <svg style="width: 24px; height: 24px; margin-bottom: 2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    <span style="font-size: 0.625rem; font-weight: 700; color: {{ $fileColor === 'text-red-600 bg-red-50 hover:bg-red-100' ? '#dc2626' : ($fileColor === 'text-blue-600 bg-blue-50 hover:bg-blue-100' ? '#2563eb' : ($fileColor === 'text-indigo-600 bg-indigo-50 hover:bg-indigo-100' ? '#4f46e5' : ($fileColor === 'text-green-600 bg-green-50 hover:bg-green-100' ? '#16a34a' : '#6b7280'))) }}; text-transform: uppercase;">
                                                        {{ pathinfo($filename, PATHINFO_EXTENSION) }}
                                                    </span>
                                                </div>
                                                <span style="font-size: 0.75rem; color: #6b7280; max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $filename }}</span>
                                            </a>
                                        </div>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    @elseif(is_string($value))
                        @php
                            // Check if it's a storage path or external URL
                            if (str_starts_with($value, 'jotform/')) {
                                // It's a local storage path
                                $filename = basename($value);
                                $filename = preg_replace('/\?.*$/', '', $filename);
                                $fileColor = $getFileColor($filename);
                                $downloadUrl = Storage::disk('public')->url($value);
                                $isImageFile = $isImage($filename);
                            } elseif (str_starts_with($value, 'http')) {
                                // It's an external URL
                                $filename = basename(parse_url($value, PHP_URL_PATH));
                                $filename = preg_replace('/\?.*$/', '', $filename);
                                $fileColor = $getFileColor($filename);
                                $downloadUrl = $value;
                                $isImageFile = $isImage($filename);
                            } else {
                                $filename = null;
                                $downloadUrl = null;
                                $isImageFile = false;
                            }
                        @endphp
                        @if ($downloadUrl)
                            @if($isImageFile)
                                <!-- Single Image Preview -->
                                <div style="display: inline-block;">
                                    <a href="{{ $downloadUrl }}" target="_blank" rel="noopener noreferrer"
                                       style="display: block; width: 64px; height: 64px; border-radius: 8px; overflow: hidden; border: 2px solid #e5e7eb; transition: all 0.2s;" onmouseover="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 10px 15px -3px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none';">
                                        <img src="{{ $downloadUrl }}"
                                             alt="{{ $filename }}"
                                             style="width: 100%; height: 100%; object-fit: cover;"
                                             onerror="this.parentElement.innerHTML='<div style=\\'width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background-color: #f3f4f6;\\'><svg style=\\'width: 24px; height: 24px; color: #9ca3af;\\' fill=\\'none\\' stroke=\\'currentColor\\' viewBox=\\'0 0 24 24\\'><path stroke-linecap=\\'round\\' stroke-linejoin=\\'round\\' stroke-width=\\'2\\' d=\\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\\'/></svg></div>'" />
                                    </a>
                                    <div style="margin-top: 0.5rem;">
                                        <span style="font-size: 0.75rem; color: #6b7280;">{{ $filename }}</span>
                                    </div>
                                </div>
                            @else
                                <!-- Non-Image File -->
                                <div style="display: inline-block;">
                                    <a href="{{ $downloadUrl }}" target="_blank" rel="noopener noreferrer"
                                       title="{{ $filename }}"
                                       style="display: inline-flex; flex-direction: column; align-items: center; gap: 4px; text-decoration: none;">
                                        <div style="width: 64px; height: 64px; border-radius: 8px; border: 2px solid #e5e7eb; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f9fafb; transition: all 0.2s; padding: 8px;"
                                           onmouseover="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 10px 15px -3px rgba(0, 0, 0, 0.1)';"
                                           onmouseout="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none';">
                                            <svg style="width: 24px; height: 24px; margin-bottom: 2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span style="font-size: 0.625rem; font-weight: 700; color: {{ $fileColor === 'text-red-600 bg-red-50 hover:bg-red-100' ? '#dc2626' : ($fileColor === 'text-blue-600 bg-blue-50 hover:bg-blue-100' ? '#2563eb' : ($fileColor === 'text-indigo-600 bg-indigo-50 hover:bg-indigo-100' ? '#4f46e5' : ($fileColor === 'text-green-600 bg-green-50 hover:bg-green-100' ? '#16a34a' : '#6b7280'))) }}; text-transform: uppercase;">
                                                {{ pathinfo($filename, PATHINFO_EXTENSION) }}
                                            </span>
                                        </div>
                                        <span style="font-size: 0.75rem; color: #6b7280; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; text-align: center;">{{ $filename }}</span>
                                    </a>
                                </div>
                            @endif
                        @endif
                    @endif
                @elseif($type !== 'control_address' && $type !== 'control_datetime' && $type !== 'control_phone' && $type !== 'control_fullname' && is_array($value))
                    <div class="text-sm text-gray-900 space-y-1">
                        @foreach ($value as $item)
                            @if (is_scalar($item))
                                <div>{{ $item }}</div>
                            @endif
                        @endforeach
                    </div>
                @elseif($type === 'control_datetime' && is_array($value))
                    @php
                        // Get datetime value only
                        $datetimeValue = $value['datetime'] ?? null;
                    @endphp
                    <div class="text-sm text-gray-900">
                        @if(!empty($datetimeValue))
                            {{ \Carbon\Carbon::parse($datetimeValue)->format('d M Y H:i') }}
                        @else
                            <span class="text-gray-400 italic">No response</span>
                        @endif
                    </div>
                @elseif($type === 'control_phone' && is_array($value))
                    @php
                        // Combine area code and phone number
                        $areaCode = $value['area'] ?? '';
                        $phoneNumber = $value['phone'] ?? '';

                        // Remove leading zero from phone number if area code exists
                        if (!empty($areaCode) && !empty($phoneNumber)) {
                            $phoneNumber = ltrim($phoneNumber, '0');
                        }

                        $fullPhone = $areaCode . $phoneNumber;
                    @endphp
                    <div class="text-sm text-gray-900">
                        @if(!empty($fullPhone))
                            {{ $fullPhone }}
                        @else
                            <span class="text-gray-400 italic">No response</span>
                        @endif
                    </div>
                @elseif($type === 'control_fullname' && is_array($value))
                    @php
                        // Combine first and last name
                        $firstName = $value['first'] ?? '';
                        $lastName = $value['last'] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                    @endphp
                    <div class="text-sm text-gray-900">
                        @if(!empty($fullName))
                            {{ $fullName }}
                        @else
                            <span class="text-gray-400 italic">No response</span>
                        @endif
                    </div>
                @elseif($type === 'control_address' && is_array($value))
                    @php
                        // Format address: addr_line1, addr_line2, city, state, postal
                        $addressParts = [];

                        if (!empty($value['addr_line1'])) {
                            $addressParts[] = $value['addr_line1'];
                        }

                        if (!empty($value['addr_line2'])) {
                            $addressParts[] = $value['addr_line2'];
                        }

                        if (!empty($value['city'])) {
                            $addressParts[] = $value['city'];
                        }

                        if (!empty($value['state'])) {
                            $addressParts[] = $value['state'];
                        }

                        if (!empty($value['postal'])) {
                            $addressParts[] = $value['postal'];
                        }

                        $formattedAddress = implode(', ', $addressParts);
                    @endphp
                    <div class="text-sm text-gray-900">
                        @if(!empty($formattedAddress))
                            {{ $formattedAddress }}
                        @else
                            <span class="text-gray-400 italic">No response</span>
                        @endif
                    </div>
                @elseif(is_bool($value))
                    <div class="text-sm text-gray-900">
                        @if ($value)
                            <span class="inline-flex items-center gap-1 text-green-600">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" style="width: 14px; height: 14px; min-width: 14px; min-height: 14px;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span>Yes</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-red-600">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" style="width: 14px; height: 14px; min-width: 14px; min-height: 14px;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>No</span>
                            </span>
                        @endif
                    </div>
                @else
                    <div class="text-sm text-gray-900 @if (empty($value)) text-gray-400 italic @endif">
                        @if (empty($value))
                            No response
                        @else
                            {!! nl2br(e($value)) !!}
                        @endif
                    </div>
                @endif
            </div>

            <!-- Add separator after each field except the last one -->
            @if (!$loop->last)
                <hr style="border-color: #e5e7eb; margin: 1rem 0;">
            @endif
        @endforeach
    </div>
</div>
