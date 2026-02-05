<?php

namespace App\Filament\Pages;

use App\Models\SiHalal as ModelsSiHalal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static UnitEnum|string|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'Pengaturan API';

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    protected ?ModelsSiHalal $record = null;

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
                            ->helperText('You can find your API key in JotForm Settings > API')
                            ->required()
                            ->password()
                            ->revealable(),

                        TextInput::make('form_id')
                            ->label('Form ID')
                            ->placeholder('Enter your JotForm ID')
                            ->helperText('The Form ID can be found in the URL of your form')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Halal.go.id API Configuration')
                    ->description('Configure the API credentials for submitting factory data to halal.go.id')
                    ->schema([
                        TextInput::make('bearer_token')
                            ->label('Bearer Token')
                            ->placeholder('Enter the Bearer Token from halal.go.id')
                            ->helperText('The authorization token for halal.go.id API')
                            ->required()
                            ->password()
                            ->revealable(),

                        TextInput::make('pelaku_usaha_uuid')
                            ->label('Pelaku Usaha UUID')
                            ->placeholder('Enter the Pelaku Usaha Profile UUID')
                            ->helperText('The UUID from the pelaku usaha profile URL')
                            ->required(),
                    ])
                    ->columns(2),

                Action::make('save')
                    ->label('💾 Simpan Pengaturan')
                    ->action('save')
                    ->color('primary')
                    ->icon('heroicon-o-check'),
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
}
