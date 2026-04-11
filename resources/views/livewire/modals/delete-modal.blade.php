<div x-show="$wire.isOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50">
    <x-ui.modal :title="__('messages.delete_' . $itemType)">
        {{ $slot }}

        <x-slot name="actions">
            <x-ui.button type="button" variant="secondary" @click="$wire.close()">
                Annuler
            </x-ui.button>
            <x-ui.button
                type="button"
                variant="danger"
                :wire:click="$listenerEvent ? $listenerEvent : 'delete'"
                wire:loading.attr="disabled"
                :wire:target="$listenerEvent ? $listenerEvent : 'delete'"
                @click="$wire.close()">
                <span wire:loading.remove :wire:target="$listenerEvent ? $listenerEvent : 'delete'" class="inline-flex items-center gap-2">
                    Continuer
                </span>
                <span wire:loading :wire:target="$listenerEvent ? $listenerEvent : 'delete'" class="inline-flex items-center gap-2">
                    <x-ui.spinner size="sm" />
                    Suppression...
                </span>
            </x-ui.button>
        </x-slot>
    </x-ui.modal>
</div>