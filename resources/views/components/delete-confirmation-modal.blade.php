@props([
'title' => 'Supprimer l\'élément',
'isOpen' => false,
'isLoading' => false,
'action' => 'delete',
'target' => 'delete',
])

<div x-show="{{ $isOpen ? 'true' : 'false' }}" x-cloak x-transition.opacity class="fixed inset-0 z-50">
    <x-ui.modal title="{{ $title }}">
        {{ $slot }}

        <x-slot name="actions">
            <x-ui.button type="button" variant="secondary" @click="$el.closest('.fixed').style.display = 'none'">
                Annuler
            </x-ui.button>
            <x-ui.button
                type="button"
                variant="danger"
                wire:click="{{ $action }}"
                wire:loading.attr="disabled"
                wire:target="{{ $target }}"
                @click="$el.closest('.fixed').style.display = 'none'">
                <span wire:loading.remove wire:target="{{ $target }}" class="inline-flex items-center gap-2">
                    Continuer
                </span>
                <span wire:loading wire:target="{{ $target }}" class="inline-flex items-center gap-2">
                    <x-ui.spinner size="sm" />
                    Suppression...
                </span>
            </x-ui.button>
        </x-slot>
    </x-ui.modal>
</div>