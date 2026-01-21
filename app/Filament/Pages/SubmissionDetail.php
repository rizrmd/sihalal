<?php

namespace App\Filament\Pages;

use App\Models\JotformSync;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class SubmissionDetail extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Detail Submission';

    protected static ?string $title = 'Detail Submission';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.submission-detail';

    public JotformSync $record;

    public function mount(): void
    {
        $id = request()->input('id');
        $this->record = JotformSync::findOrFail($id);
    }

    public function getTabs(): array
    {
        return [
            'data-pengajuan' => [
                'label' => 'Data Pengajuan',
                'icon' => 'heroicon-o-document-text',
                'view' => 'filament.tabs.data-pengajuan',
            ],
            'komitmen-tanggung-jawab' => [
                'label' => 'Komitmen dan Tanggung Jawab',
                'icon' => 'heroicon-o-user-group',
                'view' => 'filament.tabs.komitmen-tanggung-jawab',
            ],
            'bahan' => [
                'label' => 'Bahan',
                'icon' => 'heroicon-o-cube',
                'view' => 'filament.tabs.bahan',
            ],
            'proses' => [
                'label' => 'Proses',
                'icon' => 'heroicon-o-arrow-path',
                'view' => 'filament.tabs.proses',
            ],
            'produk' => [
                'label' => 'Produk',
                'icon' => 'heroicon-o-shopping-bag',
                'view' => 'filament.tabs.produk',
            ],
            'pemantauan-evaluasi' => [
                'label' => 'Pemantauan dan Evaluasi',
                'icon' => 'heroicon-o-clipboard-document-check',
                'view' => 'filament.tabs.pemantauan-evaluasi',
            ],
        ];
    }
}
