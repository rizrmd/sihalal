<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
    </form>

    <div class="mt-10">
        {{ $this->table }}
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>
