<?php

namespace App\Livewire\System;

use App\Services\InfrastructureService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class InfrastructureSetup extends Component
{
    public array $logs = [];
    public array $status = [];
    public bool $running = false;

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $this->status = app(InfrastructureService::class)->status();
    }

    public function install(): void
    {
        if (!config('ship.allow_infra_setup')) {
            session()->flash('error', 'Active SHIP_ALLOW_INFRA_SETUP=true dans .env pour autoriser l’installation.');
            return;
        }

        $this->running = true;
        $this->logs = [];

        try {
            $this->logs = app(InfrastructureService::class)->install();
            session()->flash('success', 'Infrastructure configurée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        } finally {
            $this->running = false;
            $this->refreshStatus();
        }
    }

    public function render()
    {
        return view('livewire.system.infrastructure-setup');
    }
}
