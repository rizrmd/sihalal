<x-filament-panels::page>
    @php
        $tabs = $this->getTabs();
        $activeTab = request()->query('tab', array_key_first($tabs));
    @endphp

    <div class="space-y-6">
        <!-- Header Information -->
        <x-filament::section>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Informasi Submission</h3>
                    <div class="mt-4 space-y-2">
                        <p><strong>Nama Lengkap:</strong> {{ $record->nama_lengkap }}</p>
                        <p><strong>Email:</strong> {{ $record->email }}</p>
                        <p><strong>Nama SPPG:</strong> {{ $record->nama_sppg }}</p>
                        @if($record->reg_id)
                        <p><strong>Reg ID:</strong> {{ $record->reg_id }}</p>
                        @endif
                        @if($record->pabrik)
                        <p><strong>Pabrik:</strong> {{ $record->pabrik }}</p>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Status Submit</h3>
                    <div class="mt-4">
                        @if($record->status_submit)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            {{ $record->status_submit === 'submitted' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ ucfirst($record->status_submit) }}
                        </span>
                        @else
                        <span class="text-gray-500">Belum disubmit</span>
                        @endif
                    </div>
                </div>
            </div>
        </x-filament::section>

        <!-- Tabs Navigation -->
        <div style="background-color: white; border: 2px solid #d1d5db; border-radius: 8px; padding: 16px; margin-top: 24px; margin-bottom: 24px;">
            <nav style="display: flex; gap: 8px;" aria-label="Tabs">
                @foreach($tabs as $key => $tab)
                    @if($activeTab === $key)
                        <a href="?id={{ request()->input('id') }}&tab={{ $key }}"
                           style="flex: 1; text-align: center; padding-top: 16px; padding-bottom: 16px; padding-left: 24px; padding-right: 24px; border-radius: 8px; font-weight: 500; font-size: 14px; border: 2px solid #10b981; background-color: #10b981; color: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); text-decoration: none; display: block;">
                            {{ $tab['label'] }}
                        </a>
                    @else
                        <a href="?id={{ request()->input('id') }}&tab={{ $key }}"
                           style="flex: 1; text-align: center; padding-top: 16px; padding-bottom: 16px; padding-left: 24px; padding-right: 24px; border-radius: 8px; font-weight: 500; font-size: 14px; border: 2px solid #d1d5db; background-color: #f9fafb; color: #374151; text-decoration: none; display: block; transition: all 0.2s;"
                           onmouseover="this.style.backgroundColor='#f3f4f6'; this.style.borderColor='#9ca3af';"
                           onmouseout="this.style.backgroundColor='#f9fafb'; this.style.borderColor='#d1d5db';">
                            {{ $tab['label'] }}
                        </a>
                    @endif
                @endforeach
            </nav>
        </div>

        <!-- Tab Content -->
        <x-filament::section>
            @if(isset($tabs[$activeTab]))
                @include($tabs[$activeTab]['view'], ['record' => $record])
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
