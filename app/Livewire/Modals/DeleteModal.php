<?php

namespace App\Livewire\Modals;

use Livewire\Component;

class DeleteModal extends Component
{
    public string $itemType = 'item'; // project, server, etc.
    public bool $isOpen = false;
    public bool $isLoading = false;
    public ?string $listenerEvent = null;

    #[\Livewire\Attributes\On('delete-modal:open')]
    public function open(string $itemType = 'item', ?string $listenerEvent = null): void
    {
        $this->itemType = $itemType;
        $this->listenerEvent = $listenerEvent;
        $this->isOpen = true;
    }

    #[\Livewire\Attributes\On('delete-modal:close')]
    public function close(): void
    {
        $this->isOpen = false;
    }

    #[\Livewire\Attributes\On('delete-modal:loading')]
    public function setLoading(bool $loading = true): void
    {
        $this->isLoading = $loading;
    }

    public function render()
    {
        return view('livewire.modals.delete-modal');
    }
}
