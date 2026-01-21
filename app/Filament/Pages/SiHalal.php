<?php

namespace App\Filament\Pages;

use App\Jobs\JotformSyncJob;
use App\Jobs\SubmitToHalalGoIdJob;
use App\Models\JotformSync;
use App\Models\SiHalal as ModelsSiHalal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Divider;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use UnitEnum;

class SiHalal extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static UnitEnum|string|null $navigationGroup = 'Sinkronisasi';

    protected static ?string $navigationLabel = 'SiHalal';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Sihalal Jotform';

    protected string $view = 'filament.pages.si-halal';

    public ?array $data = [];

    protected ?ModelsSiHalal $record = null;

    // Store custom table search value
    public ?string $customTableSearch = null;

    public function mount(): void
    {
        $this->record = ModelsSiHalal::query()
            ->latest()
            ->first();

        if ($this->record) {
            $this->form->fill($this->record->toArray());
        } else {
            $this->form->fill();
        }
    }

    public function updating($name, $value): void
    {
        // Capture table search updates from Livewire
        if ($name === 'tableSearch') {
            // Reset to null if empty, otherwise set the value
            $this->customTableSearch = !empty($value) ? $value : null;
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('JotForm API Configuration')
                    ->description('Enter your JotForm API key and Form ID to enable synchronization.')
                    ->schema([
                        TextInput::make('api_key')
                            ->label('API Key')
                            ->placeholder('Enter your JotForm API key')
                            ->helperText('You can find your API key in JotForm Settings > API'),

                        TextInput::make('form_id')
                            ->label('Form ID')
                            ->placeholder('Enter your JotForm ID')
                            ->helperText('The Form ID can be found in the URL of your form'),
                    ]),

                Section::make('Halal.go.id API Configuration')
                    ->description('Configure the API credentials for submitting factory data to halal.go.id')
                    ->schema([
                        TextInput::make('bearer_token')
                            ->label('Bearer Token')
                            ->placeholder('Enter the Bearer Token from halal.go.id')
                            ->helperText('The authorization token for halal.go.id API'),

                        TextInput::make('pelaku_usaha_uuid')
                            ->label('Pelaku Usaha UUID')
                            ->placeholder('Enter the Pelaku Usaha Profile UUID')
                            ->helperText('The UUID from the pelaku usaha profile URL'),
                    ]),

                Action::make('save')
                    ->label('💾 Simpan Pengaturan')
                    ->action('save'),

                Section::make()
                    ->schema([]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if ($this->record) {
            $this->record->update($data);
        } else {
            $this->record = ModelsSiHalal::create($data);
        }

        // Refresh form with latest data from database
        $this->record->refresh();
        $this->form->fill($this->record->toArray());

        Notification::make()
            ->title('Konfigurasi berhasil disimpan')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        // Always get the current search value from request
        $components = request()->input('components', []);
        $currentSearch = null;

        foreach ($components as $component) {
            if (isset($component['updates']['tableSearch'])) {
                $searchValue = $component['updates']['tableSearch'];
                $currentSearch = !empty($searchValue) ? $searchValue : null;
                break;
            }
        }

        return $table
            ->heading('Data Sinkronisasi JotForm')
            ->query(
                JotformSync::query()->when(
                    !empty($currentSearch ?? $this->customTableSearch),
                    function ($query) use ($currentSearch) {
                        $searchValue = $currentSearch ?? $this->customTableSearch;

                        // Search in dedicated columns (more efficient) and payload
                        $query->where(function ($q) use ($searchValue) {
                            $q->where('nama_lengkap', 'like', "%{$searchValue}%")
                                ->orWhere('email', 'like', "%{$searchValue}%")
                                ->orWhere('nama_sppg', 'like', "%{$searchValue}%")
                                ->orWhere('status_submit', 'like', "%{$searchValue}%")
                                // Also search in payload for other fields
                                ->orWhereRaw('CAST(payload AS CHAR) LIKE ?', ["%{$searchValue}%"]);
                        });
                    }
                )
            )
            ->emptyStateHeading('Belum Ada Data Sinkronisasi')
            ->emptyStateDescription(
                'Klik tombol "Sinkronisasi" untuk mengambil data dari JotForm.'
            )
            ->emptyStateIcon('heroicon-o-arrow-path')
            ->headerActions([
                Action::make('sync')
                    ->label(function () {
                        $isSyncing = cache()->get('jotform_sync_running', false);
                        return $isSyncing ? '⏳ Sedang Mensinkronisasi...' : '🔄 Sinkronisasi Data';
                    })
                    ->action(fn() => $this->syncJotformData())
                    ->color('primary')
                    ->disabled(fn() => cache()->get('jotform_sync_running', false)),
            ])
            ->poll('5s') // Auto refresh table every 5 seconds
            ->columns([
                TextColumn::make('nama_lengkap')
                    ->label('Nama Lengkap')
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $detailUrl = route('filament.admin.pages.submission-detail', ['id' => $record->id, 'reg_id' => $record->reg_id]);
                        $html = '<a href="' . $detailUrl . '" target="_blank" style="color: #10b981 !important; text-decoration: none !important; font-weight: 500;">' . htmlspecialchars($record->nama_lengkap) . '</a>';

                        // Add halal.go.id link icon if reg_id exists
                        if (!empty($record->reg_id)) {
                            $halalGoIdUrl = "https://ptsp.halal.go.id/sh-domestic/submission/reguler/{$record->reg_id}";
                            $html .= ' <a href="' . $halalGoIdUrl . '" target="_blank" rel="noopener noreferrer" style="color: #10b981 !important; text-decoration: none !important; margin-left: 8px; display: inline-block; font-size: 18px;" title="Lihat di Halal.go.id">
                                🔗
                            </a>';
                        }

                        // Add submit to SiHalal badge if all sections are done
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

                        if ($allDone && !empty($record->reg_id)) {
                            $submitUrl = route('submit-to-sihalal', ['id' => $record->id]);
                            $html .= ' <a href="' . $submitUrl . '" style="display: inline-block; margin-left: 12px; padding: 4px 12px; background-color: #6366f1; color: white !important; text-decoration: none !important; border-radius: 6px; font-size: 12px; font-weight: 500; white-space: nowrap;" title="Submit ke SiHalal" onclick="return confirm(\'Apakah Anda yakin ingin mensubmit data ini ke halal.go.id?\');">
                                Send to SiHalal
                            </a>';
                        }

                        return new HtmlString($html);
                    })
                    ->html(),

                TextColumn::make('email')
                    ->label('Email')
                    ->sortable(),

                TextColumn::make('nama_sppg')
                    ->label('Nama SPPG')
                    ->sortable(),

                TextColumn::make('data_pengajuan')
                    ->label('Data Pengajuan')
                    ->state(function ($record) {
                        $state = $record->data_pengajuan;
                        if (empty($state) || !isset($state['status']) || $state['status'] === 'new') {
                            return '';
                        }
                        if ($state['status'] === 'done' && empty($state['notes'])) {
                            return '✓';
                        }
                        return 'link';
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($state === '✓') {
                            return new HtmlString('<span style="color: #10b981; font-size: 1.25rem;">✓</span>');
                        }
                        if ($state === 'link') {
                            $detailUrl = route('filament.admin.pages.submission-detail', [
                                'id' => $record->id,
                                'reg_id' => $record->reg_id,
                                'tab' => 'data-pengajuan'
                            ]);
                            return new HtmlString('<a href="' . $detailUrl . '" target="_blank" style="color: #ef4444; font-size: 1.25rem; text-decoration: none;" title="Lihat detail error">✗</a>');
                        }
                        return '';
                    })
                    ->html()
                    ->alignCenter(),

                TextColumn::make('komitmen_tanggung_jawab')
                    ->label('Komitmen dan Tanggung Jawab')
                    ->state(function ($record) {
                        $state = $record->komitmen_tanggung_jawab;
                        if (empty($state) || !isset($state['status']) || $state['status'] === 'new') {
                            return '';
                        }
                        if ($state['status'] === 'done' && empty($state['notes'])) {
                            return '✓';
                        }
                        return 'link';
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($state === '✓') {
                            return new HtmlString('<span style="color: #10b981; font-size: 1.25rem;">✓</span>');
                        }
                        if ($state === 'link') {
                            $detailUrl = route('filament.admin.pages.submission-detail', [
                                'id' => $record->id,
                                'reg_id' => $record->reg_id,
                                'tab' => 'komitmen-tanggung-jawab'
                            ]);
                            return new HtmlString('<a href="' . $detailUrl . '" target="_blank" style="color: #ef4444; font-size: 1.25rem; text-decoration: none;" title="Lihat detail error">✗</a>');
                        }
                        return '';
                    })
                    ->html()
                    ->alignCenter(),

                TextColumn::make('bahan')
                    ->label('Bahan')
                    ->state(function ($record) {
                        $state = $record->bahan;
                        if (empty($state) || !isset($state['status']) || $state['status'] === 'new') {
                            return '';
                        }
                        if ($state['status'] === 'done' && empty($state['notes'])) {
                            return '✓';
                        }
                        return 'link';
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($state === '✓') {
                            return new HtmlString('<span style="color: #10b981; font-size: 1.25rem;">✓</span>');
                        }
                        if ($state === 'link') {
                            $detailUrl = route('filament.admin.pages.submission-detail', [
                                'id' => $record->id,
                                'reg_id' => $record->reg_id,
                                'tab' => 'bahan'
                            ]);
                            return new HtmlString('<a href="' . $detailUrl . '" target="_blank" style="color: #ef4444; font-size: 1.25rem; text-decoration: none;" title="Lihat detail error">✗</a>');
                        }
                        return '';
                    })
                    ->html()
                    ->alignCenter(),

                TextColumn::make('proses')
                    ->label('Proses')
                    ->state(function ($record) {
                        $state = $record->proses;
                        if (empty($state) || !isset($state['status']) || $state['status'] === 'new') {
                            return '';
                        }
                        if ($state['status'] === 'done' && empty($state['notes'])) {
                            return '✓';
                        }
                        return 'link';
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($state === '✓') {
                            return new HtmlString('<span style="color: #10b981; font-size: 1.25rem;">✓</span>');
                        }
                        if ($state === 'link') {
                            $detailUrl = route('filament.admin.pages.submission-detail', [
                                'id' => $record->id,
                                'reg_id' => $record->reg_id,
                                'tab' => 'proses'
                            ]);
                            return new HtmlString('<a href="' . $detailUrl . '" target="_blank" style="color: #ef4444; font-size: 1.25rem; text-decoration: none;" title="Lihat detail error">✗</a>');
                        }
                        return '';
                    })
                    ->html()
                    ->alignCenter(),

                TextColumn::make('produk')
                    ->label('Produk')
                    ->state(function ($record) {
                        $state = $record->produk;
                        if (empty($state) || !isset($state['status']) || $state['status'] === 'new') {
                            return '';
                        }
                        if ($state['status'] === 'done' && empty($state['notes'])) {
                            return '✓';
                        }
                        return 'link';
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($state === '✓') {
                            return new HtmlString('<span style="color: #10b981; font-size: 1.25rem;">✓</span>');
                        }
                        if ($state === 'link') {
                            $detailUrl = route('filament.admin.pages.submission-detail', [
                                'id' => $record->id,
                                'reg_id' => $record->reg_id,
                                'tab' => 'produk'
                            ]);
                            return new HtmlString('<a href="' . $detailUrl . '" target="_blank" style="color: #ef4444; font-size: 1.25rem; text-decoration: none;" title="Lihat detail error">✗</a>');
                        }
                        return '';
                    })
                    ->html()
                    ->alignCenter(),

                TextColumn::make('pemantauan_evaluasi')
                    ->label('Pemantauan dan Evaluasi')
                    ->state(function ($record) {
                        $state = $record->pemantauan_evaluasi;
                        if (empty($state) || !isset($state['status']) || $state['status'] === 'new') {
                            return '';
                        }
                        if ($state['status'] === 'done' && empty($state['notes'])) {
                            return '✓';
                        }
                        return 'link';
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($state === '✓') {
                            return new HtmlString('<span style="color: #10b981; font-size: 1.25rem;">✓</span>');
                        }
                        if ($state === 'link') {
                            $detailUrl = route('filament.admin.pages.submission-detail', [
                                'id' => $record->id,
                                'reg_id' => $record->reg_id,
                                'tab' => 'pemantauan-evaluasi'
                            ]);
                            return new HtmlString('<a href="' . $detailUrl . '" target="_blank" style="color: #ef4444; font-size: 1.25rem; text-decoration: none;" title="Lihat detail error">✗</a>');
                        }
                        return '';
                    })
                    ->html()
                    ->alignCenter(),

                TextColumn::make('alamat_sppg')
                    ->label('Alamat SPPG'),

                TextColumn::make('status_submit')
                    ->label('Status Submit')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Tanggal Sync')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->selectable()
            ->bulkActions([
                BulkAction::make('submit_bulk')
                    ->label('📤 Submit Data Terpilih')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Tidak ada data yang dipilih')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Check token validity first
                        $tokenCheck = $this->checkHalalGoIdToken();
                        if (!$tokenCheck['valid']) {
                            Notification::make()
                                ->title('Token Halal.go.id Tidak Valid!')
                                ->body($tokenCheck['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // Get record IDs
                        $recordIds = $records->pluck('id')->toArray();

                        // Dispatch job to handle submission in background
                        dispatch(new SubmitToHalalGoIdJob($recordIds, auth()->id()));

                        Notification::make()
                            ->title('Submit Sedang Diproses')
                            ->body("{$records->count()} data sedang disubmit ke halal.go.id di latar belakang. Status akan otomatis terupdate.")
                            ->success()
                            ->send();
                    })
                    ->color('success')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Data ke Halal.go.id')
                    ->modalDescription('Data yang dipilih akan disubmit ke halal.go.id. Proses ini berjalan di latar belakang.'),
            ])
            ->groupedBulkActions([
                BulkAction::make('update_all')
                    ->label('Semua')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Tidak ada data yang dipilih')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Jalankan SubmitToHalalGoIdJob
                        $recordIds = $records->pluck('id')->toArray();
                        dispatch(new \App\Jobs\SubmitToHalalGoIdJob($recordIds, auth()->id()));

                        Notification::make()
                            ->title('Submit Semua Data Sedang Diproses')
                            ->body("{$records->count()} data sedang disubmit ke halal.go.id di latar belakang. Status akan otomatis terupdate.")
                            ->success()
                            ->send();
                    })
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Semua Data ke Halal.go.id')
                    ->modalDescription('Semua data akan disubmit ke halal.go.id. Status akan otomatis terupdate setelah submit berhasil.'),

                BulkAction::make('update_data_pengajuan')
                    ->label('Data Pengajuan')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Tidak ada data yang dipilih')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Check token validity first
                        $tokenCheck = $this->checkHalalGoIdToken();
                        if (!$tokenCheck['valid']) {
                            Notification::make()
                                ->title('Token Halal.go.id Tidak Valid!')
                                ->body($tokenCheck['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // Dispatch job untuk section data_pengajuan saja
                        $recordIds = $records->pluck('id')->toArray();
                        dispatch(new \App\Jobs\SubmitToHalalGoIdJob($recordIds, auth()->id(), 'data_pengajuan'));

                        Notification::make()
                            ->title('Submit Data Pengajuan Sedang Diproses')
                            ->body("{$records->count()} data sedang disubmit ke halal.go.id di latar belakang. Status akan otomatis terupdate.")
                            ->success()
                            ->send();
                    })
                    ->icon('heroicon-o-document-text')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Data Pengajuan ke Halal.go.id')
                    ->modalDescription('Data Pengajuan akan disubmit ke halal.go.id. Status akan otomatis terupdate setelah submit berhasil.'),

                BulkAction::make('update_komitmen_tanggung_jawab')
                    ->label('Komitmen dan Tanggung Jawab')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Tidak ada data yang dipilih')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Check token validity first
                        $tokenCheck = $this->checkHalalGoIdToken();
                        if (!$tokenCheck['valid']) {
                            Notification::make()
                                ->title('Token Halal.go.id Tidak Valid!')
                                ->body($tokenCheck['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // Dispatch job untuk section komitmen_tanggung_jawab saja
                        $recordIds = $records->pluck('id')->toArray();
                        dispatch(new \App\Jobs\SubmitToHalalGoIdJob($recordIds, auth()->id(), 'komitmen_tanggung_jawab'));

                        Notification::make()
                            ->title('Submit Komitmen dan Tanggung Jawab Sedang Diproses')
                            ->body("{$records->count()} data sedang disubmit ke halal.go.id di latar belakang. Status akan otomatis terupdate.")
                            ->success()
                            ->send();
                    })
                    ->icon('heroicon-o-user-group')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Komitmen dan Tanggung Jawab ke Halal.go.id')
                    ->modalDescription('Data Komitmen dan Tanggung Jawab akan disubmit ke halal.go.id. Status akan otomatis terupdate setelah submit berhasil.'),

                BulkAction::make('update_bahan')
                    ->label('Bahan')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Tidak ada data yang dipilih')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Check token validity first
                        $tokenCheck = $this->checkHalalGoIdToken();
                        if (!$tokenCheck['valid']) {
                            Notification::make()
                                ->title('Token Halal.go.id Tidak Valid!')
                                ->body($tokenCheck['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // Dispatch job untuk section bahan saja
                        $recordIds = $records->pluck('id')->toArray();
                        dispatch(new \App\Jobs\SubmitToHalalGoIdJob($recordIds, auth()->id(), 'bahan'));

                        Notification::make()
                            ->title('Submit Data Bahan Sedang Diproses')
                            ->body("{$records->count()} data sedang disubmit ke halal.go.id di latar belakang. Status akan otomatis terupdate.")
                            ->success()
                            ->send();
                    })
                    ->icon('heroicon-o-cube')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Data Bahan ke Halal.go.id')
                    ->modalDescription('Data Bahan akan disubmit ke halal.go.id. Status akan otomatis terupdate setelah submit berhasil.'),

                BulkAction::make('update_proses')
                    ->label('Proses')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Tidak ada data yang dipilih')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Check token validity first
                        $tokenCheck = $this->checkHalalGoIdToken();
                        if (!$tokenCheck['valid']) {
                            Notification::make()
                                ->title('Token Halal.go.id Tidak Valid!')
                                ->body($tokenCheck['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // Dispatch job untuk section proses saja
                        $recordIds = $records->pluck('id')->toArray();
                        dispatch(new \App\Jobs\SubmitToHalalGoIdJob($recordIds, auth()->id(), 'proses'));

                        Notification::make()
                            ->title('Submit Data Proses Sedang Diproses')
                            ->body("{$records->count()} data sedang disubmit ke halal.go.id di latar belakang. Status akan otomatis terupdate.")
                            ->success()
                            ->send();
                    })
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Data Proses ke Halal.go.id')
                    ->modalDescription('Data Proses akan disubmit ke halal.go.id. Status akan otomatis terupdate setelah submit berhasil.'),

                BulkAction::make('update_produk')
                    ->label('Produk')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Tidak ada data yang dipilih')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Check token validity first
                        $tokenCheck = $this->checkHalalGoIdToken();
                        if (!$tokenCheck['valid']) {
                            Notification::make()
                                ->title('Token Halal.go.id Tidak Valid!')
                                ->body($tokenCheck['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // Dispatch job untuk section produk saja
                        $recordIds = $records->pluck('id')->toArray();
                        dispatch(new \App\Jobs\SubmitToHalalGoIdJob($recordIds, auth()->id(), 'produk'));

                        Notification::make()
                            ->title('Submit Data Produk Sedang Diproses')
                            ->body("{$records->count()} data sedang disubmit ke halal.go.id di latar belakang. Status akan otomatis terupdate.")
                            ->success()
                            ->send();
                    })
                    ->icon('heroicon-o-shopping-bag')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Data Produk ke Halal.go.id')
                    ->modalDescription('Data Produk akan disubmit ke halal.go.id. Status akan otomatis terupdate setelah submit berhasil.'),

                BulkAction::make('update_pemantauan_evaluasi')
                    ->label('Pemantauan dan Evaluasi')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Tidak ada data yang dipilih')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Check token validity first
                        $tokenCheck = $this->checkHalalGoIdToken();
                        if (!$tokenCheck['valid']) {
                            Notification::make()
                                ->title('Token Halal.go.id Tidak Valid!')
                                ->body($tokenCheck['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // Dispatch job untuk section pemantauan_evaluasi saja
                        $recordIds = $records->pluck('id')->toArray();
                        dispatch(new \App\Jobs\SubmitToHalalGoIdJob($recordIds, auth()->id(), 'pemantauan_evaluasi'));

                        Notification::make()
                            ->title('Submit Data Pemantauan dan Evaluasi Sedang Diproses')
                            ->body("{$records->count()} data sedang disubmit ke halal.go.id di latar belakang. Status akan otomatis terupdate.")
                            ->success()
                            ->send();
                    })
                    ->icon('heroicon-o-clipboard-document-check')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Data Pemantauan dan Evaluasi ke Halal.go.id')
                    ->modalDescription('Data Pemantauan dan Evaluasi akan disubmit ke halal.go.id. Status akan otomatis terupdate setelah submit berhasil.'),
            ])
            ->searchable()
            ->recordActions([
                Action::make('view_detail')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detail Submission')
                    ->modalContent(function ($record) {
                        return view('filament.modals.submission-detail', ['record' => $record]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ]);
    }

    protected function syncJotformData(): void
    {
        // Set sync status to running
        cache()->put('jotform_sync_running', true, now()->addMinutes(10));

        // Dispatch job to handle sync in background
        dispatch(new JotformSyncJob(auth()->id()));

        Notification::make()
            ->title('Sinkronisasi sedang diproses')
            ->body('Data sedang disinkronisasi di latar belakang. Table akan otomatis terupdate.')
            ->info()
            ->send();

        // Refresh the table to show loading state
        $this->dispatch('refresh-table');
    }

    /**
     * Check if Halal.go.id token is valid
     * Returns array with 'valid' (bool) and 'message' (string)
     */
    protected function checkHalalGoIdToken(): array
    {
        try {
            // Get bearer token from si_halal_configuration
            $config = ModelsSiHalal::latest()->first();

            if (!$config || empty($config->bearer_token)) {
                return [
                    'valid' => false,
                    'message' => 'Token Bearer tidak ditemukan di konfigurasi SiHalal.'
                ];
            }

            // Format token with "Bearer " prefix
            $token = trim($config->bearer_token);
            $token = preg_replace('/^Bearer\s+/i', '', $token);
            $formattedToken = 'Bearer ' . $token;

            // Create Guzzle Client (same as HalalGoIdService)
            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
            ]);

            $headers = [
                'Accept' => '*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Authorization' => $formattedToken,
            ];

            $endpoint = '/api/pelaku-usaha-profile';
            $url = 'https://ptsp.halal.go.id' . $endpoint;

            $request = new \GuzzleHttp\Psr7\Request('GET', $url, $headers);
            $response = $client->sendAsync($request)->wait();

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'valid' => true,
                    'message' => 'Token valid'
                ];
            }

            // Decode response body to check error
            $responseBody = (string) $response->getBody();
            $body = json_decode($responseBody, true);

            // Check for unauthorized error
            if (
                $statusCode === 401 ||
                $statusCode === 400 ||
                (isset($body['code']) && $body['code'] === 400006) ||
                (isset($body['error']) && str_contains($body['error'], 'unauthorized'))
            ) {
                return [
                    'valid' => false,
                    'message' => 'Token Halal.go.id sudah kadaluarsa atau tidak valid. Silakan update token baru di halaman Konfigurasi SiHalal.'
                ];
            }

            return [
                'valid' => false,
                'message' => 'Gagal mengecek token: HTTP ' . $statusCode
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Gagal mengecek token: ' . $e->getMessage()
            ];
        }
    }
}
